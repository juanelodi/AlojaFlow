<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\Grupos\Pages;

use App\Enums\EstadoReserva;
use App\Filament\Resources\Grupos\GrupoResource;
use App\Models\Propiedad;
use App\Reservas\GestorGrupos;
use App\Reservas\ReservaRechazada;
use Carbon\CarbonImmutable;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreateGrupo extends CreateRecord
{
    protected static string $resource = GrupoResource::class;

    protected static bool $canCreateAnother = false;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $propiedadId = (int) ($data['propiedad_id'] ?? 0);
        abort_unless(in_array($propiedadId, GrupoResource::propiedadesGestionables(), true), 403);

        try {
            return app(GestorGrupos::class)->crear(
                Propiedad::query()->findOrFail($propiedadId),
                (string) $data['nombre'],
                (int) $data['coordinador_id'],
                array_map('intval', array_values((array) $data['unidades'])),
                CarbonImmutable::parse((string) $data['llegada'])->startOfDay(),
                CarbonImmutable::parse((string) $data['salida'])->startOfDay(),
                (int) $data['personas'],
                EstadoReserva::from((string) $data['estado']),
                $data['notas'] ?? null,
            );
        } catch (ReservaRechazada $e) {
            throw ValidationException::withMessages(['data.unidades' => $e->getMessage()]);
        }
    }

    protected function getRedirectUrl(): string
    {
        return GrupoResource::getUrl('view', ['record' => $this->record]);
    }
}
