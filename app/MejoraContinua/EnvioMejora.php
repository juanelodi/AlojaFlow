<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\MejoraContinua;

use App\Support\Ajustes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Lo que corre cada hora (alojaflow:mejora-continua, D-150): manda los reportes pendientes, los errores
 * juntados y, una vez al día, el latido. Si el receptor no responde, todo se queda para la siguiente hora.
 */
final class EnvioMejora
{
    /** Lo último que se mandó, tal cual, y cómo salió: lo ve el administrador en Mejora continua. */
    public const AJUSTE_ULTIMO_LATIDO = 'mejora.ultimo_latido';

    public const AJUSTE_ULTIMO_ENVIADO = 'mejora.ultimo_enviado';

    public function __construct(
        private readonly ClienteReceptor $cliente,
        private readonly ArmadorLatido $armador,
        private readonly ReportesMejora $reportes,
    ) {
    }

    /**
     * @return array<string, int|string> qué se hizo, para la consola
     */
    public function ejecutar(bool $forzarLatido = false): array
    {
        $resumen = ['reportes' => 0, 'errores' => 0, 'latido' => 'no'];
        if (! Permiso::hayReceptor()) {
            return $resumen;
        }
        $resumen['reportes'] = $this->reportes->enviarPendientes();
        $this->limpiar();
        if (! Permiso::activa()) {
            return $resumen;
        }
        // Los errores y el latido van por separado: que falle uno no detiene al otro.
        try {
            $resumen['errores'] = $this->enviarErrores();
        } catch (ErrorReceptor $error) {
            $resumen['errores'] = $error->motivo;
        }
        if ($forzarLatido || $this->tocaLatido()) {
            $resumen['latido'] = $this->enviarLatido() ? 'enviado' : 'falló';
        }

        return $resumen;
    }

    /**
     * @return bool si llegó
     */
    public function enviarLatido(): bool
    {
        $latido = $this->armador->armar();
        try {
            $respuesta = $this->cliente->latido($latido);
        } catch (ErrorReceptor $error) {
            Ajustes::guardar(self::AJUSTE_ULTIMO_LATIDO, ['en' => now('UTC')->toIso8601String(), 'ok' => false, 'motivo' => $error->motivo] + $this->ultimoBueno());

            return false;
        }
        Ajustes::guardar(self::AJUSTE_ULTIMO_LATIDO, ['en' => now('UTC')->toIso8601String(), 'ok' => true, 'bueno_en' => now('UTC')->toIso8601String()]);
        Ajustes::guardar(self::AJUSTE_ULTIMO_ENVIADO, $latido);

        // Se traen los reportes si el equipo contestó algo desde la última vez, o si hay alguno abierto: su
        // estado puede cambiar sin una respuesta nueva.
        $respondido = $respuesta['ultima_respuesta_en'] ?? null;
        $visto = Ajustes::obtener(ReportesMejora::AJUSTE_SINCRONIZADOS);
        $respuestaNueva = is_string($respondido) && (! is_string($visto) || Carbon::parse($respondido)->greaterThan(Carbon::parse($visto)));
        if ($respuestaNueva || $this->reportes->hayAbiertos()) {
            try {
                $this->reportes->sincronizar();
            } catch (ErrorReceptor) {
                // Se intenta con el siguiente latido.
            }
        }

        return true;
    }

    /**
     * Una vez al día, a una hora que depende de la instalación, para que no lleguen todas a la vez. Si se
     * pasó esa hora sin enviar (sin cron y sin visitas), en cuanto se pueda.
     */
    public function tocaLatido(): bool
    {
        $bueno = $this->ultimoBueno()['bueno_en'] ?? null;
        if (! is_string($bueno)) {
            return true;
        }
        $horas = Carbon::parse($bueno)->diffInHours(now(), true);
        $hora = abs(crc32((string) ($this->cliente->uuid() ?? config('app.key')))) % 24;

        return $horas >= 26 || ($horas >= 20 && (int) now('UTC')->format('G') === $hora);
    }

    /**
     * @return int cuántas filas (errores distintos) se mandaron
     *
     * @throws ErrorReceptor
     */
    private function enviarErrores(): int
    {
        $enviados = 0;
        $lote = (int) config('alojaflow.mejora_continua.errores_por_envio');
        for ($vuelta = 0; $vuelta < 5; $vuelta++) {
            $filas = DB::table('mejora_errores')->orderBy('id')->limit($lote)->get();
            if ($filas->isEmpty()) {
                break;
            }
            try {
                $this->enviarLote($filas);
            } catch (ErrorReceptor $error) {
                if ($error->reintentable()) {
                    throw $error;
                }
                // El receptor no lo acepta y no lo va a aceptar: se descarta en vez de atorar a los demás.
            }
            DB::table('mejora_errores')->whereIn('id', $filas->pluck('id'))->delete();
            $enviados += $filas->count();
        }

        return $enviados;
    }

    /**
     * @param  Collection<int, stdClass>  $filas
     *
     * @throws ErrorReceptor
     */
    private function enviarLote(Collection $filas): void
    {
        $this->cliente->errores($filas->map(fn (stdClass $fila): array => array_filter([
            'clase' => $fila->clase,
            'mensaje' => $fila->mensaje,
            'codigo' => $fila->codigo,
            'archivo' => $fila->archivo,
            'linea' => $fila->linea === null ? null : (int) $fila->linea,
            'funcion' => $fila->funcion,
            'traza' => json_decode((string) $fila->traza, true) ?: [],
            'contexto' => json_decode((string) $fila->contexto, true) ?: [],
            'version' => $fila->version,
            'veces' => max(1, (int) $fila->veces),
            'primera_vez' => Carbon::parse((string) $fila->primera_vez, 'UTC')->toIso8601String(),
            'ultima_vez' => Carbon::parse((string) $fila->ultima_vez, 'UTC')->toIso8601String(),
        ], fn (mixed $valor): bool => $valor !== null))->values()->all());
    }

    private function limpiar(): void
    {
        $config = (array) config('alojaflow.mejora_continua');
        DB::table('mejora_pantallas')->where('fecha', '<', now()->subDays((int) $config['dias_pantallas'])->toDateString())->delete();
        DB::table('mejora_errores')->where('fecha', '<', now()->subDays((int) $config['dias_conservar'])->toDateString())->delete();
        // Sin permiso no se junta nada: lo que hubiera quedado de antes se borra.
        if (! Permiso::activa()) {
            DB::table('mejora_errores')->delete();
            DB::table('mejora_pantallas')->delete();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function ultimoBueno(): array
    {
        $ultimo = (array) Ajustes::obtener(self::AJUSTE_ULTIMO_LATIDO, []);

        return isset($ultimo['bueno_en']) ? ['bueno_en' => $ultimo['bueno_en']] : [];
    }
}
