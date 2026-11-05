<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Documentos;

use App\Enums\AlcanceDocumento;
use App\Models\DocumentoPropio;
use App\Models\Propiedad;

/**
 * Tres documentos para no empezar de una hoja en blanco, en cada idioma (D-123). El contrato es solo un punto de partida: no lo ha revisado ningún abogado.
 */
final class DocumentosDePartida
{
    private const DOCUMENTOS = [
        'contrato' => AlcanceDocumento::Reserva,
        'registro' => AlcanceDocumento::Reserva,
        'llegadas' => AlcanceDocumento::Periodo,
    ];

    /**
     * Los que falten: los que ya existan, quizá cambiados, se quedan.
     *
     * @return int cuántos creó
     */
    public static function sembrar(Propiedad $propiedad): int
    {
        $creados = 0;
        $orden = 0;
        foreach (self::DOCUMENTOS as $clave => $alcance) {
            $nombre = __('documentos_propios.base.'.$clave.'.nombre', [], $propiedad->idioma);
            $orden++;
            if (DocumentoPropio::query()->where('propiedad_id', $propiedad->id)->where('nombre', $nombre)->exists()) {
                continue;
            }
            DocumentoPropio::query()->create(['propiedad_id' => $propiedad->id, 'nombre' => $nombre, 'alcance' => $alcance, 'cuerpo' => self::textos($clave), 'orden' => $orden]);
            $creados++;
        }

        return $creados;
    }

    /**
     * @return array<string, string>
     */
    private static function textos(string $clave): array
    {
        $anterior = app()->getLocale();
        $textos = [];
        foreach ((array) config('alojaflow.idiomas') as $idioma) {
            app()->setLocale($idioma);
            $textos[$idioma] = view('documentos.de-partida.'.$clave)->render();
        }
        app()->setLocale($anterior);

        return $textos;
    }
}
