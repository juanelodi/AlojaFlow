<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Filament\Resources\Reservas\Pages;

use App\Documentos\CamposPropios;
use App\Filament\Resources\Reservas\ReservaResource;
use App\Models\CargoReserva;
use App\Models\Reserva;
use App\Reservas\GestorReservas;
use App\Reservas\ReservaRechazada;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class EditReserva extends EditRecord
{
    public static bool $formActionsAreSticky = true;

    protected static string $resource = ReservaResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Reserva $reserva */
        $reserva = $this->record;
        $data['cupon_codigo'] = $reserva->cupon?->codigo;
        $data['sin_catalogo'] = (int) $reserva->cargos()->whereNull('concepto_cargo_id')->sum('importe');
        $data['esta_reserva'] = $reserva->id;
        $data['campos_propios'] = CamposPropios::valoresParaFormulario($reserva);
        // Las obligatorias se añaden solas: en el formulario solo van las elegidas.
        $data['cargos'] = $reserva->cargos()->with('concepto')->get()
            ->filter(fn (CargoReserva $c): bool => $c->concepto !== null && ! $c->concepto->obligatorio)
            ->map(fn (CargoReserva $c): array => ['concepto_cargo_id' => $c->concepto_cargo_id, 'cantidad' => (float) $c->cantidad])
            ->values()->all();

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Reserva $record */
        $data['reserva_id'] = $record->id;
        try {
            $reserva = app(GestorReservas::class)->modificar($record, $data, isset($data['version']) ? (int) $data['version'] : null);
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
