<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Filament\Resources\Propiedades\Pages;

use App\Demo\GeneradorDemo;
use App\Demo\ModoDemo;
use App\Filament\Resources\Propiedades\PropiedadResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListPropiedades extends ListRecords
{
    protected static string $resource = PropiedadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('crearDemo')
                ->label(__('ui.demo.crear'))
                ->icon(Heroicon::OutlinedBeaker)
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading(__('ui.demo.crear'))
                ->modalDescription(__('ui.demo.crear_ayuda'))
                ->visible(fn (): bool => GeneradorDemo::existente() === null && ! ModoDemo::activo())
                ->action(function (): void {
                    if (function_exists('set_time_limit')) {
                        @set_time_limit(300);
                    }
                    $propiedad = app(GeneradorDemo::class)->crear();
                    Notification::make()->success()->title(__('ui.demo.creada', [
                        'nombre' => $propiedad->nombre, 'reservas' => $propiedad->reservas()->count(),
                    ]))->send();
                }),
            Action::make('quitarDemo')
                ->label(__('ui.demo.quitar'))
                ->icon(Heroicon::OutlinedTrash)
                ->color('danger')
                ->outlined()
                ->requiresConfirmation()
                ->modalHeading(__('ui.demo.quitar'))
                ->modalDescription(__('ui.demo.quitar_ayuda'))
                ->visible(fn (): bool => GeneradorDemo::existente() !== null && ! ModoDemo::activo())
                ->action(function (): void {
                    app(GeneradorDemo::class)->eliminar();
                    Notification::make()->success()->title(__('ui.demo.quitada'))->send();
                }),
            CreateAction::make()->visible(fn (): bool => PropiedadResource::canCreate()),
        ];
    }
}
