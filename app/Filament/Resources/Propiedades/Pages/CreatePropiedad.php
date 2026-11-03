<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\Propiedades\Pages;

use App\Filament\Resources\Propiedades\PropiedadResource;
use App\Models\RegistroAuditoria;
use Filament\Resources\Pages\CreateRecord;

class CreatePropiedad extends CreateRecord
{
    protected static string $resource = PropiedadResource::class;

    protected function afterCreate(): void
    {
        RegistroAuditoria::anotar('propiedad.crear', $this->record, [], (int) $this->record->getKey());
    }

    protected function getRedirectUrl(): string
    {
        return PropiedadResource::getUrl('edit', ['record' => $this->record]);
    }
}
