<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\Base;

use App\Models\RegistroAuditoria;
use Filament\Actions\DeleteAction;
use Illuminate\Support\Str;

/**
 * Edición de un catálogo de propiedad, con su botón de borrar y la auditoría.
 */
trait EditaEnPropiedad
{
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn (): bool => static::getResource()::canDelete($this->record))
                ->after(fn () => RegistroAuditoria::anotar(Str::snake(class_basename($this->record)).'.eliminar', $this->record, [], (int) $this->record->getAttribute('propiedad_id'))),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        static::getResource()::comprobarReferencias($data, (int) $this->record->getAttribute('propiedad_id'));

        return $data;
    }

    protected function afterSave(): void
    {
        // Solo los nombres de los campos: la auditoría no guarda datos personales ni secretos.
        RegistroAuditoria::anotar(Str::snake(class_basename($this->record)).'.editar', $this->record, ['campos' => array_keys($this->record->getChanges())], (int) $this->record->getAttribute('propiedad_id'));
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
