<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Filament\Resources\Huespedes\Pages;

use App\Documentos\CamposPropios;
use App\Filament\Resources\Huespedes\HuespedResource;
use App\Filament\Resources\Reservas\ReservaResource;
use App\Models\Huesped;
use App\Models\RegistroAuditoria;
use Filament\Resources\Pages\CreateRecord;

class CreateHuesped extends CreateRecord
{
    protected static string $resource = HuespedResource::class;

    protected static bool $canCreateAnother = false;

    /** @var array<int|string, mixed> */
    private array $camposPropios = [];

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        abort_unless(in_array((int) ($data['propiedad_id'] ?? 0), ReservaResource::propiedadesGestionables(), true), 403);
        $this->camposPropios = (array) ($data['campos_propios'] ?? []);
        unset($data['campos_propios']);

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var Huesped $huesped */
        $huesped = $this->record;
        CamposPropios::guardar($huesped, $this->camposPropios);
        RegistroAuditoria::anotar('huesped.crear', $this->record, [], (int) $this->record->getAttribute('propiedad_id'));
    }

    protected function getRedirectUrl(): string
    {
        return HuespedResource::getUrl('view', ['record' => $this->record]);
    }
}
