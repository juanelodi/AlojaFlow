<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\EstadosCuenta\Pages;

use App\Filament\Resources\EstadosCuenta\EstadoCuentaResource;
use App\Models\Propiedad;
use App\Propietarios\GestorEstados;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListEstadosCuenta extends ListRecords
{
    protected static string $resource = EstadoCuentaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generar')
                ->label(__('propietarios.estado.generar'))
                ->icon(Heroicon::OutlinedCalculator)
                ->visible(fn (): bool => EstadoCuentaResource::propiedadesGestionables() !== [])
                ->modalDescription(__('propietarios.estado.generar_ayuda'))
                ->fillForm(fn (): array => [
                    'propiedad' => count(EstadoCuentaResource::propiedadesGestionables()) === 1 ? EstadoCuentaResource::propiedadesGestionables()[0] : null,
                    'mes' => now()->subMonthNoOverflow()->format('Y-m'),
                ])
                ->schema([
                    Select::make('propiedad')->label(__('ui.comun.propiedad'))->native(false)->required()
                        ->options(fn (): array => Propiedad::query()->whereIn('id', EstadoCuentaResource::propiedadesGestionables())->orderBy('nombre')->pluck('nombre', 'id')->all()),
                    TextInput::make('mes')->label(__('propietarios.estado.mes'))->type('month')->required()->regex('/^\d{4}-(0[1-9]|1[0-2])$/'),
                ])
                ->action(function (array $data, GestorEstados $estados): void {
                    $propiedad = Propiedad::query()->whereIn('id', EstadoCuentaResource::propiedadesGestionables())->findOrFail((int) $data['propiedad']);
                    $cuantos = $estados->generarTodos($propiedad, CarbonImmutable::parse($data['mes'].'-01'));
                    Notification::make()->success()->title(trans_choice('propietarios.estado.generados', $cuantos))->send();
                }),
        ];
    }
}
