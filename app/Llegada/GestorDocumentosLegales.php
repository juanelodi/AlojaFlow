<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Llegada;

use App\Enums\TipoDocumentoLegal;
use App\Models\DocumentoLegal;
use App\Models\Propiedad;
use App\Models\RegistroAuditoria;
use App\Paises\Paises;
use Illuminate\Support\Facades\DB;

/**
 * Publicar fija el texto con su huella: una aceptación siempre apunta a lo que se aceptó.
 */
final class GestorDocumentosLegales
{
    public function publicar(DocumentoLegal $documento): void
    {
        DB::transaction(function () use ($documento): void {
            DocumentoLegal::query()->where('propiedad_id', $documento->propiedad_id)->where('tipo', $documento->tipo->value)->where('idioma', $documento->idioma)
                ->whereNotNull('publicado_en')->whereNull('retirado_en')->update(['retirado_en' => now('UTC')]);
            $documento->update(['huella' => hash('sha256', $documento->cuerpo), 'publicado_en' => now('UTC'), 'publicado_por' => auth()->id()]);
            RegistroAuditoria::anotar('documento_legal.publicar', $documento, ['tipo' => $documento->tipo->value, 'idioma' => $documento->idioma, 'version' => $documento->version], $documento->propiedad_id);
        });
    }

    public function nuevaVersion(DocumentoLegal $documento): DocumentoLegal
    {
        $ultima = (int) DocumentoLegal::query()->where('propiedad_id', $documento->propiedad_id)->where('tipo', $documento->tipo->value)->where('idioma', $documento->idioma)->max('version');

        return DocumentoLegal::query()->create([
            'propiedad_id' => $documento->propiedad_id,
            'tipo' => $documento->tipo,
            'idioma' => $documento->idioma,
            'version' => $ultima + 1,
            'titulo' => $documento->titulo,
            'cuerpo' => $documento->cuerpo,
        ]);
    }

    /**
     * Crea como borrador los textos del país que falten, con los datos de la propiedad.
     *
     * @return int documentos creados
     */
    public function crearBase(Propiedad $propiedad): int
    {
        $creados = 0;
        $pais = Paises::de($propiedad);
        foreach (array_intersect((array) config('alojaflow.idiomas'), [$propiedad->idioma, 'es', 'en']) as $idioma) {
            foreach ($pais->textosBase((string) $idioma) as $tipo => $texto) {
                $tipo = TipoDocumentoLegal::from($tipo);
                if (DocumentoLegal::query()->where('propiedad_id', $propiedad->id)->where('tipo', $tipo->value)->where('idioma', $idioma)->exists()) {
                    continue;
                }
                DocumentoLegal::query()->create([
                    'propiedad_id' => $propiedad->id,
                    'tipo' => $tipo,
                    'idioma' => $idioma,
                    'version' => 1,
                    'titulo' => $texto['titulo'],
                    'cuerpo' => strtr($texto['cuerpo'], [
                        '{propiedad}' => $propiedad->nombre,
                        '{domicilio}' => (string) ($propiedad->getAttribute('domicilio') ?: '[domicilio]'),
                        '{correo}' => (string) ($propiedad->getAttribute('correo') ?: '[correo]'),
                        '{telefono}' => (string) ($propiedad->getAttribute('telefono') ?: '[teléfono]'),
                    ]),
                ]);
                $creados++;
            }
        }

        return $creados;
    }
}
