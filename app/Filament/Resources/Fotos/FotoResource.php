<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Filament\Resources\Fotos;

use App\Filament\Resources\Base\RecursoDePropiedad;
use App\Filament\Resources\Fotos\Pages\ListFotos;
use App\Models\Foto;
use App\Modulos\Modulo;
use App\Publico\GestorFotos;
use App\Seguridad\Acceso;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Las fotos de la página pública: de la propiedad y de cada tarifa (D-104).
 */
class FotoResource extends RecursoDePropiedad
{
    protected static ?string $model = Foto::class;

    protected static ?string $slug = 'fotos';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static ?int $navigationSort = 7;

    protected static function modulo(): ?Modulo
    {
        return Modulo::Motor;
    }

    protected static function permiso(): string
    {
        return Acceso::CATALOGO;
    }

    public static function getModelLabel(): string
    {
        return __('portal.fotos.modelo');
    }

    public static function getPluralModelLabel(): string
    {
        return __('portal.fotos.titulo');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['tarifa', 'propiedad']))
            ->defaultSort('orden')
            ->reorderable('orden')
            ->columns([
                ImageColumn::make('imagen')->label(__('portal.fotos.foto'))->state(fn (Foto $record): string => $record->url('chica'))->imageHeight(64),
                TextColumn::make('tarifa.nombre')->label(__('portal.fotos.de'))->placeholder(__('portal.fotos.de_propiedad')),
                TextColumn::make('texto_alternativo')->label(__('portal.fotos.texto'))->placeholder('—')->wrap(),
                self::columnaPropiedad(),
            ])
            ->filters([SelectFilter::make('tarifa_id')->label(__('portal.fotos.de'))->relationship('tarifa', 'nombre')])
            ->emptyStateHeading(__('portal.fotos.vacio'))
            ->recordActions([
                Action::make('texto')->label(__('portal.fotos.editar_texto'))->icon(Heroicon::OutlinedPencil)->color('gray')
                    ->fillForm(fn (Foto $record): array => ['texto_alternativo' => $record->texto_alternativo])
                    ->schema([TextInput::make('texto_alternativo')->label(__('portal.fotos.texto'))->helperText(__('portal.fotos.texto_ayuda'))->maxLength(200)])
                    ->action(fn (Foto $record, array $data) => $record->update(['texto_alternativo' => $data['texto_alternativo'] ?: null])),
                Action::make('quitar')->label(__('portal.fotos.quitar'))->icon(Heroicon::OutlinedTrash)->color('danger')->requiresConfirmation()
                    ->action(fn (Foto $record, GestorFotos $fotos) => $fotos->eliminar($record)),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListFotos::route('/')];
    }
}
