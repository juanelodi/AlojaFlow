<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\Llegadas\Pages;

use App\Filament\Resources\Llegadas\RegistroLlegadaResource;
use App\Models\Propiedad;
use App\Seguridad\Acceso;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListLlegadas extends ListRecords
{
    protected static string $resource = RegistroLlegadaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ajustes')
                ->label(__('llegada.panel.ajustes'))
                ->icon(Heroicon::OutlinedCog6Tooth)
                ->color('gray')
                ->visible(fn (): bool => Acceso::propiedades(Acceso::usuarioActual(), Acceso::AJUSTES) !== [])
                ->schema([
                    Select::make('propiedad')->label(__('ui.comun.propiedad'))->required()->native(false)
                        ->options(fn (): array => Propiedad::query()->whereIn('id', Acceso::propiedades(Acceso::usuarioActual(), Acceso::AJUSTES))->orderBy('nombre')->pluck('nombre', 'id')->all())
                        ->default(fn (): ?int => Acceso::propiedades(Acceso::usuarioActual(), Acceso::AJUSTES)[0] ?? null),
                    Toggle::make('pedir_foto')->label(__('llegada.panel.pedir_foto'))->default(true),
                    Toggle::make('otp')->label(__('llegada.panel.otp'))->helperText(__('llegada.panel.otp_ayuda')),
                ])
                ->action(function (array $data): void {
                    $propiedad = Propiedad::query()->whereIn('id', Acceso::propiedades(Acceso::usuarioActual(), Acceso::AJUSTES))->findOrFail((int) $data['propiedad']);
                    $ajustes = (array) $propiedad->ajustes;
                    $ajustes['llegada'] = ['pedir_foto' => (bool) ($data['pedir_foto'] ?? true), 'otp' => (bool) ($data['otp'] ?? false)];
                    $propiedad->update(['ajustes' => $ajustes]);
                    Notification::make()->success()->title(__('llegada.panel.guardado'))->send();
                }),
        ];
    }
}
