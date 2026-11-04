<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\Tarifas\Pages;

use App\Filament\Resources\Tarifas\TarifaResource;
use App\Models\RegistroAuditoria;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTarifa extends EditRecord
{
    protected static string $resource = TarifaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->modalDescription(__('ui.tarifas.eliminar_ayuda'))
                ->after(fn () => RegistroAuditoria::anotar('tarifa.eliminar', $this->record, [], (int) $this->record->getAttribute('propiedad_id'))),
        ];
    }

    protected function afterSave(): void
    {
        RegistroAuditoria::anotar('tarifa.editar', $this->record, ['campos' => array_keys($this->record->getChanges())], (int) $this->record->getAttribute('propiedad_id'));
    }

    protected function getRedirectUrl(): string
    {
        return TarifaResource::getUrl('index');
    }
}
