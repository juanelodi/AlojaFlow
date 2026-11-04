<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\Huespedes\Pages;

use App\Filament\Resources\Huespedes\HuespedResource;
use App\Filament\Resources\Reservas\ReservaResource;
use App\Models\RegistroAuditoria;
use Filament\Resources\Pages\CreateRecord;

class CreateHuesped extends CreateRecord
{
    protected static string $resource = HuespedResource::class;

    protected static bool $canCreateAnother = false;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        abort_unless(in_array((int) ($data['propiedad_id'] ?? 0), ReservaResource::propiedadesGestionables(), true), 403);

        return $data;
    }

    protected function afterCreate(): void
    {
        RegistroAuditoria::anotar('huesped.crear', $this->record, [], (int) $this->record->getAttribute('propiedad_id'));
    }

    protected function getRedirectUrl(): string
    {
        return HuespedResource::getUrl('view', ['record' => $this->record]);
    }
}
