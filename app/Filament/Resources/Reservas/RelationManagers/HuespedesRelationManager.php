<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\Reservas\RelationManagers;

use App\Enums\TipoReserva;
use App\Filament\Resources\Reservas\ReservaResource;
use App\Models\Huesped;
use App\Models\Reserva;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Quiénes se alojan en una reserva propia: el titular y sus acompañantes.
 */
class HuespedesRelationManager extends RelationManager
{
    protected static string $relationship = 'huespedes';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Reserva && ! $ownerRecord->sincronizada && $ownerRecord->tipo === TipoReserva::Reserva;
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('ui.reservas.acompanantes');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->paginated(false)
            ->columns([
                TextColumn::make('nombre')->label(__('ui.huespedes.nombre'))
                    ->formatStateUsing(fn (Huesped $record): string => $record->nombreCompleto()),
                TextColumn::make('parentesco_texto')->label(__('ui.huespedes.parentesco'))->placeholder('—'),
                TextColumn::make('titular')->label('')
                    ->state(fn (Huesped $record): ?string => $record->id === $this->reserva()->titular_id ? __('ui.huespedes.titular') : null)->badge(),
            ])
            ->headerActions([
                Action::make('anadirAcompanante')->label(__('ui.huespedes.anadir_acompanante'))->icon(Heroicon::OutlinedUserPlus)
                    ->visible(fn (): bool => $this->puedeEscribir())
                    ->schema([
                        Select::make('huesped_id')->label(__('ui.comun.huesped'))->helperText(__('ui.huespedes.acompanante_ayuda'))->searchable()->native(false)
                            ->getSearchResultsUsing(fn (string $search): array => Huesped::query()->where('propiedad_id', $this->reserva()->propiedad_id)
                                ->where(fn (Builder $q): Builder => $q->where('nombre', 'like', '%'.$search.'%')->orWhere('apellido1', 'like', '%'.$search.'%'))
                                ->limit(30)->get()->mapWithKeys(fn (Huesped $h): array => [$h->id => $h->nombreCompleto()])->all())
                            ->getOptionLabelUsing(fn (mixed $value): ?string => Huesped::query()->where('propiedad_id', $this->reserva()->propiedad_id)->find((int) $value)?->nombreCompleto()),
                        TextInput::make('nombre')->label(__('ui.huespedes.nombre_nuevo'))->maxLength(100),
                        TextInput::make('apellido1')->label(__('ui.huespedes.apellido1'))->maxLength(100),
                        TextInput::make('parentesco')->label(__('ui.huespedes.parentesco'))->maxLength(70),
                    ])
                    ->action(function (array $data): void {
                        abort_unless($this->puedeEscribir(), 403);
                        $reserva = $this->reserva();
                        $huesped = filled($data['huesped_id'] ?? null)
                            ? Huesped::query()->where('propiedad_id', $reserva->propiedad_id)->findOrFail((int) $data['huesped_id'])
                            : (filled($data['nombre'] ?? null) ? Huesped::query()->create(['propiedad_id' => $reserva->propiedad_id, 'nombre' => $data['nombre'], 'apellido1' => $data['apellido1'] ?? null]) : null);
                        if ($huesped === null) {
                            Notification::make()->warning()->title(__('ui.huespedes.acompanante_falta'))->send();

                            return;
                        }
                        $reserva->huespedes()->syncWithoutDetaching([$huesped->id => [
                            'orden' => (int) $reserva->huespedes()->max('orden') + 1,
                            'parentesco_texto' => filled($data['parentesco'] ?? null) ? (string) $data['parentesco'] : null,
                        ]]);
                        Notification::make()->success()->title(__('ui.huespedes.acompanante_ok'))->send();
                    }),
            ])
            ->recordActions([
                Action::make('quitarAcompanante')->label(__('ui.pagos.quitar'))->icon(Heroicon::OutlinedTrash)->color('danger')->requiresConfirmation()
                    ->visible(fn (Huesped $record): bool => $this->puedeEscribir() && $record->id !== $this->reserva()->titular_id)
                    ->action(function (Huesped $record): void {
                        abort_unless($this->puedeEscribir(), 403);
                        $this->reserva()->huespedes()->detach($record->id);
                    }),
            ]);
    }

    private function reserva(): Reserva
    {
        /** @var Reserva $reserva */
        $reserva = $this->getOwnerRecord();

        return $reserva;
    }

    private function puedeEscribir(): bool
    {
        return in_array($this->reserva()->propiedad_id, ReservaResource::propiedadesGestionables(), true);
    }
}
