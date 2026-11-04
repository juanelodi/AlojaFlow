<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\Huespedes\Pages;

use App\Filament\Resources\Huespedes\HuespedResource;
use App\Models\Huesped;
use App\Reservas\FusionHuespedes;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
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
            Action::make('fusionar')
                ->label(__('ui.huespedes.fusionar'))
                ->icon(Heroicon::OutlinedArrowsPointingIn)
                ->color('gray')
                ->visible(fn (Huesped $record): bool => HuespedResource::canEdit($record))
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
