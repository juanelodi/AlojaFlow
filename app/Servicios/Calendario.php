<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Servicios;

use App\Enums\EstadoReserva;
use App\Enums\LugarOmision;
use App\Enums\TipoReserva;
use App\Limpieza\Omisiones;
use App\Models\BloqueoExterno;
use App\Models\ColorTipoCliente;
use App\Models\Reserva;
use App\Models\Unidad;
use App\Models\User;
use App\Reservas\UnidadesVinculadas;
use App\Seguridad\Limites;
use Carbon\CarbonImmutable;

/**
 * Calendario por habitaciones de un mes, en modo consulta (M2), y lo que pasa en cada unidad un día (la vista del teléfono).
 */
final class Calendario
{
    /**
     * @param  list<int>  $propiedades
     * @return array{dias: list<CarbonImmutable>, filas: list<array{unidad: Unidad, barras: list<array{oculta: bool, reserva: Reserva, columna: int, ancho: int, sigue_antes: bool, sigue_despues: bool, clase: string, pago: ?string, color: ?string}>, externos: list<array{columna: int, ancho: int, sigue_antes: bool, sigue_despues: bool, fuente: string}>, vinculadas: list<array{columna: int, ancho: int, sigue_antes: bool, sigue_despues: bool, unidades: string}>, tapados: array<int, true>}>} en «tapados», los días del mes (desde 1) que quedan debajo de una barra
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

        $reservas = Reserva::query()->vigentes()->with(['titular.campos', 'grupo', 'habitacionGrupo', 'marcaEstancia'])
            ->whereIn('propiedad_id', $propiedades)
            ->where('llegada', '<', $fin->format('Y-m-d'))
            ->where('salida', '>', $inicio->format('Y-m-d'))
            ->orderBy('llegada')
            ->get()
            ->groupBy('unidad_id')->toBase();

        $externos = BloqueoExterno::query()->with('fuente')->whereIn('propiedad_id', $propiedades)->whereNull('eliminado_en')
            ->where('llegada', '<', $fin->format('Y-m-d'))->where('salida', '>', $inicio->format('Y-m-d'))
            ->orderBy('llegada')->get()->groupBy('unidad_id')->toBase();

        $colores = ColorTipoCliente::query()->whereIn('propiedad_id', $propiedades)->get();
        $omitidos = Omisiones::huespedes($propiedades, LugarOmision::Calendario);
        $vinculos = UnidadesVinculadas::mapa($unidades->map(fn (Unidad $unidad): int => $unidad->id)->all());
        $nombres = $unidades->pluck('nombre', 'id');

        $filas = [];
        foreach ($unidades as $unidad) {
            $barras = [];
            foreach ($reservas->get($unidad->id, collect()) as $reserva) {
                /** @var Reserva $reserva */
                $desde = $reserva->llegada->max($inicio);
                $hasta = $reserva->salida->min($fin);
                // Omitido: ocupa sus fechas, para no venderlas dos veces, pero no dice de quién son (D-144).
                $oculta = $reserva->titular_id !== null && in_array($reserva->titular_id, $omitidos, true);
                $barras[] = [
                    'oculta' => $oculta,
                    'reserva' => $reserva,
                    'columna' => (int) $inicio->diffInDays($desde) + 1,
                    'ancho' => max(1, (int) $desde->diffInDays($hasta)),
                    'sigue_antes' => $reserva->llegada->lt($inicio),
                    'sigue_despues' => $reserva->salida->gt($fin),
                    'clase' => $oculta ? 'af-barra--bloqueo' : self::clase($reserva),
                    'pago' => $oculta ? null : self::pago($reserva),
                    'color' => $oculta ? null : self::colorDe($reserva, $colores),
                    'grupo' => $oculta ? null : $reserva->grupo,
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
            $vinculadas = self::vinculadas($vinculos[$unidad->id] ?? [], $reservas, $externos, $nombres, $inicio, $fin);
            $tapados = [];
            foreach ([...$barras, ...$deportales, ...$vinculadas] as $barra) {
                $tapados += array_fill($barra['columna'], $barra['ancho'], true);
            }
            $filas[] = ['unidad' => $unidad, 'barras' => $barras, 'externos' => $deportales, 'vinculadas' => $vinculadas, 'tapados' => $tapados];
        }

        return ['dias' => $dias, 'filas' => $filas];
    }

    /**
     * Cada unidad en un día: quién sale, quién sigue y quién llega, en ese orden, que es el de la jornada. Una unidad es libre si nadie
     * duerme esa noche, aunque alguien salga de ella ese día: lo mismo que una celda libre del mes.
     *
     * @param  list<int>  $propiedades
     * @return array{filas: list<array{unidad: Unidad, reservas: list<array{reserva: Reserva, momento: string, oculta: bool}>, externos: list<BloqueoExterno>, vinculadas: list<string>, libre: bool}>, libres: int, ocupadas: int, llegan: int, salen: int}
     */
    public static function dia(array $propiedades, CarbonImmutable $dia, ?User $usuario): array
    {
        $fecha = $dia->format('Y-m-d');
        $reservas = Reserva::query()->vigentes()->with(['unidad', 'propiedad', 'titular.contactos', 'habitacionGrupo', 'marcaEstancia'])
            ->whereIn('propiedad_id', $propiedades)
            ->where('llegada', '<=', $fecha)->where('salida', '>=', $fecha)
            ->orderBy('llegada')->get()->groupBy('unidad_id');
        $externos = BloqueoExterno::query()->with('fuente')->whereIn('propiedad_id', $propiedades)->whereNull('eliminado_en')
            ->where('llegada', '<=', $fecha)->where('salida', '>', $fecha)->orderBy('llegada')->get()->groupBy('unidad_id');

        $omitidos = Omisiones::huespedes($propiedades, LugarOmision::Calendario);
        $unidades = self::unidades($propiedades);
        $vinculos = UnidadesVinculadas::mapa($unidades->map(fn (Unidad $unidad): int => $unidad->id)->all());
        // Quién duerme ahí esa noche: quien sale ese día ya no cuenta.
        $duermen = fn (int $unidad): bool => $reservas->get($unidad, collect())->contains(fn (Reserva $r): bool => $r->salida->format('Y-m-d') !== $fecha)
            || $externos->has($unidad);
        $orden = ['sale' => 0, 'sigue' => 1, 'llega' => 2];
        $filas = [];
        $libres = $ocupadas = $llegan = $salen = 0;
        foreach ($unidades as $unidad) {
            $renglones = [];
            foreach ($reservas->get($unidad->id, collect()) as $reserva) {
                /** @var Reserva $reserva */
                $momento = match ($fecha) {
                    $reserva->salida->format('Y-m-d') => 'sale',
                    $reserva->llegada->format('Y-m-d') => 'llega',
                    default => 'sigue',
                };
                $oculta = $reserva->titular_id !== null && in_array($reserva->titular_id, $omitidos, true);
                $renglones[] = ['reserva' => $reserva, 'momento' => $momento, 'oculta' => $oculta];
                // Lo que un usuario no puede ver no se suma: las cifras dirían más que las tarjetas.
                if (! $oculta && $reserva->tipo === TipoReserva::Reserva && Limites::puedeVer($usuario, $reserva)) {
                    $llegan += $momento === 'llega' ? 1 : 0;
                    $salen += $momento === 'sale' ? 1 : 0;
                }
            }
            usort($renglones, fn (array $a, array $b): int => $orden[$a['momento']] <=> $orden[$b['momento']]);
            $deUnidad = $externos->get($unidad->id, collect())->values()->all();
            $cierran = $unidades->whereIn('id', array_filter($vinculos[$unidad->id] ?? [], $duermen))->pluck('nombre')->all();
            $libre = $deUnidad === [] && $cierran === [] && array_filter($renglones, fn (array $r): bool => $r['momento'] !== 'sale') === [];
            $libre ? $libres++ : $ocupadas++;
            $filas[] = ['unidad' => $unidad, 'reservas' => $renglones, 'externos' => $deUnidad, 'vinculadas' => $cierran, 'libre' => $libre];
        }

        return ['filas' => $filas, 'libres' => $libres, 'ocupadas' => $ocupadas, 'llegan' => $llegan, 'salen' => $salen];
    }

    /**
     * Los días que una unidad vinculada ocupa esta (D-155), en tramos seguidos y con los nombres de las que la ocupan. Cualquier
     * cosa cierra: una reserva, un bloqueo o lo de otro calendario.
     *
     * @param  list<int>  $otras
     * @param  \Illuminate\Support\Collection<int|string, \Illuminate\Database\Eloquent\Collection<int, Reserva>>  $reservas
     * @param  \Illuminate\Support\Collection<int|string, \Illuminate\Database\Eloquent\Collection<int, BloqueoExterno>>  $externos
     * @param  \Illuminate\Support\Collection<int, string>  $nombres
     * @return list<array{columna: int, ancho: int, sigue_antes: bool, sigue_despues: bool, unidades: string}>
     */
    private static function vinculadas(array $otras, \Illuminate\Support\Collection $reservas, \Illuminate\Support\Collection $externos, \Illuminate\Support\Collection $nombres, CarbonImmutable $inicio, CarbonImmutable $fin): array
    {
        $dias = [];
        $antes = $despues = false;
        foreach ($otras as $otra) {
            foreach ([...$reservas->get($otra, collect()), ...$externos->get($otra, collect())] as $ocupa) {
                /** @var Reserva|BloqueoExterno $ocupa */
                $antes = $antes || $ocupa->llegada->lt($inicio);
                $despues = $despues || $ocupa->salida->gt($fin);
                for ($dia = $ocupa->llegada->max($inicio); $dia->lt($ocupa->salida->min($fin)); $dia = $dia->addDay()) {
                    $dias[(int) $inicio->diffInDays($dia) + 1][$otra] = true;
                }
            }
        }
        ksort($dias);
        $tramos = [];
        $total = (int) $inicio->diffInDays($fin);
        foreach ($dias as $columna => $quienes) {
            $ultimo = array_key_last($tramos);
            if ($ultimo !== null && $tramos[$ultimo]['columna'] + $tramos[$ultimo]['ancho'] === $columna) {
                $tramos[$ultimo]['ancho']++;
                $tramos[$ultimo]['quienes'] += $quienes;
            } else {
                $tramos[] = ['columna' => $columna, 'ancho' => 1, 'quienes' => $quienes];
            }
        }

        return array_map(fn (array $tramo): array => [
            'columna' => $tramo['columna'],
            'ancho' => $tramo['ancho'],
            'sigue_antes' => $antes && $tramo['columna'] === 1,
            'sigue_despues' => $despues && $tramo['columna'] + $tramo['ancho'] - 1 === $total,
            'unidades' => implode(', ', array_map(fn (int $id): string => (string) $nombres->get($id), array_keys($tramo['quienes']))),
        ], $tramos);
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
            $reserva->salidaEn() !== null => 'af-barra--salio',
            $reserva->entradaEn() !== null => 'af-barra--en-casa',
            $reserva->estado === EstadoReserva::SinConfirmar => 'af-barra--sin-confirmar',
            default => 'af-barra--confirmada',
        };
    }
}
