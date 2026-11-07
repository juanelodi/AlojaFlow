<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\Propiedades\Pages;

use App\Comunicacion\PlantillasBase;
use App\Documentos\DocumentosDePartida;
use App\Enums\ModoPropiedad;
use App\Filament\Resources\Propiedades\PropiedadResource;
use App\Models\Propiedad;
use App\Models\RegistroAuditoria;
use App\Modulos\Modulo;
use App\Modulos\Modulos;
use Filament\Resources\Pages\CreateRecord;

class CreatePropiedad extends CreateRecord
{
    protected static string $resource = PropiedadResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (! Modulos::activo(Modulo::Subordinacion)) {
            $data['modo'] = ModoPropiedad::Nativa->value;
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        RegistroAuditoria::anotar('propiedad.crear', $this->record, [], (int) $this->record->getKey());
        // Ya trae sus textos de partida (D-147). Ningún mensaje sale hasta que alguien prenda un envío.
        $propiedad = $this->record;
        assert($propiedad instanceof Propiedad);
        PlantillasBase::sembrar($propiedad);
        DocumentosDePartida::sembrar($propiedad);
    }

    protected function getRedirectUrl(): string
    {
        return PropiedadResource::getUrl('edit', ['record' => $this->record]);
    }
}
