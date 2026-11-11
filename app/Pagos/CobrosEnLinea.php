<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Pagos;

use App\Enums\EstadoReserva;
use App\Enums\MetodoPago;
use App\Enums\NivelAviso;
use App\Models\Aviso;
use App\Models\EnlacePago;
use App\Models\PagoEnLinea;
use App\Models\Reserva;
use App\Motor\AjustesMotor;
use App\Motor\Pasarelas;
use App\Reservas\GestorReservas;
use App\Reservas\Mensualidades;
use App\Reservas\ReservaRechazada;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Lleva un cobro en línea de principio a fin (D-042). Confirmarlo es idempotente: la pasarela puede
 * avisar varias veces y el huésped puede recargar la página de retorno, y el pago se anota una vez.
 */
final class CobrosEnLinea
{
    private const MINUTOS_COBRO_ABIERTO = 20;

    private const HORAS_ESPERA_TRANSFERENCIA = 72;

    public function __construct(private readonly GestorReservas $reservas)
    {
    }

    /**
     * Lo que falta por cobrar para garantizar la reserva y, con calendario de cobros, lo que toca pagar de él. La primera de un
     * pedido de varias unidades cobra por todas (D-158).
     */
    public function porCobrar(Reserva $reserva): int
    {
        return $this->delPedido($reserva)->sum(fn (Reserva $una): int => $this->porCobrarUna($una));
    }

    private function porCobrarUna(Reserva $reserva): int
    {
        return max(0, min($reserva->pendiente, max($reserva->deposito - $reserva->pagado, Mensualidades::porPagar($reserva))));
    }

    /**
     * Las reservas que paga un cobro de esta: todo el pedido si es la primera (D-158), y si no, solo ella.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Reserva>
     */
    private function delPedido(Reserva $reserva): \Illuminate\Database\Eloquent\Collection
    {
        return $reserva->reserva_principal_id === null && $reserva->acompanantes()->exists()
            ? $reserva->pedido()
            : new \Illuminate\Database\Eloquent\Collection([$reserva]);
    }

    public function iniciar(Reserva $reserva, ProveedorPago $proveedor, string $concepto, string $urlExito, string $urlCancelar, string $urlAviso, ?EnlacePago $enlace = null): string
    {
        $importe = $enlace === null ? $this->porCobrar($reserva) : $enlace->importe;
        if ($importe <= 0) {
            throw new ErrorPago(__('motor.error.nada_que_cobrar'));
        }
        // Ya tiene una ficha sin pagar: otra podría pagarse además de ella.
        $espera = $this->enEspera($reserva, $enlace);
        if (is_string($espera->datos['instrucciones'] ?? null)) {
            return $espera->datos['instrucciones'];
        }
        // Volver a pulsar «Pagar» sigue en el mismo cobro: con dos abiertos a la vez se pueden pagar los dos.
        $abierto = PagoEnLinea::query()->where('reserva_id', $reserva->id)->where('pasarela', $proveedor->nombre())
            ->where('estado', 'pendiente')->where('importe', $importe)->where('enlace_pago_id', $enlace?->id)->where('creado_en', '>=', now('UTC')->subMinutes(self::MINUTOS_COBRO_ABIERTO))
            ->orderByDesc('id')->first();
        $url = $abierto->datos['url'] ?? null;
        if (is_string($url)) {
            return $url;
        }
        $cobro = PagoEnLinea::query()->create([
            'propiedad_id' => $reserva->propiedad_id,
            'reserva_id' => $reserva->id,
            'enlace_pago_id' => $enlace?->id,
            'pasarela' => $proveedor->nombre(),
            'estado' => 'pendiente',
            'importe' => $importe,
            'moneda' => $reserva->moneda,
        ]);
        $separador = str_contains($urlExito, '?') ? '&' : '?';
        $inicio = $proveedor->iniciar($reserva, $importe, $concepto, $urlExito.$separador.'cobro='.$cobro->id, $urlCancelar, $urlAviso, self::referenciaInterna($cobro->id));
        $cobro->update(['referencia' => $inicio->referencia, 'datos' => ['url' => $inicio->url] + $inicio->datos]);

        return $inicio->url;
    }

    /**
     * El cobro de OXXO o transferencia que falta pagar, si no ha vencido.
     */
    public function enEspera(Reserva $reserva, ?EnlacePago $enlace = null): ?PagoEnLinea
    {
        $cobro = PagoEnLinea::query()->where('reserva_id', $reserva->id)->where('enlace_pago_id', $enlace?->id)->where('estado', 'en_espera')->orderByDesc('id')->first();
        $vence = $cobro->datos['vence'] ?? null;

        return is_numeric($vence) && (int) $vence < now()->getTimestamp() ? null : $cobro;
    }

    /**
     * Anota lo que la pasarela confirma. Comprueba que coincidan el importe y la moneda: no se
     * da por pagada una reserva con un cobro de otra cantidad.
     */
    public function confirmar(ProveedorPago $proveedor, ResultadoPago $resultado, int $propiedadId): ?PagoEnLinea
    {
        $esInterna = str_starts_with($resultado->referencia, 'interna:');
        $id = $esInterna ? self::idDeReferencia(substr($resultado->referencia, 8)) : null;
        if ($esInterna && $id === null) {
            return null;
        }
        $consulta = PagoEnLinea::query()->where('propiedad_id', $propiedadId)->where('pasarela', $proveedor->nombre());
        $cobro = $id !== null ? (clone $consulta)->find($id) : (clone $consulta)->where('referencia', $resultado->referencia)->first();
        if ($cobro === null) {
            return null;
        }
        if ($resultado->fallido) {
            $this->marcarFallido($cobro);

            return $cobro;
        }
        if (! $resultado->pagado) {
            if ($resultado->enEspera) {
                $this->ponerEnEspera($cobro, $resultado);
            }

            return $cobro;
        }
        if ($resultado->importe !== $cobro->importe || $resultado->moneda !== $cobro->moneda) {
            Aviso::abrir($cobro->propiedad_id, 'pago_no_coincide', NivelAviso::Error, ['reserva' => $cobro->reserva->numero]);
            report(new ErrorPago('Un cobro en línea no coincide con lo esperado: '.$cobro->id));

            return $cobro;
        }

        return DB::transaction(function () use ($cobro, $resultado, $proveedor): PagoEnLinea {
            $bloqueado = PagoEnLinea::query()->whereKey($cobro->id)->lockForUpdate()->firstOrFail();
            if ($bloqueado->estado === 'pagado') {
                return $bloqueado;
            }
            $reserva = $bloqueado->reserva;
            $enlace = $bloqueado->enlace_pago_id === null ? null : EnlacePago::query()->lockForUpdate()->find($bloqueado->enlace_pago_id);
            // Un enlace es de una sola reserva; sin él, la primera de un pedido paga por todas (D-158).
            $pedido = $enlace === null ? $this->delPedido($reserva) : new \Illuminate\Database\Eloquent\Collection([$reserva]);
            // El dinero ya entró a la cuenta, así que se anota de todos modos; devolverlo lo decide una persona. Un enlace puede
            // cobrar un extra que no está en el total: ahí no hay duplicado, salvo que el mismo enlace se pague dos veces.
            if ($enlace === null ? $bloqueado->importe > max(0, (int) $pedido->sum('pendiente')) : $enlace->pagado_en !== null) {
                Aviso::abrir($reserva->propiedad_id, 'pago_duplicado', NivelAviso::Error, ['reserva' => $reserva->numero]);
            }
            $referencia = mb_substr($proveedor->nombre().' '.($resultado->idPago ?? $bloqueado->referencia), 0, 100);
            $pago = null;
            foreach ($this->repartir($pedido, $bloqueado->importe) as [$una, $parte]) {
                $hecho = $this->reservas->registrarPago($una, $parte, $resultado->metodo ?? MetodoPago::Tarjeta, CarbonImmutable::now($una->propiedad->zona_horaria), $referencia, $enlace?->caja_id);
                $pago ??= $hecho;
            }
            $bloqueado->update(['estado' => 'pagado', 'pago_id' => $pago?->id, 'datos' => ['id_pago' => $resultado->idPago]]);
            if ($enlace !== null && $enlace->pagado_en === null) {
                $enlace->update(['pagado_en' => now('UTC'), 'pago_id' => $pago?->id]);
            }
            foreach ($pedido as $una) {
                $this->asegurarReserva($una->refresh());
            }

            return $bloqueado;
        });
    }

    /**
     * Se fue a pagar en una tienda o desde su banco. Sus noches no se sueltan a los 30 minutos: esperan lo que dure la ficha (D-099). Si la retención ya
     * había vencido, se intenta recuperar: el vale ya existe, y puede pagarlo.
     */
    private function ponerEnEspera(PagoEnLinea $cobro, ResultadoPago $resultado): void
    {
        DB::transaction(function () use ($cobro, $resultado): void {
            $bloqueado = PagoEnLinea::query()->lockForUpdate()->findOrFail($cobro->id);
            if (! in_array($bloqueado->estado, ['pendiente', 'en_espera'], true)) {
                return;
            }
            // Un segundo aviso puede traer menos que el primero: lo que ya se sabe de la ficha no se borra.
            $datos = array_filter(['instrucciones' => $resultado->instrucciones, 'vence' => $resultado->venceEn], fn (mixed $valor): bool => $valor !== null) + (array) $bloqueado->datos;
            $bloqueado->update(['estado' => 'en_espera', 'datos' => $datos]);
            $pedido = $bloqueado->enlace_pago_id === null ? $this->delPedido($bloqueado->reserva) : new \Illuminate\Database\Eloquent\Collection([$bloqueado->reserva]);
            foreach ($pedido as $reserva) {
                // La que no tiene retención (la hizo recepción y mandó un enlace de pago) no empieza a caducar por esto.
                if ($reserva->expira_en === null) {
                    continue;
                }
                try {
                    if ($reserva->estado === EstadoReserva::Cancelada) {
                        $this->reservas->reactivar($reserva);
                    }
                    $this->reservas->fijarVencimiento($reserva->refresh(), $this->hastaCuandoEspera($reserva, $datos['vence'] ?? null));
                } catch (ReservaRechazada) {
                    // Las noches ya se vendieron. Si paga, se avisa: lo resuelve el alojamiento.
                }
            }
        });
    }

    /**
     * Lo que dura la ficha, y nunca después del día de llegada: una ficha sin pagar no aparta noches que ya pasaron.
     */
    private function hastaCuandoEspera(Reserva $reserva, mixed $vence): Carbon
    {
        $hasta = is_numeric($vence) ? Carbon::createFromTimestamp((int) $vence)->addHour() : now('UTC')->addHours(self::HORAS_ESPERA_TRANSFERENCIA);
        $finDeLlegada = Carbon::parse($reserva->llegada->format('Y-m-d'), $reserva->propiedad->zona_horaria)->endOfDay();

        return ($hasta->lessThan($finDeLlegada) ? $hasta : $finDeLlegada)->utc();
    }

    /**
     * No pagó. Las noches se sueltan pronto, pero no de golpe: puede volver a intentarlo con otro medio.
     */
    private function marcarFallido(PagoEnLinea $cobro): void
    {
        DB::transaction(function () use ($cobro): void {
            $bloqueado = PagoEnLinea::query()->lockForUpdate()->findOrFail($cobro->id);
            if ($bloqueado->estado === 'pagado') {
                return;
            }
            $bloqueado->update(['estado' => 'fallido']);
            $pedido = $bloqueado->enlace_pago_id === null ? $this->delPedido($bloqueado->reserva) : new \Illuminate\Database\Eloquent\Collection([$bloqueado->reserva]);
            foreach ($pedido as $reserva) {
                if ($reserva->expira_en !== null) {
                    $this->reservas->fijarVencimiento($reserva, now('UTC')->addMinutes((new AjustesMotor($reserva->propiedad))->minutosRetencion()));
                }
            }
        });
    }

    /**
     * Cuánto le toca a cada reserva de un cobro: a cada una lo que le faltaba, en orden, y lo que sobre a la primera (D-158). Se
     * calcula antes de anotar nada: cada pago cambia lo que falta.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, Reserva>  $pedido
     * @return list<array{0: Reserva, 1: int}>
     */
    private function repartir(\Illuminate\Database\Eloquent\Collection $pedido, int $importe): array
    {
        if ($pedido->count() === 1) {
            return [[$pedido->first(), $importe]];
        }
        $partes = [];
        $resta = $importe;
        foreach ($pedido as $una) {
            $parte = min($resta, $this->porCobrarUna($una));
            $partes[] = [$una, $parte];
            $resta -= $parte;
        }
        $partes[0][1] += $resta;

        return array_values(array_filter($partes, fn (array $parte): bool => $parte[1] !== 0));
    }

    /**
     * Si la retención venció mientras pagaba, se intenta recuperar. Si ya se vendieron las noches, el
     * dinero entró igual: se avisa al alojamiento para que lo resuelva con el huésped. La que canceló
     * una persona no vuelve sola: esa decisión, y la de devolver el dinero, siguen siendo suyas.
     */
    public function asegurarReserva(Reserva $reserva): void
    {
        if ($reserva->estado === EstadoReserva::Cancelada && $reserva->expira_en === null) {
            Aviso::abrir($reserva->propiedad_id, 'pago_en_cancelada', NivelAviso::Error, ['reserva' => $reserva->numero]);

            return;
        }
        try {
            if ($reserva->estado === EstadoReserva::Cancelada) {
                $this->reservas->reactivar($reserva);
            }
            $this->reservas->confirmar($reserva->refresh());
        } catch (ReservaRechazada) {
            Aviso::abrir($reserva->propiedad_id, 'pago_sin_lugar', NivelAviso::Error, ['reserva' => $reserva->numero]);
        }
    }

    /**
     * Lo que viaja a la pasarela para reconocer el cobro cuando vuelve. Va firmado: otro pago de la
     * misma cuenta de la pasarela con un número cualquiera no se aplica a una reserva.
     */
    /**
     * El cobro que dice una referencia firmada, si sigue esperando el pago.
     */
    public static function pendienteDeReferencia(string $referencia, int $propiedadId, string $pasarela): ?PagoEnLinea
    {
        $id = self::idDeReferencia($referencia);

        return $id === null ? null : PagoEnLinea::query()->where('propiedad_id', $propiedadId)->where('pasarela', $pasarela)->where('estado', 'pendiente')->find($id);
    }

    public static function referenciaInterna(int $id): string
    {
        return $id.'-'.self::firmaReferencia($id);
    }

    private static function idDeReferencia(string $referencia): ?int
    {
        if (preg_match('/^([0-9]+)-([0-9a-f]{24})$/', $referencia, $partes) !== 1) {
            return null;
        }

        return hash_equals(self::firmaReferencia((int) $partes[1]), $partes[2]) ? (int) $partes[1] : null;
    }

    private static function firmaReferencia(int $id): string
    {
        return substr(hash_hmac('sha256', 'cobro:'.$id, (string) config('app.key')), 0, 24);
    }

    public static function pasarelas(Reserva $reserva): Pasarelas
    {
        return new Pasarelas($reserva->propiedad);
    }
}
