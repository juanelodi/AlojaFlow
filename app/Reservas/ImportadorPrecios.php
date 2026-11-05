<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Reservas;

use App\Models\Propiedad;
use App\Models\RegistroAuditoria;
use App\Models\Tarifa;
use App\Models\TemporadaTarifa;
use App\Seguridad\AuditoriaModelos;
use App\Support\Csv;
use App\Support\Dinero;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Importar precios desde un CSV o pegados de Excel (D-083): tarifa, desde, hasta (última noche incluida), precio y, opcional, precio por persona
 * extra. Primero se revisa: solo se importan las filas correctas, y sustituyen los precios de esas fechas. La lógica viene de la 1.2.3, reescrita.
 */
final class ImportadorPrecios
{
    public const BYTES_MAXIMOS = 1048576;

    /**
     * @return list<array{linea: int, tarifa: string, tarifa_id: int|null, desde: string, hasta: string, precio: int|null, persona: int|null, error: string|null}>
     */
    public function revisar(Propiedad $propiedad, string $texto): array
    {
        $texto = Csv::enUtf8($texto);
        if (strlen($texto) > self::BYTES_MAXIMOS) {
            return [['linea' => 0, 'tarifa' => '', 'tarifa_id' => null, 'desde' => '', 'hasta' => '', 'precio' => null, 'persona' => null, 'error' => __('operacion.precios.error_tamano')]];
        }
        $tarifas = Tarifa::query()->where('propiedad_id', $propiedad->id)->get(['id', 'nombre']);
        $lineas = preg_split('/\r\n|\r|\n/', trim($texto)) ?: [];
        $separador = Csv::separador($lineas[0]);
        $filas = [];
        foreach ($lineas as $i => $linea) {
            if (trim($linea) === '') {
                continue;
            }
            $campos = array_map('trim', str_getcsv($linea, $separador, '"', ''));
            if ($i === 0 && self::esEncabezado($campos)) {
                continue;
            }
            $tarifa = $campos[0];
            $encontrada = $tarifas->first(fn (Tarifa $t): bool => mb_strtolower($t->nombre) === mb_strtolower($tarifa) || (string) $t->id === $tarifa);
            $desde = self::fecha($campos[1] ?? '');
            $hasta = self::fecha($campos[2] ?? '');
            $precio = self::importe($campos[3] ?? '', $propiedad->moneda);
            $persona = ($campos[4] ?? '') === '' ? null : self::importe($campos[4], $propiedad->moneda);
            $error = match (true) {
                $encontrada === null => __('operacion.precios.error_tarifa'),
                $desde === null || $hasta === null => __('operacion.precios.error_fecha'),
                $hasta < $desde => __('operacion.precios.error_orden'),
                $precio === null => __('operacion.precios.error_precio'),
                ($campos[4] ?? '') !== '' && $persona === null => __('operacion.precios.error_precio'),
                default => null,
            };
            $filas[] = [
                'linea' => $i + 1,
                'tarifa' => $tarifa,
                'tarifa_id' => $encontrada?->id,
                'desde' => (string) $desde,
                'hasta' => (string) $hasta,
                'precio' => $precio,
                'persona' => $persona,
                'error' => $error,
            ];
        }

        return $filas;
    }

    /**
     * Solo las filas sin error, y dentro de una transacción: o entran todas o ninguna.
     *
     * @param  list<array{linea: int, tarifa: string, tarifa_id: int|null, desde: string, hasta: string, precio: int|null, persona: int|null, error: string|null}>  $filas
     * @return int filas importadas
     */
    public function importar(Propiedad $propiedad, array $filas): int
    {
        $correctas = array_values(array_filter($filas, fn (array $f): bool => $f['error'] === null && $f['tarifa_id'] !== null && $f['precio'] !== null));
        AuditoriaModelos::sinAnotar(fn () => DB::transaction(function () use ($correctas): void {
            foreach ($correctas as $fila) {
                $this->sustituir((int) $fila['tarifa_id'], CarbonImmutable::parse($fila['desde']), CarbonImmutable::parse($fila['hasta']), (int) $fila['precio'], $fila['persona']);
            }
        }));
        RegistroAuditoria::anotar('tarifas.importar', null, ['filas' => count($correctas)], $propiedad->id);

        return count($correctas);
    }

    /**
     * El CSV para empezar: lo que cuesta hoy cada tarifa y cada temporada.
     *
     * @return list<list<string>>
     */
    public function plantilla(Propiedad $propiedad): array
    {
        $filas = [[__('operacion.precios.col_tarifa'), __('operacion.precios.col_desde'), __('operacion.precios.col_hasta'), __('operacion.precios.col_precio'), __('operacion.precios.col_persona')]];
        $hoy = $propiedad->hoy();
        foreach (Tarifa::query()->with('temporadas')->where('propiedad_id', $propiedad->id)->where('activa', true)->orderBy('nombre')->get() as $tarifa) {
            $filas[] = [$tarifa->nombre, $hoy->format('Y-m-d'), $hoy->addYear()->format('Y-m-d'), Dinero::aDecimal($tarifa->precio_noche, $propiedad->moneda), Dinero::aDecimal($tarifa->precio_persona_extra, $propiedad->moneda)];
            foreach ($tarifa->temporadas->sortBy('desde') as $temporada) {
                $filas[] = [$tarifa->nombre, $temporada->desde->format('Y-m-d'), $temporada->hasta->format('Y-m-d'), Dinero::aDecimal($temporada->precio_noche, $propiedad->moneda), $temporada->precio_persona_extra === null ? '' : Dinero::aDecimal($temporada->precio_persona_extra, $propiedad->moneda)];
            }
        }

        return $filas;
    }

    /**
     * Quita esas fechas de las temporadas que las pisan, partiéndolas si hace falta, y pone la nueva. Vacío el precio por persona, se queda el
     * que tenía esa fecha.
     */
    private function sustituir(int $tarifaId, CarbonImmutable $desde, CarbonImmutable $hasta, int $precio, ?int $persona): void
    {
        $pisadas = TemporadaTarifa::query()->where('tarifa_id', $tarifaId)->where('desde', '<=', $hasta->format('Y-m-d'))->where('hasta', '>=', $desde->format('Y-m-d'))->orderBy('desde')->get();
        $personaDeAntes = $persona ?? $pisadas->first()?->precio_persona_extra;

        foreach ($pisadas as $temporada) {
            $antes = $temporada->desde->lt($desde);
            $despues = $temporada->hasta->gt($hasta);
            if ($antes && $despues) {
                $resto = $temporada->replicate();
                $resto->desde = $hasta->addDay();
                $resto->save();
                $temporada->update(['hasta' => $desde->subDay()->format('Y-m-d')]);
            } elseif ($antes) {
                $temporada->update(['hasta' => $desde->subDay()->format('Y-m-d')]);
            } elseif ($despues) {
                $temporada->update(['desde' => $hasta->addDay()->format('Y-m-d')]);
            } else {
                $temporada->delete();
            }
        }
        TemporadaTarifa::query()->create([
            'tarifa_id' => $tarifaId,
            'nombre' => __('operacion.precios.temporada', ['desde' => $desde->format('Y-m-d')]),
            'desde' => $desde->format('Y-m-d'),
            'hasta' => $hasta->format('Y-m-d'),
            'precio_noche' => $precio,
            'precio_persona_extra' => $personaDeAntes,
        ]);
    }

    /**
     * Excel en Windows guarda en otra codificación.
     */
    /**
     * @param  list<string|null>  $campos
     */
    private static function esEncabezado(array $campos): bool
    {
        return self::fecha((string) ($campos[1] ?? '')) === null && self::fecha((string) ($campos[2] ?? '')) === null;
    }

    /**
     * Año-mes-día o día/mes/año. Mes/día/año no: 03/04 sería ambiguo.
     */
    private static function fecha(string $texto): ?string
    {
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $texto, $m) === 1) {
            [$anio, $mes, $dia] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } elseif (preg_match('#^(\d{1,2})[/-](\d{1,2})[/-](\d{4})$#', $texto, $m) === 1) {
            [$dia, $mes, $anio] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } else {
            return null;
        }

        return checkdate($mes, $dia, $anio) ? sprintf('%04d-%02d-%02d', $anio, $mes, $dia) : null;
    }

    /**
     * Acepta coma o punto decimal: el último separador es el de los decimales.
     */
    private static function importe(string $texto, string $moneda): ?int
    {
        $limpio = (string) preg_replace('/[^\d.,]/', '', $texto);
        if (str_contains($texto, '-') || preg_match('/\d/', $limpio) !== 1) {
            return null;
        }
        $ultimo = max((int) strrpos($limpio, ','), (int) strrpos($limpio, '.'));
        // Sin decimales después del último separador de tres cifras, es de miles: 1,200 son mil doscientos.
        $tieneDecimales = (strrpos($limpio, ',') !== false || strrpos($limpio, '.') !== false) && strlen($limpio) - $ultimo - 1 !== 3;
        $entero = $tieneDecimales ? substr($limpio, 0, $ultimo) : $limpio;
        $decimales = $tieneDecimales ? substr($limpio, $ultimo + 1) : '';
        $numero = str_replace([',', '.'], '', $entero).($decimales === '' ? '' : '.'.$decimales);

        return is_numeric($numero) ? Dinero::aUnidadMenor($numero, $moneda) : null;
    }
}
