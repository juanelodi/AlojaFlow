<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
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
use App\Motor\Pasarelas;
use App\Reservas\GestorReservas;
use App\Reservas\ReservaRechazada;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Lleva un cobro en línea de principio a fin (D-042). Confirmarlo es idempotente: la pasarela puede
 * avisar varias veces y el huésped puede recargar la página de retorno, y el pago se anota una vez.
 */
final class CobrosEnLinea
{
    private const MINUTOS_COBRO_ABIERTO = 20;

    public function __construct(private readonly GestorReservas $reservas)
    {
    }

    /**
     * Lo que falta por cobrar para garantizar la reserva.
     */
    public function porCobrar(Reserva $reserva): int
    {
        return max(0, min($reserva->pendiente, $reserva->deposito - $reserva->pagado));
    }

    public function iniciar(Reserva $reserva, ProveedorPago $proveedor, string $concepto, string $urlExito, string $urlCancelar, string $urlAviso, ?EnlacePago $enlace = null): string
    {
        $importe = $enlace === null ? $this->porCobrar($reserva) : $enlace->importe;
        if ($importe <= 0) {
            throw new ErrorPago(__('motor.error.nada_que_cobrar'));
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
        $cobro->update(['referencia' => $inicio->referencia, 'datos' => ['url' => $inicio->url]]);

        return $inicio->url;
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
        if (! $resultado->pagado) {
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
            // El dinero ya entró a la cuenta, así que se anota de todos modos; devolverlo lo decide una persona. Un enlace puede
            // cobrar un extra que no está en el total: ahí no hay duplicado, salvo que el mismo enlace se pague dos veces.
            if ($enlace === null ? $bloqueado->importe > max(0, $reserva->pendiente) : $enlace->pagado_en !== null) {
                Aviso::abrir($reserva->propiedad_id, 'pago_duplicado', NivelAviso::Error, ['reserva' => $reserva->numero]);
            }
            $pago = $this->reservas->registrarPago(
                $reserva,
                $bloqueado->importe,
                MetodoPago::Tarjeta,
                CarbonImmutable::now($reserva->propiedad->zona_horaria),
                mb_substr($proveedor->nombre().' '.($resultado->idPago ?? $bloqueado->referencia), 0, 100),
                $enlace?->caja_id,
            );
            $bloqueado->update(['estado' => 'pagado', 'pago_id' => $pago->id, 'datos' => ['id_pago' => $resultado->idPago]]);
            if ($enlace !== null && $enlace->pagado_en === null) {
                $enlace->update(['pagado_en' => now('UTC'), 'pago_id' => $pago->id]);
            }
            $this->asegurarReserva($reserva->refresh());

            return $bloqueado;
        });
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
