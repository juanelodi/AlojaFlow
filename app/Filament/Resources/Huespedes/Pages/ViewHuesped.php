<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\Huespedes\Pages;

use App\Enums\EntidadCampo;
use App\Filament\Campos\CamposPropiosPanel;
use App\Filament\Resources\Huespedes\HuespedResource;
use App\Models\Huesped;
use App\Privacidad\GestorPrivacidad;
use App\Reservas\FusionHuespedes;
use App\Seguridad\Acceso;
use App\Seguridad\Limites;
use Filament\Actions\Action;
use Filament\Actions\Action as NotificationAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class ViewHuesped extends ViewRecord
{
    protected static string $resource = HuespedResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()->visible(fn (Huesped $record): bool => HuespedResource::canEdit($record)),
            CamposPropiosPanel::accion(EntidadCampo::Huesped, Acceso::HUESPEDES),
            Action::make('exportarDatos')
                ->label(__('llegada.privacidad.exportar'))
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription(__('llegada.privacidad.exportar_ayuda'))
                ->visible(fn (Huesped $record): bool => Acceso::puede(Acceso::usuarioActual(), Acceso::HUESPEDES, $record->propiedad_id))
                ->action(function (Huesped $record, GestorPrivacidad $privacidad): void {
                    $archivo = $privacidad->exportar($record);
                    Notification::make()->success()->persistent()->title(__('llegada.privacidad.exportado'))
                        ->actions([NotificationAction::make('descargar')->label(__('llegada.privacidad.descargar'))->url(route('archivos.ver', $archivo), shouldOpenInNewTab: true)])->send();
                }),
            Action::make('anonimizar')
                ->label(__('llegada.privacidad.anonimizar'))
                ->icon(Heroicon::OutlinedEyeSlash)
                ->color('danger')
                ->outlined()
                ->modalDescription(fn (Huesped $record): string => $record->sincronizada ? __('llegada.privacidad.anonimizar_ayuda_sub') : __('llegada.privacidad.anonimizar_ayuda'))
                ->visible(fn (Huesped $record): bool => Acceso::puede(Acceso::usuarioActual(), Acceso::HUESPEDES, $record->propiedad_id))
                ->schema([TextInput::make('confirmacion')->label(__('llegada.privacidad.confirmacion'))->required()->in([__('llegada.privacidad.palabra')])->autocomplete('off')])
                ->action(function (Huesped $record, GestorPrivacidad $privacidad): void {
                    $completa = $privacidad->anonimizar($record);
                    Notification::make()->success()->title($completa ? __('llegada.privacidad.anonimizado_ok') : __('llegada.privacidad.anonimizado_sub'))->send();
                    $this->redirect(static::getUrl(['record' => $record]));
                }),
            Action::make('fusionar')
                ->label(__('ui.huespedes.fusionar'))
                ->icon(Heroicon::OutlinedArrowsPointingIn)
                ->color('gray')
                // Fusionar junta dos fichas: quien solo ve las suyas no puede elegir la otra.
                ->visible(fn (Huesped $record): bool => HuespedResource::canEdit($record) && ! in_array($record->propiedad_id, Limites::propiedadesSoloPropias(Acceso::usuarioActual()), true))
                ->modalDescription(__('ui.huespedes.fusionar_ayuda'))
                ->schema([
                    Select::make('otra')->label(__('ui.huespedes.fusionar_otra'))->required()->searchable()->native(false)
                        ->getSearchResultsUsing(fn (string $search, Huesped $record): array => Huesped::query()->where('propiedad_id', $record->propiedad_id)->whereKeyNot($record->id)
                            ->where(fn (Builder $q): Builder => $q->where('nombre', 'like', '%'.$search.'%')->orWhere('apellido1', 'like', '%'.$search.'%'))
                            ->limit(30)->get()->mapWithKeys(fn (Huesped $h): array => [$h->id => $h->nombreCompleto()])->all())
                        ->getOptionLabelUsing(fn (mixed $value, Huesped $record): ?string => Huesped::query()->where('propiedad_id', $record->propiedad_id)->find((int) $value)?->nombreCompleto()),
                ])
                ->action(function (Huesped $record, array $data): void {
                    $otra = Huesped::query()->where('propiedad_id', $record->propiedad_id)->findOrFail((int) $data['otra']);
                    app(FusionHuespedes::class)->fusionar($record, $otra);
                    Notification::make()->success()->title(__('ui.huespedes.fusionada'))->send();
                }),
        ];
    }
}
