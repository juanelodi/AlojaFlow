<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\Huespedes\Pages;

use App\Documentos\CamposPropios;
use App\Filament\Resources\Huespedes\HuespedResource;
use App\Models\Huesped;
use App\Models\RegistroAuditoria;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditHuesped extends EditRecord
{
    protected static string $resource = HuespedResource::class;

    /** @var array<int|string, mixed> */
    private array $camposPropios = [];

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->visible(fn (): bool => HuespedResource::canDelete($this->record))->after(fn () => RegistroAuditoria::anotar('huesped.eliminar', $this->record, [], (int) $this->record->getAttribute('propiedad_id'))),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Huesped $huesped */
        $huesped = $this->record;
        $data['campos_propios'] = CamposPropios::valoresParaFormulario($huesped);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->camposPropios = (array) ($data['campos_propios'] ?? []);
        unset($data['campos_propios']);

        return $data;
    }

    protected function afterSave(): void
    {
        /** @var Huesped $huesped */
        $huesped = $this->record;
        CamposPropios::guardar($huesped, $this->camposPropios);
        // Solo los nombres de los campos: nunca datos personales en la auditoría.
        RegistroAuditoria::anotar('huesped.editar', $this->record, ['campos' => array_keys($this->record->getChanges())], (int) $this->record->getAttribute('propiedad_id'));
    }

    protected function getRedirectUrl(): string
    {
        return HuespedResource::getUrl('view', ['record' => $this->record]);
    }
}
