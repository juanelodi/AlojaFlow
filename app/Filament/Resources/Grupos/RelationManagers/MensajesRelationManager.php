<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\Grupos\RelationManagers;

use App\Models\Grupo;
use App\Models\MensajeGrupo;
use App\Support\Formato;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Lo que se escriben con el coordinador. Él lo lee en su página: no se manda ningún correo.
 */
class MensajesRelationManager extends RelationManager
{
    protected static string $relationship = 'mensajes';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('grupos.mensajes.titulo');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->paginated([25])
            ->columns([
                TextColumn::make('creado_en')->label(__('grupos.mensajes.cuando'))->formatStateUsing(fn (MensajeGrupo $record): string => Formato::momento($record->creado_en)),
                TextColumn::make('quien')->label(__('grupos.mensajes.quien'))
                    ->state(fn (MensajeGrupo $record): string => $record->del_coordinador ? __('ui.grupos.coordinador') : ($record->usuario->name ?? '—')),
                TextColumn::make('texto')->label(__('grupos.mensajes.texto'))->wrap(),
            ])
            ->emptyStateHeading(__('grupos.mensajes.vacio'))
            ->headerActions([
                Action::make('escribir')
                    ->label(__('grupos.mensajes.mandar'))
                    ->icon(Heroicon::OutlinedChatBubbleLeftEllipsis)
                    ->schema([Textarea::make('texto')->label(__('grupos.mensajes.texto'))->required()->rows(4)->maxLength(2000)])
                    ->action(function (array $data): void {
                        /** @var Grupo $grupo */
                        $grupo = $this->getOwnerRecord();
                        MensajeGrupo::query()->create(['grupo_id' => $grupo->id, 'del_coordinador' => false, 'texto' => trim((string) $data['texto']), 'user_id' => auth()->id()]);
                    }),
            ]);
    }
}
