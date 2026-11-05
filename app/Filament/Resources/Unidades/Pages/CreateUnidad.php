<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\Unidades\Pages;

use App\Filament\Resources\Unidades\Schemas\UnidadForm;
use App\Filament\Resources\Unidades\UnidadResource;
use App\Models\RegistroAuditoria;
use App\Models\Tarifa;
use App\Models\Unidad;
use Filament\Resources\Pages\CreateRecord;

class CreateUnidad extends CreateRecord
{
    protected static string $resource = UnidadResource::class;

    /** @var array<int, mixed> */
    private array $otrasTarifas = [];

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $propiedadId = (int) ($data['propiedad_id'] ?? 0);
        abort_unless(in_array($propiedadId, UnidadResource::propiedadesGestionables(), true), 403);
        if (filled($data['tarifa_id'] ?? null)) {
            abort_unless(Tarifa::query()->where('propiedad_id', $propiedadId)->whereKey((int) $data['tarifa_id'])->exists(), 403);
        }
        $data['codigo'] = $data['nombre'];
        $this->otrasTarifas = (array) ($data['otras_tarifas'] ?? []);
        unset($data['otras_tarifas']);

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var Unidad $unidad */
        $unidad = $this->record;
        UnidadForm::guardarOtrasTarifas($unidad, $this->otrasTarifas);
        RegistroAuditoria::anotar('unidad.crear', $this->record, [], (int) $this->record->getAttribute('propiedad_id'));
    }

    protected function getRedirectUrl(): string
    {
        return UnidadResource::getUrl('index');
    }
}
