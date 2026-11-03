<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\Propiedades\Pages;

use App\Filament\Resources\Propiedades\AccionesSincronizacion;
use App\Filament\Resources\Propiedades\PropiedadResource;
use App\Models\RegistroAuditoria;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPropiedad extends EditRecord
{
    protected static string $resource = PropiedadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            AccionesSincronizacion::probar(),
            AccionesSincronizacion::sincronizar(),
            AccionesSincronizacion::reanudar(),
            DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        // Sin valores: nunca se anotan credenciales (regla 13).
        RegistroAuditoria::anotar('propiedad.editar', $this->record, ['campos' => array_keys($this->record->getChanges())], (int) $this->record->getKey());
    }
}
