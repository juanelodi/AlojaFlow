<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\DocumentosLegales\Pages;

use App\Filament\Resources\Base\CreaEnPropiedad;
use App\Filament\Resources\DocumentosLegales\DocumentoLegalResource;
use App\Models\DocumentoLegal;
use Filament\Resources\Pages\CreateRecord;

class CreateDocumentoLegal extends CreateRecord
{
    use CreaEnPropiedad;

    protected static string $resource = DocumentoLegalResource::class;

    protected static bool $canCreateAnother = false;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function completarDatos(array $data): array
    {
        $data['version'] = 1 + (int) DocumentoLegal::query()->where('propiedad_id', $data['propiedad_id'])->where('tipo', $data['tipo'])->where('idioma', $data['idioma'])->max('version');

        return $data;
    }
}
