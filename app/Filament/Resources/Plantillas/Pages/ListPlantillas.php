<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\Plantillas\Pages;

use App\Comunicacion\PlantillasBase;
use App\Filament\Resources\Plantillas\PlantillaResource;
use App\Models\Propiedad;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListPlantillas extends ListRecords
{
    protected static string $resource = PlantillaResource::class;

    protected function getHeaderActions(): array
    {
        return [
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
            CreateAction::make()->visible(fn (): bool => PlantillaResource::canCreate()),
        ];
    }
}
