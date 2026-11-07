<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\Plantillas\Pages;

use App\Comunicacion\PlantillasBase;
use App\Comunicacion\VariablesMensaje;
use App\Filament\Resources\Plantillas\PlantillaResource;
use App\Models\Propiedad;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;

class ListPlantillas extends ListRecords
{
    protected static string $resource = PlantillaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('variablesPropias')
                ->label(__('operacion.mensajes.variables'))
                ->icon(Heroicon::OutlinedVariable)
                ->color('gray')
                ->modalDescription(__('operacion.mensajes.variables_ayuda'))
                ->schema([
                    Select::make('propiedad')->label(__('ui.comun.propiedad'))->required()->native(false)->live()
                        ->options(fn (): array => Propiedad::query()->whereIn('id', PlantillaResource::propiedadesGestionables())->orderBy('nombre')->pluck('nombre', 'id')->all())
                        ->afterStateUpdated(fn (mixed $state, Set $set) => $set('variables', VariablesMensaje::propias((int) $state))),
                    KeyValue::make('variables')->label(__('operacion.mensajes.variables'))->keyLabel(__('operacion.mensajes.variable_nombre'))->valueLabel(__('operacion.mensajes.variable_valor')),
                ])
                ->action(function (array $data): void {
                    abort_unless(in_array((int) $data['propiedad'], PlantillaResource::propiedadesGestionables(), true), 403);
                    VariablesMensaje::guardarPropias((int) $data['propiedad'], (array) ($data['variables'] ?? []));
                    Notification::make()->success()->title(__('operacion.mensajes.variables_guardadas'))->send();
                }),
            Action::make('crearBase')
                ->label(__('mensajes.plantillas.crear_base'))
                ->icon(Heroicon::OutlinedSparkles)
                ->color('gray')
                ->schema([Select::make('propiedad')->label(__('ui.comun.propiedad'))->required()->native(false)
                    ->options(fn (): array => Propiedad::query()->whereIn('id', PlantillaResource::propiedadesGestionables())->orderBy('nombre')->pluck('nombre', 'id')->all())
                    ->default(function (): ?int {
                        $permitidas = PlantillaResource::propiedadesGestionables();

                        return count($permitidas) === 1 ? $permitidas[0] : null;
                    })])
                ->visible(fn (): bool => PlantillaResource::canCreate())
                ->action(function (array $data): void {
                    $propiedad = Propiedad::query()->whereIn('id', PlantillaResource::propiedadesGestionables())->findOrFail((int) $data['propiedad']);
                    Notification::make()->success()->title(__('mensajes.plantillas.creadas', ['n' => PlantillasBase::sembrar($propiedad)]))->send();
                }),
            Action::make('activarEnvios')->label(__('mensajes.plantillas.activar_envios'))->icon(Heroicon::OutlinedBolt)->color('gray')
                ->modalDescription(__('mensajes.plantillas.activar_envios_ayuda'))
                ->schema([Select::make('propiedad')->label(__('ui.comun.propiedad'))->required()->native(false)
                    ->options(fn (): array => Propiedad::query()->whereIn('id', PlantillaResource::propiedadesGestionables())->orderBy('nombre')->pluck('nombre', 'id')->all())
                    ->default(function (): ?int {
                        $permitidas = PlantillaResource::propiedadesGestionables();

                        return count($permitidas) === 1 ? $permitidas[0] : null;
                    })])
                ->visible(fn (): bool => PlantillaResource::canCreate())
                ->action(function (array $data): void {
                    $propiedad = Propiedad::query()->whereIn('id', PlantillaResource::propiedadesGestionables())->findOrFail((int) $data['propiedad']);
                    Notification::make()->success()->title(__('mensajes.plantillas.envios_activados', ['n' => PlantillasBase::prenderRecomendados($propiedad)]))->send();
                }),
            CreateAction::make()->visible(fn (): bool => PlantillaResource::canCreate()),
        ];
    }
}
