<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\TurnosCaja\Pages;

use App\Caja\GestorTurnos;
use App\Caja\TurnoRechazado;
use App\Filament\Resources\TurnosCaja\TurnoCajaResource;
use App\Models\Caja;
use App\Models\TurnoCaja;
use App\Support\Dinero;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListTurnosCaja extends ListRecords
{
    protected static string $resource = TurnoCajaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('abrir')
                ->label(__('caja.turnos.abrir'))
                ->icon(Heroicon::OutlinedLockOpen)
                ->modalDescription(__('caja.turnos.abrir_ayuda'))
                ->schema([
                    Select::make('caja_id')->label(__('ui.cajas.modelo_campo'))->required()->native(false)
                        ->options(fn (): array => $this->cajasLibres())
                        ->default(fn (): ?int => count($this->cajasLibres()) === 1 ? (int) array_key_first($this->cajasLibres()) : null),
                    TextInput::make('fondo')->label(__('caja.turnos.fondo'))->helperText(__('caja.turnos.fondo_ayuda'))->required()->numeric()->minValue(0)->step('any')->default(0),
                ])
                ->action(function (array $data): void {
                    $caja = Caja::query()->whereIn('propiedad_id', TurnoCajaResource::propiedadesGestionables())->findOrFail((int) $data['caja_id']);
                    try {
                        app(GestorTurnos::class)->abrir($caja, Dinero::aUnidadMenor((string) $data['fondo'], $caja->propiedad->moneda));
                    } catch (TurnoRechazado $e) {
                        Notification::make()->danger()->title($e->getMessage())->send();

                        return;
                    }
                    Notification::make()->success()->title(__('caja.turnos.abierto_ok', ['caja' => $caja->nombre]))->send();
                }),
        ];
    }

    /**
     * Las cajas sin un turno abierto. Con varias propiedades, cada una dice de cuál es.
     *
     * @return array<int, string>
     */
    private function cajasLibres(): array
    {
        $propiedades = TurnoCajaResource::propiedadesGestionables();
        $ocupadas = TurnoCaja::query()->whereIn('propiedad_id', $propiedades)->whereNotNull('caja_abierta')->pluck('caja_id');

        return Caja::query()->with('propiedad')->whereIn('propiedad_id', $propiedades)->where('activa', true)->whereNotIn('id', $ocupadas)->orderBy('nombre')->get()
            ->mapWithKeys(fn (Caja $caja): array => [$caja->id => count($propiedades) > 1 ? $caja->nombre.' · '.$caja->propiedad->nombre : $caja->nombre])->all();
    }
}
