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
use App\Reservas\CodigosAcceso;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditUnidad extends EditRecord
{
    protected static string $resource = UnidadResource::class;

    /** @var array<int, mixed> */
    private array $otrasTarifas = [];

    /** @var array<int, mixed>|null */
    private ?array $partes = null;

    private ?string $codigoAcceso = null;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->visible(fn (): bool => UnidadResource::canDelete($this->record))->after(fn () => RegistroAuditoria::anotar('unidad.eliminar', $this->record, [], (int) $this->record->getAttribute('propiedad_id'))),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (filled($data['tarifa_id'] ?? null)) {
            abort_unless(Tarifa::query()->where('propiedad_id', (int) $this->record->getAttribute('propiedad_id'))->whereKey((int) $data['tarifa_id'])->exists(), 403);
        }
        $data['codigo'] = $data['nombre'];
        $this->otrasTarifas = (array) ($data['otras_tarifas'] ?? []);
        $this->partes = array_key_exists('partes', $data) ? (array) $data['partes'] : null;
        $this->codigoAcceso = isset($data['codigo_acceso']) ? (string) $data['codigo_acceso'] : null;
        unset($data['otras_tarifas'], $data['partes'], $data['codigo_acceso']);

        return $data;
    }

    protected function afterSave(): void
    {
        /** @var Unidad $unidad */
        $unidad = $this->record;
        UnidadForm::guardarOtrasTarifas($unidad, $this->otrasTarifas);
        UnidadForm::guardarPartes($unidad, $this->partes);
        CodigosAcceso::guardarDeUnidad($unidad, $this->codigoAcceso);
        RegistroAuditoria::anotar('unidad.editar', $this->record, ['campos' => array_keys($this->record->getChanges())], (int) $this->record->getAttribute('propiedad_id'));
    }

    protected function getRedirectUrl(): string
    {
        return UnidadResource::getUrl('index');
    }
}
