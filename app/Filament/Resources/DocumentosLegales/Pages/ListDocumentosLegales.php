<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Filament\Resources\DocumentosLegales\Pages;

use App\Filament\Resources\DocumentosLegales\DocumentoLegalResource;
use App\Llegada\GestorDocumentosLegales;
use App\Models\Propiedad;
use App\Paises\Paises;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListDocumentosLegales extends ListRecords
{
    protected static string $resource = DocumentoLegalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('crearBase')
                ->label(__('llegada.documentos.crear_base'))
                ->icon(Heroicon::OutlinedSparkles)
                ->color('gray')
                ->modalDescription(__('llegada.documentos.revisar_abogado'))
                ->visible(fn (): bool => DocumentoLegalResource::canCreate())
                ->schema([Select::make('propiedad')->label(__('ui.comun.propiedad'))->required()->native(false)
                    ->options(fn (): array => Propiedad::query()->whereIn('id', DocumentoLegalResource::propiedadesGestionables())->orderBy('nombre')->pluck('nombre', 'id')->all())
                    ->default(fn (): ?int => DocumentoLegalResource::propiedadesGestionables()[0] ?? null)])
                ->action(function (array $data, GestorDocumentosLegales $documentos): void {
                    $propiedad = Propiedad::query()->whereIn('id', DocumentoLegalResource::propiedadesGestionables())->findOrFail((int) $data['propiedad']);
                    $aviso = Notification::make()->success()->title(__('llegada.documentos.creados', ['n' => $documentos->crearBase($propiedad)]));
                    if (! Paises::de($propiedad)->legalRevisado()) {
                        $aviso->body(__('llegada.documentos.sin_revisar_pais'));
                    }
                    $aviso->send();
                }),
            CreateAction::make()->visible(fn (): bool => DocumentoLegalResource::canCreate()),
        ];
    }
}
