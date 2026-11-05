<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\Reservas\Pages;

use App\Documentos\CamposPropios;
use App\Filament\Resources\Reservas\ReservaResource;
use App\Filament\Resources\Reservas\Schemas\ReservaForm;
use App\Models\Propiedad;
use App\Reservas\GestorReservas;
use App\Reservas\ReservaRechazada;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreateReserva extends CreateRecord
{
    public static bool $formActionsAreSticky = true;

    protected static string $resource = ReservaResource::class;

    protected static bool $canCreateAnother = false;

    protected function afterFill(): void
    {
        $total = ReservaForm::totalDeLaDireccion();
        if ($total !== null) {
            $this->data['total'] = $total;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $propiedadId = (int) ($data['propiedad_id'] ?? 0);
        abort_unless(in_array($propiedadId, ReservaResource::propiedadesGestionables(), true), 403);

        try {
            $reserva = app(GestorReservas::class)->crear(Propiedad::query()->findOrFail($propiedadId), $data);
            CamposPropios::guardar($reserva, (array) ($data['campos_propios'] ?? []));

            return $reserva;
        } catch (ReservaRechazada $e) {
            throw ValidationException::withMessages(['data.'.$e->campo => $e->getMessage()]);
        }
    }

    protected function getRedirectUrl(): string
    {
        return ReservaResource::getUrl('view', ['record' => $this->record]);
    }
}
