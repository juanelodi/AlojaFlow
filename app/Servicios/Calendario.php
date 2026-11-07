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
use App\Models\User;
use App\Seguridad\Limites;
use Carbon\CarbonImmutable;

/**
 * Calendario por habitaciones de un mes, en modo consulta (M2), y lo que pasa en cada unidad un día (la vista del teléfono).
 */
final class Calendario
{
    /**
     * @param  list<int>  $propiedades
     * @return array{dias: list<CarbonImmutable>, filas: list<array{unidad: Unidad, barras: list<array{reserva: Reserva, columna: int, ancho: int, sigue_antes: bool, sigue_despues: bool, clase: string, color: ?string}>, externos: list<array{columna: int, ancho: int, sigue_antes: bool, sigue_despues: bool, fuente: string}>, tapados: array<int, true>}>} en «tapados», los días del mes (desde 1) que quedan debajo de una barra
     */
    public static function mes(array $propiedades, CarbonImmutable $inicio): array
    {
        $inicio = $inicio->startOfMonth();
        $fin = $inicio->addMonth(); // exclusivo
        $dias = [];
        for ($dia = $inicio; $dia->lt($fin); $dia = $dia->addDay()) {
            $dias[] = $dia;
        }

        $unidades = self::unidades($propiedades);

        $reservas = Reserva::query()->vigentes()->with(['titular.campos', 'grupo', 'habitacionGrupo'])
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
                    'grupo' => $reserva->grupo,
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
            $tapados = [];
            foreach ([...$barras, ...$deportales] as $barra) {
                $tapados += array_fill($barra['columna'], $barra['ancho'], true);
            }
            $filas[] = ['unidad' => $unidad, 'barras' => $barras, 'externos' => $deportales, 'tapados' => $tapados];
        }

        return ['dias' => $dias, 'filas' => $filas];
    }

    /**
     * Cada unidad en un día: quién sale, quién sigue y quién llega, en ese orden, que es el de la jornada. Una unidad es libre si nadie
     * duerme esa noche, aunque alguien salga de ella ese día: lo mismo que una celda libre del mes.
     *
     * @param  list<int>  $propiedades
     * @return array{filas: list<array{unidad: Unidad, reservas: list<array{reserva: Reserva, momento: string}>, externos: list<BloqueoExterno>, libre: bool}>, libres: int, ocupadas: int, llegan: int, salen: int}
     */
    public static function dia(array $propiedades, CarbonImmutable $dia, ?User $usuario): array
    {
        $fecha = $dia->format('Y-m-d');
        $reservas = Reserva::query()->vigentes()->with(['unidad', 'propiedad', 'titular.contactos', 'habitacionGrupo'])
            ->whereIn('propiedad_id', $propiedades)
            ->where('llegada', '<=', $fecha)->where('salida', '>=', $fecha)
            ->orderBy('llegada')->get()->groupBy('unidad_id');
        $externos = BloqueoExterno::query()->with('fuente')->whereIn('propiedad_id', $propiedades)->whereNull('eliminado_en')
            ->where('llegada', '<=', $fecha)->where('salida', '>', $fecha)->orderBy('llegada')->get()->groupBy('unidad_id');

        $orden = ['sale' => 0, 'sigue' => 1, 'llega' => 2];
        $filas = [];
        $libres = $ocupadas = $llegan = $salen = 0;
        foreach (self::unidades($propiedades) as $unidad) {
            $renglones = [];
            foreach ($reservas->get($unidad->id, collect()) as $reserva) {
                /** @var Reserva $reserva */
                $momento = match ($fecha) {
                    $reserva->salida->format('Y-m-d') => 'sale',
                    $reserva->llegada->format('Y-m-d') => 'llega',
                    default => 'sigue',
                };
                $renglones[] = ['reserva' => $reserva, 'momento' => $momento];
                // Lo que un usuario no puede ver no se suma: las cifras dirían más que las tarjetas.
                if ($reserva->tipo === TipoReserva::Reserva && Limites::puedeVer($usuario, $reserva)) {
                    $llegan += $momento === 'llega' ? 1 : 0;
                    $salen += $momento === 'sale' ? 1 : 0;
                }
            }
            usort($renglones, fn (array $a, array $b): int => $orden[$a['momento']] <=> $orden[$b['momento']]);
            $deUnidad = $externos->get($unidad->id, collect())->values()->all();
            $libre = $deUnidad === [] && array_filter($renglones, fn (array $r): bool => $r['momento'] !== 'sale') === [];
            $libre ? $libres++ : $ocupadas++;
            $filas[] = ['unidad' => $unidad, 'reservas' => $renglones, 'externos' => $deUnidad, 'libre' => $libre];
        }

        return ['filas' => $filas, 'libres' => $libres, 'ocupadas' => $ocupadas, 'llegan' => $llegan, 'salen' => $salen];
    }

    /**
     * Lo que se escribe de una reserva en el calendario. Lo ajeno solo dice que está ocupado.
     */
    public static function textoDe(Reserva $reserva, ?User $usuario): string
    {
        return match (true) {
            ! Limites::puedeVer($usuario, $reserva) => __('permisos.limites.ocupada'),
            $reserva->tipo === TipoReserva::Bloqueo => $reserva->origen_texto ?? __('ui.calendario.bloqueo'),
            default => $reserva->habitacionGrupo?->responsable ?: ($reserva->titular?->nombreCompleto() ?? $reserva->numero),
        };
    }

    /**
     * @param  list<int>  $propiedades
     * @return \Illuminate\Database\Eloquent\Collection<int, Unidad>
     */
    private static function unidades(array $propiedades): \Illuminate\Database\Eloquent\Collection
    {
        return Unidad::query()->with('propiedad')
            ->whereIn('unidades.propiedad_id', $propiedades)->where('unidades.activa', true)
            ->join('propiedades', 'propiedades.id', '=', 'unidades.propiedad_id')
            ->orderBy('propiedades.nombre')->orderBy('unidades.orden')->orderBy('unidades.nombre')
            ->select('unidades.*')->get();
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
