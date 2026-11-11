<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Filament\Resources\Unidades\Pages;

use App\Filament\Resources\Unidades\Schemas\UnidadForm;
use App\Filament\Resources\Unidades\UnidadResource;
use App\Models\RegistroAuditoria;
use App\Models\Tarifa;
use App\Models\Unidad;
use App\Reservas\CodigosAcceso;
use Filament\Resources\Pages\CreateRecord;

class CreateUnidad extends CreateRecord
{
    protected static string $resource = UnidadResource::class;

    /** @var array<int, mixed> */
    private array $otrasTarifas = [];

    /** @var array<int, mixed>|null */
    private ?array $partes = null;

    private ?string $codigoAcceso = null;

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
        $this->partes = array_key_exists('partes', $data) ? (array) $data['partes'] : null;
        $this->codigoAcceso = isset($data['codigo_acceso']) ? (string) $data['codigo_acceso'] : null;
        unset($data['otras_tarifas'], $data['partes'], $data['codigo_acceso']);

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var Unidad $unidad */
        $unidad = $this->record;
        UnidadForm::guardarOtrasTarifas($unidad, $this->otrasTarifas);
        UnidadForm::guardarPartes($unidad, $this->partes);
        CodigosAcceso::guardarDeUnidad($unidad, $this->codigoAcceso);
        RegistroAuditoria::anotar('unidad.crear', $this->record, [], (int) $this->record->getAttribute('propiedad_id'));
    }

    protected function getRedirectUrl(): string
    {
        return UnidadResource::getUrl('index');
    }
}
