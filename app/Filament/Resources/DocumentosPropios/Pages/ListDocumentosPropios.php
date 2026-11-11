<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Filament\Resources\DocumentosPropios\Pages;

use App\Documentos\DocumentosDePartida;
use App\Filament\Resources\DocumentosPropios\DocumentoPropioResource;
use App\Models\Propiedad;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListDocumentosPropios extends ListRecords
{
    protected static string $resource = DocumentoPropioResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('dePartida')->label(__('documentos_propios.de_partida'))->icon(Heroicon::OutlinedSparkles)->color('gray')
                ->visible(fn (): bool => DocumentoPropioResource::canCreate())
                ->modalDescription(__('documentos_propios.de_partida_ayuda'))
                ->fillForm(fn (): array => ['propiedad' => count(DocumentoPropioResource::propiedadesGestionables()) === 1 ? DocumentoPropioResource::propiedadesGestionables()[0] : null])
                ->schema([
                    Select::make('propiedad')->label(__('ui.comun.propiedad'))->required()->native(false)
                        ->options(fn (): array => Propiedad::query()->whereIn('id', DocumentoPropioResource::propiedadesGestionables())->orderBy('nombre')->pluck('nombre', 'id')->all()),
                ])
                ->action(function (array $data): void {
                    DocumentosDePartida::sembrar(Propiedad::query()->whereIn('id', DocumentoPropioResource::propiedadesGestionables())->findOrFail((int) $data['propiedad']));
                    Notification::make()->success()->title(__('documentos_propios.de_partida_listo'))->send();
                }),
            CreateAction::make()->visible(fn (): bool => DocumentoPropioResource::canCreate()),
        ];
    }
}
