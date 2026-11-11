<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Filament\Resources\Opiniones\Pages;

use App\Comunicacion\Opiniones;
use App\Filament\Resources\Automatizaciones\AutomatizacionResource;
use App\Filament\Resources\Opiniones\OpinionResource;
use App\Models\Propiedad;
use App\Support\Ajustes;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class ListOpiniones extends ListRecords
{
    protected static string $resource = OpinionResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        $total = OpinionResource::getEloquentQuery()->count();

        return $total === 0 ? null : __('operacion.opiniones.promedio', [
            'promedio' => number_format((float) OpinionResource::getEloquentQuery()->avg('calificacion'), 1),
            'total' => $total,
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('preparar')
                ->label(__('operacion.opiniones.preparar'))
                ->icon(Heroicon::OutlinedSparkles)
                ->modalDescription(__('operacion.opiniones.preparar_ayuda'))
                ->schema([
                    Select::make('propiedad')->label(__('ui.comun.propiedad'))->required()->native(false)->live()
                        ->options(fn (): array => Propiedad::query()->whereIn('id', OpinionResource::propiedadesGestionables())->orderBy('nombre')->pluck('nombre', 'id')->all())
                        ->afterStateUpdated(fn (mixed $state, Set $set) => $set('enlace', Opiniones::enlacePublicoDe((int) $state))),
                    TextInput::make('enlace')->label(__('operacion.opiniones.enlace_publico'))->helperText(__('operacion.opiniones.enlace_publico_ayuda'))->url()->maxLength(255),
                ])
                ->action(function (array $data): void {
                    $propiedad = Propiedad::query()->whereIn('id', OpinionResource::propiedadesGestionables())->findOrFail((int) $data['propiedad']);
                    Ajustes::guardar('opiniones.enlace.'.$propiedad->id, (string) ($data['enlace'] ?? ''));
                    $automatizacion = app(Opiniones::class)->prepararMensaje($propiedad);
                    Notification::make()->success()->persistent()->title(__('operacion.opiniones.preparado'))->body(__('operacion.opiniones.preparado_ayuda'))
                        ->actions([Action::make('revisar')->label(__('operacion.opiniones.revisar'))->url(AutomatizacionResource::getUrl('edit', ['record' => $automatizacion]))])
                        ->send();
                }),
        ];
    }
}
