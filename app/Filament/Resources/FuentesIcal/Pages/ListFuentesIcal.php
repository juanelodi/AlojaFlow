<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Filament\Resources\FuentesIcal\Pages;

use App\Filament\Resources\FuentesIcal\FuenteIcalResource;
use App\Ical\GestorIcal;
use App\Models\Propiedad;
use App\Models\Unidad;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Support\Icons\Heroicon;

class ListFuentesIcal extends ListRecords
{
    protected static string $resource = FuenteIcalResource::class;

    protected function getHeaderActions(): array
    {
        $campoPropiedad = fn (): Select => Select::make('propiedad')->label(__('ical.exportar.propiedad'))->required()->native(false)->live()
            ->options(fn (): array => Propiedad::query()->whereIn('id', FuenteIcalResource::propiedadesGestionables())->orderBy('nombre')->pluck('nombre', 'id')->all())
            ->default(function (): ?int {
                $permitidas = FuenteIcalResource::propiedadesGestionables();

                return $permitidas[0] ?? null;
            });

        return [
            Action::make('enlaces')
                ->label(__('ical.exportar.accion'))
                ->icon(Heroicon::OutlinedLink)
                ->color('gray')
                ->modalWidth('3xl')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel(__('ui.comun.cerrar'))
                ->schema([
                    $campoPropiedad(),
                    View::make('filament.modales.enlaces-ical')->viewData(['enlaces' => []])
                        ->viewData(fn (Get $get): array => ['enlaces' => $this->enlacesDe((int) $get('propiedad'))]),
                ]),
            Action::make('regenerar')
                ->label(__('ical.exportar.regenerar'))
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->modalDescription(__('ical.exportar.regenerar_ayuda'))
                ->schema([$campoPropiedad()])
                ->action(function (array $data, GestorIcal $ical): void {
                    $ical->regenerarEnlaces(Propiedad::query()->whereIn('id', FuenteIcalResource::propiedadesGestionables())->findOrFail((int) $data['propiedad']));
                    Notification::make()->success()->title(__('ical.exportar.regenerados'))->send();
                }),
            CreateAction::make()->visible(fn (): bool => FuenteIcalResource::canCreate()),
        ];
    }

    /**
     * @return list<array{unidad: string, url: string}>
     */
    private function enlacesDe(int $propiedadId): array
    {
        if (! in_array($propiedadId, FuenteIcalResource::propiedadesGestionables(), true)) {
            return [];
        }
        $ical = app(GestorIcal::class);
        $enlaces = [];
        foreach (Unidad::query()->where('propiedad_id', $propiedadId)->where('activa', true)->orderBy('orden')->orderBy('nombre')->get() as $unidad) {
            $enlaces[] = ['unidad' => $unidad->nombre, 'url' => route('ical.exportar', ['token' => $ical->calendarioDe($unidad)->enlace->token()])];
        }

        return $enlaces;
    }
}
