<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Servicios;

use App\Enums\EstadoReserva;
use App\Enums\TipoReserva;
use App\Models\BloqueoExterno;
use App\Models\ColorTipoCliente;
use App\Models\Reserva;
use App\Models\Unidad;
use Carbon\CarbonImmutable;

/**
 * Calendario por habitaciones de un mes, en modo consulta (M2).
 */
final class Calendario
{
    /**
     * @param  list<int>  $propiedades
     * @return array{dias: list<CarbonImmutable>, filas: list<array{unidad: Unidad, barras: list<array<string, mixed>>, externos: list<array<string, mixed>>}>}
     */
    public static function mes(array $propiedades, CarbonImmutable $inicio): array
    {
        $inicio = $inicio->startOfMonth();
        $fin = $inicio->addMonth(); // exclusivo
        $dias = [];
        for ($dia = $inicio; $dia->lt($fin); $dia = $dia->addDay()) {
            $dias[] = $dia;
        }

        $unidades = Unidad::query()->with('propiedad')
            ->whereIn('unidades.propiedad_id', $propiedades)->where('unidades.activa', true)
            ->join('propiedades', 'propiedades.id', '=', 'unidades.propiedad_id')
            ->orderBy('propiedades.nombre')->orderBy('unidades.orden')->orderBy('unidades.nombre')
            ->select('unidades.*')->get();

        $reservas = Reserva::query()->vigentes()->with('titular.campos')
            ->whereIn('propiedad_id', $propiedades)
            ->where('llegada', '<', $fin->format('Y-m-d'))
            ->where('salida', '>', $inicio->format('Y-m-d'))
            ->orderBy('llegada')
            ->get()
            ->groupBy('unidad_id');

        $externos = BloqueoExterno::query()->with('fuente')->whereIn('propiedad_id', $propiedades)->whereNull('eliminado_en')
            ->where('llegada', '<', $fin->format('Y-m-d'))->where('salida', '>', $inicio->format('Y-m-d'))
            ->orderBy('llegada')->get()->groupBy('unidad_id');

        $colores = ColorTipoCliente::query()->whereIn('propiedad_id', $propiedades)->get();

        $filas = [];
        foreach ($unidades as $unidad) {
            $barras = [];
            foreach ($reservas->get($unidad->id, collect()) as $reserva) {
                /** @var Reserva $reserva */
                $desde = $reserva->llegada->max($inicio);
                $hasta = $reserva->salida->min($fin);
                $barras[] = [
                    'reserva' => $reserva,
                    'columna' => (int) $inicio->diffInDays($desde) + 1,
                    'ancho' => max(1, (int) $desde->diffInDays($hasta)),
                    'sigue_antes' => $reserva->llegada->lt($inicio),
                    'sigue_despues' => $reserva->salida->gt($fin),
                    'clase' => self::clase($reserva),
                    'color' => self::colorDe($reserva, $colores),
                ];
            }
            $deportales = [];
            foreach ($externos->get($unidad->id, collect()) as $externo) {
                /** @var BloqueoExterno $externo */
                $desde = $externo->llegada->max($inicio);
                $hasta = $externo->salida->min($fin);
                $deportales[] = [
                    'columna' => (int) $inicio->diffInDays($desde) + 1,
                    'ancho' => max(1, (int) $desde->diffInDays($hasta)),
                    'sigue_antes' => $externo->llegada->lt($inicio),
                    'sigue_despues' => $externo->salida->gt($fin),
                    'fuente' => $externo->fuente->nombre,
                ];
            }
            $filas[] = ['unidad' => $unidad, 'barras' => $barras, 'externos' => $deportales];
        }

        return ['dias' => $dias, 'filas' => $filas];
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, ColorTipoCliente>  $colores
     */
    private static function colorDe(Reserva $reserva, \Illuminate\Database\Eloquent\Collection $colores): ?string
    {
        if ($colores->isEmpty() || $reserva->titular === null) {
            return null;
        }
        foreach ($reserva->titular->campos as $campo) {
            $color = $colores->first(fn (ColorTipoCliente $c): bool => $c->propiedad_id === $reserva->propiedad_id && mb_strtolower($c->campo) === mb_strtolower((string) $campo->campo) && mb_strtolower($c->valor) === mb_strtolower((string) $campo->valor));
            if ($color !== null) {
                return $color->color;
            }
        }

        return null;
    }

    private static function clase(Reserva $reserva): string
    {
        return match (true) {
            $reserva->tipo === TipoReserva::Bloqueo => 'af-barra--bloqueo',
            $reserva->salida_registrada_en !== null => 'af-barra--salio',
            $reserva->estaEnCasa() => 'af-barra--en-casa',
            $reserva->estado === EstadoReserva::SinConfirmar => 'af-barra--sin-confirmar',
            default => 'af-barra--confirmada',
        };
    }
}
