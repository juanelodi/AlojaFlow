<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Filament\Resources\Reservas\RelationManagers;

use App\Filament\Resources\Reservas\ReservaResource;
use App\Models\NotaReserva;
use App\Models\Reserva;
use App\Support\Formato;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Notas internas del personal. Es dato propio: también se puede usar en reservas subordinadas,
 * porque la sincronización no las toca (modelo-datos.md §4.4).
 */
class NotasRelationManager extends RelationManager
{
    protected static string $relationship = 'notas';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('ui.notas.titulo');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('creado_en', 'desc')
            ->paginated(false)
            ->columns([
                TextColumn::make('texto')->label(__('ui.notas.texto'))->wrap(),
                TextColumn::make('usuario.name')->label(__('ui.notas.autor'))->placeholder('—'),
                TextColumn::make('creado_en')->label(__('ui.pagos.fecha'))
                    ->formatStateUsing(fn (NotaReserva $record): string => Formato::momento($record->getAttribute('creado_en'))),
            ])
            ->emptyStateHeading(__('ui.notas.vacio'))
            ->headerActions([
                Action::make('anadirNota')->label(__('ui.notas.anadir'))->icon(Heroicon::OutlinedChatBubbleBottomCenterText)
                    ->visible(fn (): bool => $this->puedeEscribir())
                    ->schema([Textarea::make('texto')->label(__('ui.notas.texto'))->required()->rows(3)->maxLength(5000)])
                    ->action(function (array $data): void {
                        abort_unless($this->puedeEscribir(), 403);
                        NotaReserva::query()->create(['reserva_id' => $this->getOwnerRecord()->getKey(), 'user_id' => auth()->id(), 'texto' => (string) $data['texto']]);
                        Notification::make()->success()->title(__('ui.notas.guardada'))->send();
                    }),
            ])
            ->recordActions([
                Action::make('quitarNota')->label(__('ui.pagos.quitar'))->icon(Heroicon::OutlinedTrash)->color('danger')->requiresConfirmation()
                    ->visible(fn (NotaReserva $record): bool => $this->puedeEscribir() && $record->user_id === auth()->id())
                    ->action(fn (NotaReserva $record) => $record->delete()),
            ]);
    }

    private function puedeEscribir(): bool
    {
        /** @var Reserva $reserva */
        $reserva = $this->getOwnerRecord();

        return in_array($reserva->propiedad_id, ReservaResource::propiedadesPermitidas(), true);
    }
}
