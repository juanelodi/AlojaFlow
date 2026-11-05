<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\Tarifas\Pages;

use App\Filament\Resources\Tarifas\Schemas\TarifaForm;
use App\Filament\Resources\Tarifas\TarifaResource;
use App\Models\RegistroAuditoria;
use App\Models\Tarifa;
use Filament\Resources\Pages\CreateRecord;

class CreateTarifa extends CreateRecord
{
    protected static string $resource = TarifaResource::class;

    /** @var array<int, mixed> */
    private array $otrasUnidades = [];

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        abort_unless(in_array((int) ($data['propiedad_id'] ?? 0), TarifaResource::propiedadesGestionables(), true), 403);
        $this->otrasUnidades = (array) ($data['otras_unidades'] ?? []);

        return TarifaForm::normalizar($data, (int) $data['propiedad_id']);
    }

    protected function afterCreate(): void
    {
        /** @var Tarifa $tarifa */
        $tarifa = $this->record;
        TarifaForm::guardarOtrasUnidades($tarifa, $this->otrasUnidades);
        RegistroAuditoria::anotar('tarifa.crear', $this->record, [], (int) $this->record->getAttribute('propiedad_id'));
    }

    protected function getRedirectUrl(): string
    {
        return TarifaResource::getUrl('index');
    }
}
