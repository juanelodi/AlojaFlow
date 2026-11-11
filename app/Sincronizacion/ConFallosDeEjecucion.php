<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Sincronizacion;

use App\Enums\EstadoEjecucion;
use App\Enums\EstadoPrincipal;
use App\Enums\NivelAviso;
use App\Models\Aviso;
use App\Models\EjecucionSincronizacion;
use App\Models\Principal;
use App\Sincronizacion\HotelDruid\CodigoError;
use App\Sincronizacion\HotelDruid\ErrorSincronizacion;
use App\Sincronizacion\HotelDruid\Sincronizador;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Lo que pasa cuando una sincronización falla: se anota, se pausa si reintentar no sirve y se avisa.
 */
trait ConFallosDeEjecucion
{
    /**
     * @param  array<string, int>  $incidencias
     */
    private function anotarFallo(Principal $principal, EjecucionSincronizacion $ejecucion, ErrorSincronizacion $error, array $incidencias = []): void
    {
        $ejecucion->update([
            'estado' => EstadoEjecucion::Fallida,
            'terminada_en' => now(),
            'paso_fallido' => $error->paso,
            'codigo_error' => $error->codigo->value,
            'detalle' => mb_substr($error->getMessage(), 0, 2000),
            'incidencias' => $incidencias ?: null,
        ]);

        $fallos = $principal->fallos_consecutivos + 1;
        $cambios = ['fallos_consecutivos' => $fallos, 'proxima_en' => now()->addMinutes($principal->frecuencia_minutos)];
        if ($error->codigo->pausaLaPrincipal()) {
            $cambios += [
                'estado' => EstadoPrincipal::Pausada,
                'codigo_pausa' => $error->codigo->value,
                // El límite diario se reinicia a las 00:00 de la principal; lo demás espera al administrador.
                'pausada_hasta' => $error->codigo === CodigoError::LimiteDiario
                    ? CarbonImmutable::now($principal->propiedad->zona_horaria)->addDay()->startOfDay()->utc()
                    : null,
            ];
        }
        $principal->forceFill($cambios)->save();

        if ($error->codigo->pausaLaPrincipal() || $fallos >= (int) config('alojaflow.subordinacion.fallos_para_avisar')) {
            $datos = ['codigo' => $error->codigo->value, 'mensaje' => $this->explicar($error)];
            // Si ya estaba abierto se pone al día: el fallo de ahora puede no ser el de hace una semana.
            Aviso::abrir($principal->propiedad_id, Sincronizador::AVISO_FALLO, NivelAviso::Error, $datos)->update(['datos' => $datos]);
        }
    }

    /**
     * Lo que se lee en el resumen. Con lo que respondió la principal, cuando el código por sí solo no dice qué hacer.
     */
    private function explicar(ErrorSincronizacion $error): string
    {
        $etiqueta = $error->codigo->etiqueta();
        if (! in_array($error->codigo, [CodigoError::RespuestaDesconocida, CodigoError::FormatoInvalido], true) || $error->getMessage() === $etiqueta) {
            return $etiqueta;
        }

        return $etiqueta.' '.__('sincronizacion.respondio', [
            'paso' => __('sincronizacion.pasos.'.$error->paso),
            'detalle' => Str::limit($error->getMessage(), 160),
        ]);
    }
}
