<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Sincronizacion\HotelDruid;

use App\Enums\EstadoEjecucion;
use App\Models\EjecucionSincronizacion;
use App\Models\Principal;
use App\Sincronizacion\ContextoSincronizacion;
use App\Sincronizacion\HotelDruid\Proyeccion\CajaPrincipal;
use App\Sincronizacion\HotelDruid\Proyeccion\Contexto;
use App\Sincronizacion\HotelDruid\Proyeccion\HuespedesPrincipal;
use App\Sincronizacion\HotelDruid\Proyeccion\ReservasPrincipal;
use App\Sincronizacion\HotelDruid\Proyeccion\UnidadesPrincipal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Convierte lo que una ejecución cargó en el espejo al modelo propio (subordinacion.md, parte C): unidades,
 * huéspedes, reservas con sus noches y la caja, en ese orden y en una sola transacción. Si algo falla, la
 * proyección anterior queda intacta.
 */
class Proyector
{
    /**
     * Sube cuando cambia cómo se calculan las huellas o cómo se convierte algún campo. Las huellas que guardó otra
     * generación no se pueden comparar con las de esta, así que la primera proyección de una generación pone al día
     * lo que haga falta sin anunciar nada y sin dar ningún número por reutilizado, igual que una primera carga
     * (D-028): si no, un cambio en el programa se leería como un cambio en cada reserva. La 1 fue la anterior a D-173.
     */
    public const GENERACION = 2;

    /** Con qué nombre queda la generación entre las cuentas de la ejecución. */
    public const CUENTA_GENERACION = 'generacion_proyector';

    public function proyectar(Principal $principal, EjecucionSincronizacion $ejecucion, bool $generarEventos): ResultadoProyeccion
    {
        $resultado = new ResultadoProyeccion();
        $mismaGeneracion = $this->generacionAnterior($principal, $ejecucion) === self::GENERACION;
        $contexto = new Contexto(
            $principal,
            $ejecucion,
            new Espejo($ejecucion->id),
            new Conversor($principal->propiedad->zona_horaria, $principal->propiedad->moneda),
            $resultado,
            CarbonImmutable::now('UTC')->format('Y-m-d H:i:s'),
            $mismaGeneracion,
        );

        ContextoSincronizacion::ejecutar(function () use ($contexto, $generarEventos, $mismaGeneracion): void {
            DB::transaction(function () use ($contexto, $generarEventos, $mismaGeneracion): void {
                $unidades = (new UnidadesPrincipal($contexto))->proyectar();
                $huespedes = (new HuespedesPrincipal($contexto))->proyectar();
                $reservas = (new ReservasPrincipal($contexto, $unidades, $huespedes, $generarEventos && $mismaGeneracion))->proyectar();
                (new CajaPrincipal($contexto, $reservas))->proyectar();
            });
        });
        $resultado->cuentas[self::CUENTA_GENERACION] = self::GENERACION;

        return $resultado;
    }

    /**
     * La generación del proyector que dejó la última carga buena de esta principal, o nulo si no hay o no lo dice.
     */
    private function generacionAnterior(Principal $principal, EjecucionSincronizacion $ejecucion): ?int
    {
        $anterior = EjecucionSincronizacion::query()->where('principal_id', $principal->id)->where('id', '!=', $ejecucion->id)
            ->where('estado', EstadoEjecucion::Correcta->value)->orderByDesc('id')->first();
        $generacion = $anterior?->filas[self::CUENTA_GENERACION] ?? null;

        return is_int($generacion) ? $generacion : null;
    }
}
