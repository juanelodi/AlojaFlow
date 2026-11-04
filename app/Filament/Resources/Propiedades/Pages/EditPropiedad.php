<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\Propiedades\Pages;

use App\Copias\ErrorCopia;
use App\Filament\Resources\Propiedades\AccionesSincronizacion;
use App\Filament\Resources\Propiedades\PropiedadResource;
use App\Models\Propiedad;
use App\Models\RegistroAuditoria;
use App\Propiedades\ErrorIndependizar;
use App\Propiedades\Independizador;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditPropiedad extends EditRecord
{
    protected static string $resource = PropiedadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            AccionesSincronizacion::probar(),
            AccionesSincronizacion::sincronizar(),
            AccionesSincronizacion::reanudar(),
            Action::make('independizar')
                ->label(__('ui.independizar.accion'))
                ->icon(Heroicon::OutlinedArrowRightStartOnRectangle)
                ->color('warning')
                ->visible(fn (Propiedad $record): bool => ! $record->esIndependiente())
                ->modalHeading(__('ui.independizar.titulo'))
                ->modalDescription(fn (Propiedad $record): string => __('ui.independizar.descripcion', ['codigo' => $record->codigo]))
                ->modalSubmitActionLabel(__('ui.independizar.accion'))
                ->schema(fn (Propiedad $record): array => [
                    TextInput::make('confirmacion')->label(__('ui.propiedades.eliminar_confirmacion'))->required()->in([$record->codigo])->autocomplete('off'),
                ])
                ->action(function (Propiedad $record, Independizador $independizador): void {
                    if (function_exists('set_time_limit')) {
                        @set_time_limit(300);
                    }
                    try {
                        $reservas = $independizador->independizar($record);
                    } catch (ErrorIndependizar|ErrorCopia $e) {
                        Notification::make()->danger()->persistent()->title(__('ui.independizar.no_se_pudo'))->body($e->getMessage())->send();

                        return;
                    }
                    Notification::make()->success()->persistent()->title(__('ui.independizar.listo', ['reservas' => $reservas]))->body(__('ui.independizar.siguiente'))->send();
                    $this->redirect(static::getUrl(['record' => $record]));
                }),
            // En una independiente estos son los únicos datos: se pide escribir el código para borrarla.
            DeleteAction::make()
                ->modalDescription(fn (Propiedad $record): string => $record->esIndependiente()
                    ? __('ui.propiedades.eliminar_independiente', ['codigo' => $record->codigo])
                    : __('ui.propiedades.eliminar_subordinada'))
                ->schema(fn (Propiedad $record): array => $record->esIndependiente() ? [
                    TextInput::make('confirmacion')->label(__('ui.propiedades.eliminar_confirmacion'))->required()->in([$record->codigo])->autocomplete('off'),
                ] : [])
                ->before(fn (Propiedad $record) => RegistroAuditoria::anotar('propiedad.eliminar', $record, ['nombre' => $record->nombre, 'modo' => $record->modo->value])),
        ];
    }

    protected function afterSave(): void
    {
        // Sin valores: nunca se anotan credenciales (regla 13).
        RegistroAuditoria::anotar('propiedad.editar', $this->record, ['campos' => array_keys($this->record->getChanges())], (int) $this->record->getKey());
    }
}
