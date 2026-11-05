<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Servicios;

use App\Enums\EstadoReserva;
use App\Enums\EstadoTarea;
use App\Enums\LugarOmision;
use App\Enums\MetodoPago;
use App\Enums\TipoMovimiento;
use App\Enums\TipoReserva;
use App\Enums\TipoTarea;
use App\Limpieza\Omisiones;
use App\Models\Propiedad;
use App\Support\Dinero;
use App\Support\Formato;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Los informes de M9. Cada uno da columnas y filas ya con formato: la pantalla y el CSV usan lo mismo.
 * Las cantidades de dinero van por moneda: propiedades con monedas distintas no se suman.
 */
final class Informes
{
    public const INFORMES = ['unidades', 'canales', 'anio_anterior', 'futuro', 'cobros', 'estancia', 'dia_semana', 'cancelaciones', 'limpieza', 'huespedes', 'anticipos', 'gastos'];

    /**
     * @param  list<int>  $propiedades
     * @return array{columnas: list<string>, filas: list<list<string>>}
     */
    public static function generar(string $informe, array $propiedades, CarbonImmutable $desde, CarbonImmutable $hasta): array
    {
        return match ($informe) {
            'unidades' => self::unidades($propiedades, $desde, $hasta),
            'canales' => self::canales($propiedades, $desde, $hasta),
            'anio_anterior' => self::anioAnterior($propiedades, $desde, $hasta),
            'futuro' => self::futuro($propiedades),
            'cobros' => self::cobros($propiedades, $desde, $hasta),
            'estancia' => self::estancia($propiedades, $desde, $hasta),
            'dia_semana' => self::diaSemana($propiedades, $desde, $hasta),
            'cancelaciones' => self::cancelaciones($propiedades, $desde, $hasta),
            'limpieza' => self::limpieza($propiedades, $desde, $hasta),
            'huespedes' => self::huespedes($propiedades, $desde, $hasta),
            'anticipos' => self::anticipos($propiedades),
            'gastos' => self::gastos($propiedades, $desde, $hasta),
            default => ['columnas' => [], 'filas' => []],
        };
    }

    /**
     * @param  list<int>  $propiedades
     * @return array{columnas: list<string>, filas: list<list<string>>}
     */
    private static function unidades(array $propiedades, CarbonImmutable $desde, CarbonImmutable $hasta): array
    {
        $dias = (int) $desde->diffInDays($hasta) + 1;
        // «101» puede existir en dos propiedades: con varias elegidas, cada fila dice de cuál es.
        $varias = count($propiedades) > 1;
        $filas = self::noches($propiedades, $desde, $hasta)
            ->join('unidades as u', 'u.id', '=', 'n.unidad_id')
            ->join('propiedades as p', 'p.id', '=', 'u.propiedad_id')
            ->groupBy('u.id', 'u.nombre', 'r.moneda', 'p.nombre')
            ->orderBy('p.nombre')->orderBy('u.nombre')
            ->get(['u.nombre', 'p.nombre as propiedad', 'r.moneda', DB::raw('COUNT(*) as noches'), DB::raw('SUM(n.importe) as ingresos')])
            ->map(fn (object $f): array => [
                ...($varias ? [(string) $f->propiedad] : []),
                (string) $f->nombre,
                (string) $f->noches,
                Formato::porcentaje($f->noches / max(1, $dias)),
                Dinero::formatear((int) $f->ingresos, (string) $f->moneda),
                Dinero::formatear((int) round($f->ingresos / max(1, $f->noches)), (string) $f->moneda),
            ])->all();

        return ['columnas' => self::titulos(...[...($varias ? ['propiedad'] : []), 'unidad', 'noches', 'ocupacion', 'ingresos', 'adr']), 'filas' => $filas];
    }

    /**
     * Ingresos netos de la comisión de cada canal.
     *
     * @param  list<int>  $propiedades
     * @return array{columnas: list<string>, filas: list<list<string>>}
     */
    private static function canales(array $propiedades, CarbonImmutable $desde, CarbonImmutable $hasta): array
    {
        $filas = self::reservas($propiedades)->where('estado', '!=', EstadoReserva::Cancelada->value)
            ->whereBetween('llegada', [$desde->format('Y-m-d'), $hasta->format('Y-m-d')])
            ->groupBy('origen_texto', 'moneda')->orderByRaw('SUM(total) DESC')
            ->get([DB::raw("COALESCE(NULLIF(origen_texto, ''), '—') as canal"), 'moneda', DB::raw('COUNT(*) as reservas'), DB::raw('SUM(total) as total'), DB::raw('SUM(comision) as comision')])
            ->map(fn (object $f): array => [
                (string) $f->canal,
                (string) $f->reservas,
                Dinero::formatear((int) $f->total, (string) $f->moneda),
                Dinero::formatear((int) $f->comision, (string) $f->moneda),
                Dinero::formatear((int) $f->total - (int) $f->comision, (string) $f->moneda),
            ])->all();

        return ['columnas' => self::titulos('canal', 'reservas', 'ingresos', 'comision', 'neto'), 'filas' => $filas];
    }

    /**
     * @param  list<int>  $propiedades
     * @return array{columnas: list<string>, filas: list<list<string>>}
     */
    private static function anioAnterior(array $propiedades, CarbonImmutable $desde, CarbonImmutable $hasta): array
    {
        $filas = [];
        foreach (Propiedad::query()->whereIn('id', $propiedades)->orderBy('nombre')->get() as $propiedad) {
            $datos = fn (CarbonImmutable $a, CarbonImmutable $b): object => self::noches([$propiedad->id], $a, $b)
                ->first([DB::raw('COUNT(*) as noches'), DB::raw('SUM(n.importe) as ingresos')]) ?? (object) ['noches' => 0, 'ingresos' => 0];
            $actual = $datos($desde, $hasta);
            $anterior = $datos($desde->subYear(), $hasta->subYear());
            $variacion = (int) $anterior->ingresos > 0 ? Formato::porcentaje(((int) $actual->ingresos - (int) $anterior->ingresos) / (int) $anterior->ingresos) : '—';
            $filas[] = [
                $propiedad->nombre,
                (string) (int) $actual->noches,
                (string) (int) $anterior->noches,
                Dinero::formatear((int) $actual->ingresos, $propiedad->moneda),
                Dinero::formatear((int) $anterior->ingresos, $propiedad->moneda),
                $variacion,
            ];
        }

        return ['columnas' => self::titulos('propiedad', 'noches', 'noches_anterior', 'ingresos', 'ingresos_anterior', 'variacion'), 'filas' => $filas];
    }

    /**
     * Lo ya vendido en los próximos meses.
     *
     * @param  list<int>  $propiedades
     * @return array{columnas: list<string>, filas: list<list<string>>}
     */
    private static function futuro(array $propiedades): array
    {
        $hoy = CarbonImmutable::now();
        $filas = self::noches($propiedades, $hoy->startOfMonth(), $hoy->addMonths(11)->endOfMonth())
            ->groupBy(DB::raw("DATE_FORMAT(n.fecha, '%Y-%m')"), 'r.moneda')->orderBy(DB::raw("DATE_FORMAT(n.fecha, '%Y-%m')"))
            ->get([DB::raw("DATE_FORMAT(n.fecha, '%Y-%m') as mes"), 'r.moneda', DB::raw('COUNT(*) as noches'), DB::raw('SUM(n.importe) as ingresos')])
            ->map(fn (object $f): array => [(string) $f->mes, (string) $f->noches, Dinero::formatear((int) $f->ingresos, (string) $f->moneda)])->all();

        return ['columnas' => self::titulos('mes', 'noches', 'ingresos'), 'filas' => $filas];
    }

    /**
     * @param  list<int>  $propiedades
     * @return array{columnas: list<string>, filas: list<list<string>>}
     */
    private static function cobros(array $propiedades, CarbonImmutable $desde, CarbonImmutable $hasta): array
    {
        $filas = DB::table('pagos')->whereIn('propiedad_id', $propiedades)->whereBetween('fecha', [$desde->format('Y-m-d'), $hasta->format('Y-m-d')])
            ->groupBy('metodo', 'moneda')->orderBy('metodo')
            ->get(['metodo', 'moneda', DB::raw('COUNT(*) as cuantos'), DB::raw('SUM(importe) as total')])
            ->map(fn (object $f): array => [
                MetodoPago::tryFrom((string) $f->metodo)?->etiqueta() ?? (string) $f->metodo,
                (string) $f->cuantos,
                Dinero::formatear((int) $f->total, (string) $f->moneda),
            ])->all();

        return ['columnas' => self::titulos('metodo', 'pagos', 'importe'), 'filas' => $filas];
    }

    /**
     * Cuánto se quedan y con cuánta antelación reservan.
     *
     * @param  list<int>  $propiedades
     * @return array{columnas: list<string>, filas: list<list<string>>}
     */
    private static function estancia(array $propiedades, CarbonImmutable $desde, CarbonImmutable $hasta): array
    {
        $filas = [];
        foreach (Propiedad::query()->whereIn('id', $propiedades)->orderBy('nombre')->get() as $propiedad) {
            $datos = self::reservas([$propiedad->id])->where('estado', '!=', EstadoReserva::Cancelada->value)
                ->whereBetween('llegada', [$desde->format('Y-m-d'), $hasta->format('Y-m-d')])
                ->first([DB::raw('COUNT(*) as reservas'), DB::raw('AVG(noches) as estancia'), DB::raw('AVG(personas) as personas'), DB::raw('AVG(DATEDIFF(llegada, COALESCE(creada_en_origen, creado_en))) as anticipacion')]);
            $filas[] = [
                $propiedad->nombre,
                (string) (int) ($datos->reservas ?? 0),
                number_format((float) ($datos->estancia ?? 0), 1),
                number_format((float) ($datos->personas ?? 0), 1),
                number_format((float) ($datos->anticipacion ?? 0), 0),
            ];
        }

        return ['columnas' => self::titulos('propiedad', 'reservas', 'estancia', 'personas', 'anticipacion'), 'filas' => $filas];
    }

    /**
     * @param  list<int>  $propiedades
     * @return array{columnas: list<string>, filas: list<list<string>>}
     */
    private static function diaSemana(array $propiedades, CarbonImmutable $desde, CarbonImmutable $hasta): array
    {
        $datos = self::noches($propiedades, $desde, $hasta)
            ->groupBy(DB::raw('WEEKDAY(n.fecha)'), 'r.moneda')->orderBy(DB::raw('WEEKDAY(n.fecha)'))
            ->get([DB::raw('WEEKDAY(n.fecha) as dia'), 'r.moneda', DB::raw('COUNT(*) as noches'), DB::raw('SUM(n.importe) as ingresos')]);
        $filas = $datos->map(fn (object $f): array => [
            (string) CarbonImmutable::create(2024, 1, 1 + (int) $f->dia)?->locale(app()->getLocale())->isoFormat('dddd'),
            (string) $f->noches,
            Dinero::formatear((int) $f->ingresos, (string) $f->moneda),
        ])->all();

        return ['columnas' => self::titulos('dia', 'noches', 'ingresos'), 'filas' => $filas];
    }

    /**
     * @param  list<int>  $propiedades
     * @return array{columnas: list<string>, filas: list<list<string>>}
     */
    private static function cancelaciones(array $propiedades, CarbonImmutable $desde, CarbonImmutable $hasta): array
    {
        $filas = [];
        foreach (Propiedad::query()->whereIn('id', $propiedades)->orderBy('nombre')->get() as $propiedad) {
            $rango = [$desde->format('Y-m-d'), $hasta->format('Y-m-d')];
            $datos = self::reservas([$propiedad->id])->whereBetween('llegada', $rango)
                ->first([DB::raw('COUNT(*) as reservas'), DB::raw('SUM(CASE WHEN estado = \''.EstadoReserva::Cancelada->value.'\' THEN 1 ELSE 0 END) as canceladas'), DB::raw('SUM(CASE WHEN estado = \''.EstadoReserva::Cancelada->value.'\' THEN total ELSE 0 END) as valor')]);
            $noShow = DB::table('marcas_no_show as m')->join('reservas as r', 'r.id', '=', 'm.reserva_id')->where('r.propiedad_id', $propiedad->id)->whereBetween('r.llegada', $rango)->count();
            $reservas = (int) ($datos->reservas ?? 0);
            $canceladas = (int) ($datos->canceladas ?? 0);
            $filas[] = [
                $propiedad->nombre,
                (string) $reservas,
                (string) $canceladas,
                Formato::porcentaje($reservas > 0 ? $canceladas / $reservas : 0),
                Dinero::formatear((int) ($datos->valor ?? 0), $propiedad->moneda),
                (string) $noShow,
            ];
        }

        return ['columnas' => self::titulos('propiedad', 'reservas', 'canceladas', 'tasa', 'valor_perdido', 'no_show'), 'filas' => $filas];
    }

    /**
     * @param  list<int>  $propiedades
     * @return array{columnas: list<string>, filas: list<list<string>>}
     */
    private static function limpieza(array $propiedades, CarbonImmutable $desde, CarbonImmutable $hasta): array
    {
        $filas = DB::table('tareas')->whereIn('propiedad_id', $propiedades)->whereBetween('fecha', [$desde->format('Y-m-d'), $hasta->format('Y-m-d')])
            ->where('estado', '!=', EstadoTarea::Cancelada->value)
            ->groupBy('tipo')->orderBy('tipo')
            ->get(['tipo', DB::raw('COUNT(*) as total'), DB::raw("SUM(CASE WHEN estado = '".EstadoTarea::Hecha->value."' THEN 1 ELSE 0 END) as hechas")])
            ->map(fn (object $f): array => [
                TipoTarea::tryFrom((string) $f->tipo)?->etiqueta() ?? (string) $f->tipo,
                (string) $f->total,
                (string) $f->hechas,
                Formato::porcentaje($f->total > 0 ? $f->hechas / $f->total : 0),
            ])->all();

        return ['columnas' => self::titulos('tipo', 'total', 'hechas', 'cumplimiento'), 'filas' => $filas];
    }

    /**
     * De dónde vienen y cuántos repiten.
     *
     * @param  list<int>  $propiedades
     * @return array{columnas: list<string>, filas: list<list<string>>}
     */
    private static function huespedes(array $propiedades, CarbonImmutable $desde, CarbonImmutable $hasta): array
    {
        $porHuesped = self::reservas($propiedades)->where('estado', '!=', EstadoReserva::Cancelada->value)->whereNotNull('titular_id')
            ->whereBetween('llegada', [$desde->format('Y-m-d'), $hasta->format('Y-m-d')])->distinct()->pluck('titular_id');
        $filas = DB::table('huespedes as h')->whereIn('h.id', $porHuesped)
            ->groupBy(DB::raw("COALESCE(NULLIF(h.nacionalidad_texto, ''), NULLIF(h.domicilio_pais_texto, ''), '—')"))->orderByRaw('COUNT(*) DESC')
            ->get([DB::raw("COALESCE(NULLIF(h.nacionalidad_texto, ''), NULLIF(h.domicilio_pais_texto, ''), '—') as pais"), DB::raw('COUNT(*) as huespedes'), DB::raw('SUM(CASE WHEN (SELECT COUNT(*) FROM reservas r2 WHERE r2.titular_id = h.id AND r2.estado <> \''.EstadoReserva::Cancelada->value.'\') > 1 THEN 1 ELSE 0 END) as repiten')])
            ->map(fn (object $f): array => [(string) $f->pais, (string) $f->huespedes, (string) $f->repiten])->all();

        return ['columnas' => self::titulos('pais', 'huespedes', 'repiten'), 'filas' => $filas];
    }

    /**
     * Reservas por llegar que no han pagado su anticipo.
     *
     * @param  list<int>  $propiedades
     * @return array{columnas: list<string>, filas: list<list<string>>}
     */
    private static function anticipos(array $propiedades): array
    {
        $hoy = CarbonImmutable::now()->format('Y-m-d');
        $varias = count($propiedades) > 1;
        $nombres = Propiedad::query()->whereIn('id', $propiedades)->pluck('nombre', 'id');
        $filas = self::reservas($propiedades)->where('estado', '!=', EstadoReserva::Cancelada->value)->where('llegada', '>=', $hoy)
            ->whereColumn('pagado', '<', 'deposito')->orderBy('llegada')->limit(200)
            ->get(['propiedad_id', 'numero', 'llegada', 'moneda', 'total', 'deposito', 'pagado'])
            ->map(fn (object $f): array => [
                ...($varias ? [(string) $nombres->get($f->propiedad_id)] : []),
                (string) $f->numero,
                (string) $f->llegada,
                Dinero::formatear((int) $f->total, (string) $f->moneda),
                Dinero::formatear((int) $f->deposito, (string) $f->moneda),
                Dinero::formatear((int) $f->pagado, (string) $f->moneda),
                Dinero::formatear((int) $f->deposito - (int) $f->pagado, (string) $f->moneda),
            ])->all();

        return ['columnas' => self::titulos(...[...($varias ? ['propiedad'] : []), 'reserva', 'llegada', 'total', 'anticipo', 'pagado', 'falta']), 'filas' => $filas];
    }

    /**
     * @param  list<int>  $propiedades
     * @return array{columnas: list<string>, filas: list<list<string>>}
     */
    private static function gastos(array $propiedades, CarbonImmutable $desde, CarbonImmutable $hasta): array
    {
        $filas = DB::table('movimientos')->whereIn('propiedad_id', $propiedades)->whereBetween('fecha', [$desde->format('Y-m-d'), $hasta->format('Y-m-d').' 23:59:59'])
            ->groupBy('tipo', 'concepto', 'moneda')->orderBy('tipo')->orderByRaw('SUM(importe) DESC')
            ->get(['tipo', 'concepto', 'moneda', DB::raw('COUNT(*) as cuantos'), DB::raw('SUM(importe) as total')])
            ->map(fn (object $f): array => [
                TipoMovimiento::tryFrom((string) $f->tipo)?->etiqueta() ?? (string) $f->tipo,
                (string) $f->concepto,
                (string) $f->cuantos,
                Dinero::formatear((int) $f->total, (string) $f->moneda),
            ])->all();

        return ['columnas' => self::titulos('tipo', 'concepto', 'movimientos', 'importe'), 'filas' => $filas];
    }

    /**
     * Noches vendidas: sin bloqueos, porque no generan ingresos ni cuentan como venta.
     *
     * @param  list<int>  $propiedades
     */
    private static function noches(array $propiedades, CarbonImmutable $desde, CarbonImmutable $hasta): Builder
    {
        $consulta = DB::table('reservas_noches as n')->join('reservas as r', 'r.id', '=', 'n.reserva_id')
            ->whereIn('n.propiedad_id', $propiedades)->where('r.tipo', TipoReserva::Reserva->value)
            ->whereBetween('n.fecha', [$desde->format('Y-m-d'), $hasta->format('Y-m-d')]);

        return Omisiones::excluir($consulta, 'r.titular_id', $propiedades, LugarOmision::Informes);
    }

    /**
     * @param  list<int>  $propiedades
     */
    private static function reservas(array $propiedades): Builder
    {
        $consulta = DB::table('reservas')->whereIn('propiedad_id', $propiedades)->where('tipo', TipoReserva::Reserva->value)->whereNull('eliminada_en_principal_en');

        return Omisiones::excluir($consulta, 'titular_id', $propiedades, LugarOmision::Informes);
    }

    /**
     * @return list<string>
     */
    private static function titulos(string ...$claves): array
    {
        return array_map(fn (string $clave): string => __('informes.columnas.'.$clave), array_values($claves));
    }
}
