<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Servicios;

use App\Enums\EstadoReserva;
use App\Enums\EstadoTarea;
use App\Enums\TipoMovimiento;
use App\Enums\TipoTarea;
use App\Models\Empresa;
use App\Models\Grupo;
use App\Models\Huesped;
use App\Models\Propiedad;
use App\Seguridad\Acceso;
use App\Support\Dinero;
use App\Support\Formato;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Los informes para decidir (D-079): cómo va el año, qué se está vendiendo, qué falta por cobrar. Las fechas son textos sin zona, como
 * en toda la pantalla de informes.
 *
 * @phpstan-import-type DatosGrafica from Grafica
 *
 * @phpstan-type Reporte array{columnas: list<string>, filas: list<list<string>>, grafica?: DatosGrafica|null}
 */
final class InformesGestion
{
    /**
     * Mes por mes, con las fórmulas de siempre: ocupación = noches / disponibles; ADR = ingresos / noches con importe; RevPAR = ingresos / disponibles.
     *
     * @param  list<int>  $propiedades
     * @return Reporte
     */
    public static function resumenAnio(array $propiedades, CarbonImmutable $desde, CarbonImmutable $hasta): array
    {
        $unidades = self::unidades($propiedades);
        $datos = Informes::noches($propiedades, $desde, $hasta)
            ->groupBy(DB::raw("DATE_FORMAT(n.fecha, '%Y-%m')"), 'r.moneda')->orderBy(DB::raw("DATE_FORMAT(n.fecha, '%Y-%m')"))
            ->get([DB::raw("DATE_FORMAT(n.fecha, '%Y-%m') as mes"), 'r.moneda', DB::raw('COUNT(*) as noches'), DB::raw('SUM(n.importe) as ingresos'), DB::raw('SUM(CASE WHEN n.importe > 0 THEN 1 ELSE 0 END) as con_importe')]);

        $filas = $datos->map(function (object $f) use ($unidades, $desde, $hasta): array {
            $inicio = CarbonImmutable::createFromFormat('!Y-m', (string) $f->mes) ?: $desde;
            $dias = (int) $inicio->max($desde)->diffInDays($inicio->endOfMonth()->min($hasta)->startOfDay()) + 1;
            $disponibles = max(1, $unidades * $dias);

            return [
                Informes::mes((string) $f->mes),
                (string) $f->noches,
                Formato::porcentaje($f->noches / $disponibles),
                Dinero::formatear((int) $f->ingresos, (string) $f->moneda),
                Dinero::formatear((int) round($f->ingresos / max(1, (int) $f->con_importe)), (string) $f->moneda),
                Dinero::formatear((int) round($f->ingresos / $disponibles), (string) $f->moneda),
            ];
        })->all();

        return [
            'columnas' => Informes::titulos('mes', 'noches', 'ocupacion', 'ingresos', 'adr', 'revpar'),
            'filas' => $filas,
            'grafica' => Grafica::de(
                [__('informes.columnas.ingresos')],
                $datos,
                fn (object $f): string => Informes::mes((string) $f->mes),
                fn (object $f): array => [[(int) $f->ingresos, Dinero::formatear((int) $f->ingresos, (string) $f->moneda)]],
                fn (object $f): string => (string) $f->moneda,
            ),
        ];
    }

    /**
     * Qué se vendió en este periodo y para qué meses: las noches reservadas entre esas fechas, sea cuando sea la estancia.
     *
     * @param  list<int>  $propiedades
     * @return Reporte
     */
    public static function pickup(array $propiedades, CarbonImmutable $desde, CarbonImmutable $hasta): array
    {
        $datos = Informes::noches($propiedades, $desde, $desde->addYears(3))
            ->whereRaw('DATE(COALESCE(r.creada_en_origen, r.creado_en)) BETWEEN ? AND ?', [$desde->format('Y-m-d'), $hasta->format('Y-m-d')])
            ->groupBy(DB::raw("DATE_FORMAT(n.fecha, '%Y-%m')"), 'r.moneda')->orderBy(DB::raw("DATE_FORMAT(n.fecha, '%Y-%m')"))
            ->get([DB::raw("DATE_FORMAT(n.fecha, '%Y-%m') as mes"), 'r.moneda', DB::raw('COUNT(*) as noches'), DB::raw('SUM(n.importe) as ingresos')]);

        return [
            'columnas' => Informes::titulos('mes', 'noches', 'ingresos'),
            'filas' => $datos->map(fn (object $f): array => [Informes::mes((string) $f->mes), (string) $f->noches, Dinero::formatear((int) $f->ingresos, (string) $f->moneda)])->all(),
            'grafica' => Grafica::de(
                [__('informes.columnas.noches')],
                $datos,
                fn (object $f): string => Informes::mes((string) $f->mes),
                fn (object $f): array => [[(int) $f->noches, (string) $f->noches]],
            ),
        ];
    }

    /**
     * Lo vendido hoy, más lo que el año pasado todavía entró desde esta fecha. Es una proyección sencilla y a la vista, no una predicción.
     *
     * @param  list<int>  $propiedades
     * @return Reporte
     */
    public static function pronostico(array $propiedades): array
    {
        $hoy = CarbonImmutable::now()->startOfDay();
        $unidades = self::unidades($propiedades);
        $meses = [];
        for ($i = 0; $i < 6; $i++) {
            $inicio = $hoy->startOfMonth()->addMonths($i);
            $fin = $inicio->endOfMonth()->startOfDay();
            $vendidas = Informes::noches($propiedades, $inicio, $fin)->count();
            $total = Informes::noches($propiedades, $inicio->subYear(), $fin->subYear())->count();
            $aEstaFecha = Informes::noches($propiedades, $inicio->subYear(), $fin->subYear())
                ->whereRaw('DATE(COALESCE(r.creada_en_origen, r.creado_en)) <= ?', [$hoy->subYear()->format('Y-m-d')])->count();
            $disponibles = max(1, $unidades * $inicio->daysInMonth);
            $meses[] = [
                'mes' => $inicio->format('Y-m'),
                'vendidas' => $vendidas,
                'total' => $total,
                'a_esta_fecha' => $aEstaFecha,
                'pronostico' => min($disponibles, $vendidas + max(0, $total - $aEstaFecha)),
                'disponibles' => $disponibles,
            ];
        }

        return [
            'columnas' => Informes::titulos('mes', 'vendidas', 'total_anterior', 'a_esta_fecha', 'pronostico', 'ocupacion'),
            'filas' => array_map(fn (array $m): array => [
                Informes::mes($m['mes']),
                (string) $m['vendidas'],
                (string) $m['total'],
                (string) $m['a_esta_fecha'],
                (string) $m['pronostico'],
                Formato::porcentaje($m['pronostico'] / $m['disponibles']),
            ], $meses),
            'grafica' => Grafica::de(
                [__('informes.columnas.vendidas'), __('informes.columnas.pronostico')],
                $meses,
                fn (array $m): string => Informes::mes($m['mes']),
                fn (array $m): array => [[$m['vendidas'], (string) $m['vendidas']], [$m['pronostico'], (string) $m['pronostico']]],
            ),
        ];
    }

    /**
     * Quién debe y desde cuándo. Vence al salir o, si la paga una empresa, cuando acaba su crédito. El nombre del huésped solo lo ve quien
     * puede ver sus datos.
     *
     * @param  list<int>  $propiedades
     * @return Reporte
     */
    public static function porCobrar(array $propiedades): array
    {
        $hoy = CarbonImmutable::now()->startOfDay();
        $varias = count($propiedades) > 1;
        $nombres = Propiedad::query()->whereIn('id', $propiedades)->pluck('nombre', 'id');
        $reservas = Informes::reservas($propiedades)->where('estado', '!=', EstadoReserva::Cancelada->value)->where('pendiente', '>', 0)
            ->orderBy('salida')->limit(500)->get(['propiedad_id', 'numero', 'titular_id', 'empresa_id', 'salida', 'moneda', 'pendiente']);
        $empresas = Empresa::query()->whereIn('id', $reservas->pluck('empresa_id')->filter()->unique())->get()->keyBy('id');
        $conDatos = Acceso::propiedades(Acceso::usuarioActual(), Acceso::DATOS);
        $titulares = Huesped::query()->whereIn('id', $reservas->filter(fn (object $r): bool => in_array((int) $r->propiedad_id, $conDatos, true))->pluck('titular_id')->filter()->unique())->get()->keyBy('id');

        $tramos = [];
        $filas = [];
        foreach ($reservas as $r) {
            $empresa = $empresas->get($r->empresa_id);
            $vence = CarbonImmutable::parse((string) $r->salida)->addDays((int) $empresa?->dias_credito);
            $vencida = $vence->lt($hoy) ? (int) $vence->diffInDays($hoy) : 0;
            $tramo = match (true) {
                $vencida === 0 => 'sin_vencer',
                $vencida <= 30 => 'hasta_30',
                $vencida <= 60 => 'hasta_60',
                default => 'mas_de_60',
            };
            $tramos[$tramo] = ($tramos[$tramo] ?? 0) + (int) $r->pendiente;
            $filas[] = [
                ...($varias ? [(string) $nombres->get($r->propiedad_id)] : []),
                (string) $r->numero,
                $empresa->nombre ?? (string) $titulares->get($r->titular_id)?->nombreCompleto(),
                Formato::fecha($vence),
                (string) $vencida,
                Dinero::formatear((int) $r->pendiente, (string) $r->moneda),
            ];
        }
        $moneda = (string) ($reservas->first()->moneda ?? '');
        $orden = array_values(array_intersect(['sin_vencer', 'hasta_30', 'hasta_60', 'mas_de_60'], array_keys($tramos)));

        return [
            'columnas' => Informes::titulos(...[...($varias ? ['propiedad'] : []), 'reserva', 'debe', 'vence', 'dias_vencida', 'pendiente']),
            'filas' => $filas,
            'grafica' => $reservas->pluck('moneda')->unique()->count() > 1 ? null : Grafica::de(
                [__('informes.columnas.pendiente')],
                $orden,
                fn (string $tramo): string => __('informes.tramos.'.$tramo),
                fn (string $tramo): array => [[$tramos[$tramo], Dinero::formatear($tramos[$tramo], $moneda)]],
            ),
        ];
    }

    /**
     * Mes por mes, lo que entró y lo que salió de verdad: los cobros cuentan aunque el cliente esté omitido, porque ese dinero es real.
     *
     * @param  list<int>  $propiedades
     * @return Reporte
     */
    public static function ingresosGastos(array $propiedades, CarbonImmutable $desde, CarbonImmutable $hasta): array
    {
        $rango = [$desde->format('Y-m-d'), $hasta->format('Y-m-d')];
        $meses = [];
        foreach (DB::table('pagos')->whereIn('propiedad_id', $propiedades)->whereBetween('fecha', $rango)->groupBy(DB::raw("DATE_FORMAT(fecha, '%Y-%m')"), 'moneda')
            ->get([DB::raw("DATE_FORMAT(fecha, '%Y-%m') as mes"), 'moneda', DB::raw('SUM(importe) as total')]) as $f) {
            $clave = $f->mes.'|'.$f->moneda;
            $meses[$clave] ??= self::mesVacio((string) $f->mes, (string) $f->moneda);
            $meses[$clave]['cobros'] += (int) $f->total;
        }
        foreach (DB::table('movimientos')->whereIn('propiedad_id', $propiedades)->whereBetween('fecha', $rango)->groupBy(DB::raw("DATE_FORMAT(fecha, '%Y-%m')"), 'moneda', 'tipo')
            ->get([DB::raw("DATE_FORMAT(fecha, '%Y-%m') as mes"), 'moneda', 'tipo', DB::raw('SUM(importe) as total')]) as $f) {
            $clave = $f->mes.'|'.$f->moneda;
            $meses[$clave] ??= self::mesVacio((string) $f->mes, (string) $f->moneda);
            $meses[$clave][$f->tipo === TipoMovimiento::Gasto->value ? 'gastos' : 'ingresos'] += (int) $f->total;
        }
        ksort($meses);
        $meses = array_values($meses);

        return [
            'columnas' => Informes::titulos('mes', 'cobros', 'otros_ingresos', 'gastos', 'neto'),
            'filas' => array_map(fn (array $m): array => [
                Informes::mes($m['mes']),
                Dinero::formatear($m['cobros'], $m['moneda']),
                Dinero::formatear($m['ingresos'], $m['moneda']),
                Dinero::formatear($m['gastos'], $m['moneda']),
                Dinero::formatear($m['cobros'] + $m['ingresos'] - $m['gastos'], $m['moneda']),
            ], $meses),
            'grafica' => Grafica::de(
                [__('informes.columnas.entradas'), __('informes.columnas.gastos')],
                $meses,
                fn (array $m): string => Informes::mes($m['mes']),
                fn (array $m): array => [
                    [$m['cobros'] + $m['ingresos'], Dinero::formatear($m['cobros'] + $m['ingresos'], $m['moneda'])],
                    [$m['gastos'], Dinero::formatear($m['gastos'], $m['moneda'])],
                ],
                fn (array $m): string => $m['moneda'],
            ),
        ];
    }

    /**
     * Lo cobrado de cada impuesto, por fecha de llegada. De una principal solo se sabe el porcentaje total de la reserva, también después de pasar a independiente. Un cliente omitido
     * sí cuenta: el impuesto se declara igual.
     *
     * @param  list<int>  $propiedades
     * @return Reporte
     */
    public static function impuestos(array $propiedades, CarbonImmutable $desde, CarbonImmutable $hasta): array
    {
        $rango = [$desde->format('Y-m-d'), $hasta->format('Y-m-d')];
        $propios = DB::table('reservas_impuestos as i')->join('reservas as r', 'r.id', '=', 'i.reserva_id')
            ->whereIn('r.propiedad_id', $propiedades)->where('r.estado', '!=', EstadoReserva::Cancelada->value)->whereBetween('r.llegada', $rango)
            ->groupBy('i.nombre', 'r.moneda')->orderBy('i.nombre')
            ->get(['i.nombre', 'r.moneda', DB::raw('COUNT(DISTINCT r.id) as reservas'), DB::raw('SUM(i.unidades) as unidades'), DB::raw('SUM(i.unidades_exentas) as exentas'), DB::raw('SUM(CASE WHEN i.cobrado_por_canal = 0 THEN i.importe ELSE 0 END) as cobrado'), DB::raw('SUM(CASE WHEN i.cobrado_por_canal = 1 THEN i.importe ELSE 0 END) as canal')]);
        $dePrincipal = DB::table('reservas')->whereIn('propiedad_id', $propiedades)->whereNotIn('id', DB::table('reservas_impuestos')->select('reserva_id'))->whereNull('eliminada_en_principal_en')
            ->where('estado', '!=', EstadoReserva::Cancelada->value)->whereBetween('llegada', $rango)->where('impuestos_porcentaje', '>', 0)
            ->groupBy('impuestos_porcentaje', 'moneda')->orderBy('impuestos_porcentaje')
            ->get(['impuestos_porcentaje', 'moneda', DB::raw('COUNT(*) as reservas'), DB::raw('SUM(total - total / (1 + impuestos_porcentaje / 100)) as cobrado')]);

        $datos = [];
        foreach ($propios as $f) {
            $datos[] = ['nombre' => (string) $f->nombre, 'moneda' => (string) $f->moneda, 'reservas' => (int) $f->reservas, 'unidades' => (int) $f->unidades, 'exentas' => (int) $f->exentas, 'cobrado' => (int) $f->cobrado, 'canal' => (int) $f->canal];
        }
        foreach ($dePrincipal as $f) {
            $datos[] = ['nombre' => __('informes.impuesto_principal', ['porcentaje' => rtrim(rtrim(number_format((float) $f->impuestos_porcentaje, 2, '.', ''), '0'), '.')]), 'moneda' => (string) $f->moneda, 'reservas' => (int) $f->reservas, 'cobrado' => (int) round((float) $f->cobrado), 'canal' => 0];
        }

        return [
            'columnas' => Informes::titulos('impuesto', 'reservas', 'unidades', 'exentas', 'cobrado', 'cobrado_canal'),
            'filas' => array_map(fn (array $d): array => [$d['nombre'], (string) $d['reservas'], (string) ($d['unidades'] ?? 0), (string) ($d['exentas'] ?? 0), Dinero::formatear($d['cobrado'], $d['moneda']), Dinero::formatear($d['canal'], $d['moneda'])], $datos),
            'grafica' => Grafica::de(
                [__('informes.columnas.cobrado')],
                $datos,
                fn (array $d): string => $d['nombre'],
                fn (array $d): array => [[$d['cobrado'], Dinero::formatear($d['cobrado'], $d['moneda'])]],
                fn (array $d): string => $d['moneda'],
            ),
        ];
    }

    /**
     * Lo que hizo cada quien: sirve para repartir el trabajo y para pagarle a quien cobra por limpieza.
     *
     * @param  list<int>  $propiedades
     * @return Reporte
     */
    public static function limpiezaPorPersona(array $propiedades, CarbonImmutable $desde, CarbonImmutable $hasta): array
    {
        $datos = DB::table('tareas as t')->leftJoin('users as u', 'u.id', '=', 't.hecha_por')
            ->whereIn('t.propiedad_id', $propiedades)->where('t.estado', EstadoTarea::Hecha->value)->whereBetween('t.fecha', [$desde->format('Y-m-d'), $hasta->format('Y-m-d')])
            ->groupBy('t.hecha_por', 'u.name', 't.tipo')->orderBy('u.name')->orderBy('t.tipo')
            ->get(['t.hecha_por', 'u.name', 't.tipo', DB::raw('COUNT(*) as hechas')]);
        $porPersona = [];
        foreach ($datos as $f) {
            $persona = (string) ($f->name ?? '—');
            $porPersona[$persona] = ($porPersona[$persona] ?? 0) + (int) $f->hechas;
        }

        return [
            'columnas' => Informes::titulos('persona', 'tipo', 'hechas'),
            'filas' => $datos->map(fn (object $f): array => [(string) ($f->name ?? '—'), TipoTarea::tryFrom((string) $f->tipo)?->etiqueta() ?? (string) $f->tipo, (string) $f->hechas])->all(),
            'grafica' => Grafica::de(
                [__('informes.columnas.hechas')],
                array_keys($porPersona),
                fn (int|string $persona): string => (string) $persona,
                fn (int|string $persona): array => [[$porPersona[$persona], (string) $porPersona[$persona]]],
            ),
        ];
    }

    /**
     * Cada grupo, por fecha de llegada. De una principal solo llega el código del grupo.
     *
     * @param  list<int>  $propiedades
     * @return Reporte
     */
    public static function grupos(array $propiedades, CarbonImmutable $desde, CarbonImmutable $hasta): array
    {
        $datos = Informes::reservas($propiedades)->where('estado', '!=', EstadoReserva::Cancelada->value)
            ->whereBetween('llegada', [$desde->format('Y-m-d'), $hasta->format('Y-m-d')])
            ->where(fn ($q) => $q->whereNotNull('grupo_id')->orWhereNotNull('grupo_codigo'))
            ->groupBy('grupo_id', 'grupo_codigo', 'moneda')->orderByRaw('SUM(total) DESC')
            ->get(['grupo_id', 'grupo_codigo', 'moneda', DB::raw('COUNT(*) as reservas'), DB::raw('SUM(noches) as noches'), DB::raw('SUM(total) as total'), DB::raw('SUM(pagado) as pagado'), DB::raw('SUM(pendiente) as pendiente')]);
        $nombres = Grupo::query()->whereIn('id', $datos->pluck('grupo_id')->filter()->unique())->pluck('nombre', 'id');
        $nombre = fn (object $f): string => (string) ($nombres->get($f->grupo_id) ?? $f->grupo_codigo ?? '—');

        return [
            'columnas' => Informes::titulos('grupo', 'reservas', 'noches', 'total', 'pagado', 'pendiente'),
            'filas' => $datos->map(fn (object $f): array => [
                $nombre($f),
                (string) $f->reservas,
                (string) $f->noches,
                Dinero::formatear((int) $f->total, (string) $f->moneda),
                Dinero::formatear((int) $f->pagado, (string) $f->moneda),
                Dinero::formatear((int) $f->pendiente, (string) $f->moneda),
            ])->all(),
            'grafica' => Grafica::de(
                [__('informes.columnas.total')],
                $datos,
                $nombre,
                fn (object $f): array => [[(int) $f->total, Dinero::formatear((int) $f->total, (string) $f->moneda)]],
                fn (object $f): string => (string) $f->moneda,
            ),
        ];
    }

    /**
     * @return array{mes: string, moneda: string, cobros: int, ingresos: int, gastos: int}
     */
    private static function mesVacio(string $mes, string $moneda): array
    {
        return ['mes' => $mes, 'moneda' => $moneda, 'cobros' => 0, 'ingresos' => 0, 'gastos' => 0];
    }

    /**
     * @param  list<int>  $propiedades
     */
    private static function unidades(array $propiedades): int
    {
        return DB::table('unidades')->whereIn('propiedad_id', $propiedades)->where('activa', true)->count();
    }
}
