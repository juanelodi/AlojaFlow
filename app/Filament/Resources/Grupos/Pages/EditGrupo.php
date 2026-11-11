<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Filament\Resources\Grupos\Pages;

use App\Filament\Resources\Grupos\GrupoResource;
use App\Models\Grupo;
use App\Models\RegistroAuditoria;
use App\Reservas\GestorGrupos;
use App\Support\Dinero;
use Filament\Resources\Pages\EditRecord;

/**
 * Los datos del grupo y su precio. Las habitaciones se cambian en su pantalla, de una en una: cada una puede fallar por su lado.
 */
class EditGrupo extends EditRecord
{
    protected static string $resource = GrupoResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getRelationManagers(): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $moneda = $this->grupo()->propiedad->moneda;
        $data['precio_total'] = $data['precio_total'] === null ? null : Dinero::aDecimal((int) $data['precio_total'], $moneda);
        $data['extras'] = Dinero::aDecimal((int) $data['extras'], $moneda);
        $filas = [];
        foreach ((array) ($data['precios_capacidad'] ?? []) as $capacidad => $precio) {
            $filas[] = ['capacidad' => $capacidad, 'precio' => Dinero::aDecimal((int) $precio, $moneda)];
        }
        $data['precios_capacidad'] = $filas;

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        GrupoResource::comprobarReferencias($data, $this->grupo()->propiedad_id);
        $moneda = $this->grupo()->propiedad->moneda;
        $data['precio_total'] = blank($data['precio_total'] ?? null) ? null : Dinero::aUnidadMenor((string) $data['precio_total'], $moneda);
        $data['extras'] = Dinero::aUnidadMenor((string) ($data['extras'] ?? 0), $moneda);
        $precios = [];
        foreach ((array) ($data['precios_capacidad'] ?? []) as $fila) {
            $precios[(int) $fila['capacidad']] = Dinero::aUnidadMenor((string) $fila['precio'], $moneda);
        }
        $data['precios_capacidad'] = $precios === [] ? null : $precios;
        $data['gratis_cada'] = blank($data['gratis_cada'] ?? null) ? null : (int) $data['gratis_cada'];
        $data['liberar_sin_responsable'] = (bool) ($data['liberar_sin_responsable'] ?? false);

        return $data;
    }

    protected function afterSave(): void
    {
        $grupo = $this->grupo();
        if ($grupo->wasChanged(['modo_precio', 'precio_total', 'precios_capacidad', 'gratis_cada', 'descuento_porcentaje', 'extras'])) {
            app(GestorGrupos::class)->aplicarPrecios($grupo, forzar: true);
        }
        RegistroAuditoria::anotar('grupo.editar', $grupo, ['campos' => array_keys($grupo->getChanges())], $grupo->propiedad_id);
    }

    protected function getRedirectUrl(): string
    {
        return GrupoResource::getUrl('view', ['record' => $this->record]);
    }

    private function grupo(): Grupo
    {
        /** @var Grupo $grupo */
        $grupo = $this->record;

        return $grupo;
    }
}
