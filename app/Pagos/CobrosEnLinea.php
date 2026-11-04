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

    public function iniciar(Reserva $reserva, ProveedorPago $proveedor, string $concepto, string $urlExito, string $urlCancelar, string $urlAviso): string
    {
        $importe = $this->porCobrar($reserva);
        if ($importe <= 0) {
            throw new ErrorPago(__('motor.error.nada_que_cobrar'));
        }
        $cobro = PagoEnLinea::query()->create([
            'propiedad_id' => $reserva->propiedad_id,
            'reserva_id' => $reserva->id,
            'pasarela' => $proveedor->nombre(),
            'estado' => 'pendiente',
            'importe' => $importe,
            'moneda' => $reserva->moneda,
        ]);
        $separador = str_contains($urlExito, '?') ? '&' : '?';
        $inicio = $proveedor->iniciar($reserva, $importe, $concepto, $urlExito.$separador.'cobro='.$cobro->id, $urlCancelar, $urlAviso, (string) $cobro->id);
        $cobro->update(['referencia' => $inicio->referencia]);

        return $inicio->url;
    }

    /**
     * Anota lo que la pasarela confirma. Comprueba que coincidan el importe y la moneda: no se
     * da por pagada una reserva con un cobro de otra cantidad.
     */
    public function confirmar(ProveedorPago $proveedor, ResultadoPago $resultado, int $propiedadId): ?PagoEnLinea
    {
        $id = str_starts_with($resultado->referencia, 'interna:') ? (int) substr($resultado->referencia, 8) : null;
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
            $pago = $this->reservas->registrarPago(
                $reserva,
                $bloqueado->importe,
                MetodoPago::Tarjeta,
                CarbonImmutable::now($reserva->propiedad->zona_horaria),
                mb_substr($proveedor->nombre().' '.($resultado->idPago ?? $bloqueado->referencia), 0, 100),
            );
            $bloqueado->update(['estado' => 'pagado', 'pago_id' => $pago->id, 'datos' => ['id_pago' => $resultado->idPago]]);
            $this->asegurarReserva($reserva->refresh());

            return $bloqueado;
        });
    }

    /**
     * Si la retención venció mientras pagaba, se intenta recuperar. Si ya se vendieron las noches, el
     * dinero entró igual: se avisa al alojamiento para que lo resuelva con el huésped.
     */
    private function asegurarReserva(Reserva $reserva): void
    {
        try {
            if ($reserva->estado === EstadoReserva::Cancelada) {
                $this->reservas->reactivar($reserva);
            }
            $this->reservas->confirmar($reserva->refresh());
        } catch (ReservaRechazada) {
            Aviso::abrir($reserva->propiedad_id, 'pago_sin_lugar', NivelAviso::Error, ['reserva' => $reserva->numero]);
        }
    }

    public static function pasarelas(Reserva $reserva): Pasarelas
    {
        return new Pasarelas($reserva->propiedad);
    }
}
