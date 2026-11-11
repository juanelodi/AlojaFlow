<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Filament\Resources\Propiedades\Pages;

use App\Comunicacion\PlantillasBase;
use App\Dinero\SegundaMoneda;
use App\Documentos\DocumentosDePartida;
use App\Enums\ModoPropiedad;
use App\Filament\Resources\Propiedades\PropiedadResource;
use App\Models\Propiedad;
use App\Models\RegistroAuditoria;
use App\Modulos\Modulo;
use App\Modulos\Modulos;
use Filament\Resources\Pages\CreateRecord;

class CreatePropiedad extends CreateRecord
{
    protected static string $resource = PropiedadResource::class;

    private ?string $segundaMoneda = null;

    private ?string $tipoCambio = null;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (! Modulos::activo(Modulo::Subordinacion)) {
            $data['modo'] = ModoPropiedad::Nativa->value;
        }
        $this->segundaMoneda = filled($data['segunda_moneda'] ?? null) ? (string) $data['segunda_moneda'] : null;
        $this->tipoCambio = filled($data['tipo_cambio'] ?? null) ? (string) $data['tipo_cambio'] : null;
        unset($data['segunda_moneda'], $data['tipo_cambio']);

        return $data;
    }

    protected function afterCreate(): void
    {
        RegistroAuditoria::anotar('propiedad.crear', $this->record, [], (int) $this->record->getKey());
        // Ya trae sus textos de partida (D-147). Ningún mensaje sale hasta que alguien prenda un envío.
        $propiedad = $this->record;
        assert($propiedad instanceof Propiedad);
        (new SegundaMoneda($propiedad))->guardar($this->segundaMoneda, $this->tipoCambio);
        PlantillasBase::sembrar($propiedad);
        DocumentosDePartida::sembrar($propiedad);
    }

    protected function getRedirectUrl(): string
    {
        return PropiedadResource::getUrl('edit', ['record' => $this->record]);
    }
}
