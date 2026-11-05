<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Propietarios;

use App\Comunicacion\Mensajero;
use App\Enums\FaseEstadoCuenta;
use App\Models\EstadoCuenta;
use App\Models\Propiedad;
use App\Models\Propietario;
use App\Models\RegistroAuditoria;
use App\Support\Formato;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Los estados de cuenta: se calculan en borrador, se revisan, se mandan y se anota lo que se le pagó al dueño (D-115). Aquí no se mueve dinero.
 */
final class GestorEstados
{
    public function __construct(private readonly CalculadorEstado $calculador)
    {
    }

    /**
     * El de ese mes. Un borrador se vuelve a calcular; lo que ya se le mandó al dueño no cambia si nadie lo reabre.
     */
    public function generar(Propietario $propietario, CarbonImmutable $mes): EstadoCuenta
    {
        $desde = CarbonImmutable::parse($mes->format('Y-m').'-01');
        $hasta = $desde->endOfMonth()->startOfDay();

        return DB::transaction(function () use ($propietario, $desde, $hasta): EstadoCuenta {
            $estado = EstadoCuenta::query()->where('propietario_id', $propietario->id)->where('desde', $desde->format('Y-m-d'))->lockForUpdate()->first();
            if ($estado !== null && $estado->fase !== FaseEstadoCuenta::Borrador) {
                return $estado;
            }
            $estado ??= new EstadoCuenta(['propiedad_id' => $propietario->propiedad_id, 'propietario_id' => $propietario->id, 'desde' => $desde->format('Y-m-d'), 'hasta' => $hasta->format('Y-m-d'), 'fase' => FaseEstadoCuenta::Borrador]);
            $estado->fill($this->calculador->calcular($propietario, $desde, $hasta) + ['moneda' => $propietario->propiedad->moneda])->save();

            return $estado;
        });
    }

    /**
     * Los de todos los dueños activos de una propiedad.
     *
     * @return int cuántos
     */
    public function generarTodos(Propiedad $propiedad, CarbonImmutable $mes): int
    {
        $propietarios = Propietario::query()->where('propiedad_id', $propiedad->id)->where('activo', true)->get();
        foreach ($propietarios as $propietario) {
            $this->generar($propietario, $mes);
        }

        return $propietarios->count();
    }

    /**
     * Si lo guardado ya no es lo que saldría hoy: cambió una reserva o se anotó un gasto después de mandarlo.
     */
    public function difiere(EstadoCuenta $estado): bool
    {
        return $this->calculador->calcular($estado->propietario, $estado->desde, $estado->hasta)['neto'] !== $estado->neto;
    }

    /**
     * Antes de mandarlo se vuelve a calcular, y se le avisa si tiene correo.
     */
    public function enviar(EstadoCuenta $estado): EstadoCuenta
    {
        $actual = $this->generar($estado->propietario, $estado->desde);
        if ($actual->fase !== FaseEstadoCuenta::Borrador) {
            return $actual;
        }
        $actual->update(['fase' => FaseEstadoCuenta::Enviado, 'enviado_en' => now('UTC')]);
        RegistroAuditoria::anotar('estado_cuenta.enviar', $actual, ['neto' => $actual->neto], $actual->propiedad_id);
        $this->avisar($actual);

        return $actual;
    }

    /**
     * Anota lo que se le pagó. El pago se hizo por fuera: AlojaFlow no mueve dinero.
     */
    public function pagar(EstadoCuenta $estado, CarbonImmutable $fecha, ?string $referencia): EstadoCuenta
    {
        $estado->update(['fase' => FaseEstadoCuenta::Pagado, 'pagado_en' => $fecha->format('Y-m-d'), 'referencia_pago' => blank($referencia) ? null : $referencia]);
        RegistroAuditoria::anotar('estado_cuenta.pagar', $estado, ['neto' => $estado->neto], $estado->propiedad_id);

        return $estado;
    }

    /**
     * Para corregirlo: vuelve a borrador y se recalcula con lo de hoy. Lo mandado antes ya lo vio el dueño, y lo pagado ya salió: por eso queda anotado.
     */
    public function reabrir(EstadoCuenta $estado): EstadoCuenta
    {
        $antes = ['neto' => $estado->neto, 'fase' => $estado->fase->value, 'pagado_en' => $estado->pagado_en?->format('Y-m-d'), 'referencia_pago' => $estado->referencia_pago];
        $estado->update(['fase' => FaseEstadoCuenta::Borrador, 'enviado_en' => null, 'pagado_en' => null, 'referencia_pago' => null]);
        RegistroAuditoria::anotar('estado_cuenta.reabrir', $estado, $antes, $estado->propiedad_id);

        return $this->generar($estado->propietario, $estado->desde);
    }

    private function avisar(EstadoCuenta $estado): void
    {
        $propietario = $estado->propietario;
        $idioma = $propietario->idioma ?? $estado->propiedad->idioma;
        $anterior = app()->getLocale();
        app()->setLocale($idioma);
        $datos = [
            'nombre' => $propietario->nombre,
            'propiedad' => $estado->propiedad->nombre,
            'periodo' => $estado->periodo(),
            'neto' => Formato::dinero($estado->neto, $estado->moneda),
            'enlace' => (string) app(AccesoPropietario::class)->url($propietario, $idioma),
        ];
        $asunto = __('propietarios.correo.asunto', $datos);
        $cuerpo = __('propietarios.correo.'.($datos['enlace'] === '' ? 'cuerpo_sin_enlace' : 'cuerpo'), $datos);
        app()->setLocale($anterior);
        app(Mensajero::class)->avisarSinReserva($estado->propiedad, 'estado_cuenta:'.$estado->id.':'.$estado->enviado_en?->timestamp, $propietario->correo, $asunto, $cuerpo, $idioma);
    }
}
