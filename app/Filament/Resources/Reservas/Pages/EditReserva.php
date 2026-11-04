<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\Reservas\Pages;

use App\Filament\Resources\Reservas\ReservaResource;
use App\Models\Reserva;
use App\Reservas\GestorReservas;
use App\Reservas\ReservaRechazada;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class EditReserva extends EditRecord
{
    protected static string $resource = ReservaResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Reserva $record */
        try {
            return app(GestorReservas::class)->modificar($record, $data, isset($data['version']) ? (int) $data['version'] : null);
        } catch (ReservaRechazada $e) {
            throw ValidationException::withMessages(['data.'.$e->campo => $e->getMessage()]);
        }
    }

    protected function getRedirectUrl(): string
    {
        return ReservaResource::getUrl('view', ['record' => $this->record]);
    }
}
