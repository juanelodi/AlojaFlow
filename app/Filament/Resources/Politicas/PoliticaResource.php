<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\Politicas;

use App\Dinero\GestorPoliticas;
use App\Filament\Resources\Base\RecursoDeCualquierPropiedad;
use App\Filament\Resources\Politicas\Pages\CreatePolitica;
use App\Filament\Resources\Politicas\Pages\EditPolitica;
use App\Filament\Resources\Politicas\Pages\ListPoliticas;
use App\Models\Politica;
use App\Seguridad\Acceso;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Cuánto cuesta cancelar o no llegar, y qué anticipo se pide.
 */
class PoliticaResource extends RecursoDeCualquierPropiedad
{
    protected static ?string $model = Politica::class;

    protected static ?string $slug = 'politicas';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static ?int $navigationSort = 10;

    protected static function permiso(): string
    {
        return Acceso::AJUSTES;
    }

    public static function getNavigationGroup(): ?string
    {
        return __('ui.nav.dinero');
    }

    public static function getModelLabel(): string
    {
        return __('dinero.politicas.modelo');
    }

    public static function getPluralModelLabel(): string
    {
        return __('dinero.politicas.modelos');
    }

    public static function form(Schema $schema): Schema
    {
        $porcentaje = fn (string $campo, string $etiqueta): TextInput => TextInput::make($campo)->label(__($etiqueta))->numeric()->minValue(0)->maxValue(100)->step('any')->default(0)->suffix('%')->required();

        return $schema->components([
            Section::make()->columnSpanFull()->columns(['default' => 1, 'md' => 2])->schema([
                self::campoPropiedad(),
                TextInput::make('nombre')->label(__('dinero.politicas.nombre'))->required()->maxLength(100),
                Toggle::make('no_reembolsable')->label(__('dinero.politicas.no_reembolsable'))->helperText(__('dinero.politicas.no_reembolsable_ayuda'))->live()->columnSpanFull(),
                TextInput::make('dias_cancelacion_gratuita')->label(__('dinero.politicas.dias_gratis'))->integer()->minValue(0)->maxValue(365)->default(0)->required()
                    ->visible(fn (Get $get): bool => ! $get('no_reembolsable')),
                $porcentaje('penalizacion_porcentaje', 'dinero.politicas.penalizacion')->visible(fn (Get $get): bool => ! $get('no_reembolsable')),
                $porcentaje('no_show_porcentaje', 'dinero.politicas.no_show'),
                $porcentaje('deposito_porcentaje', 'dinero.politicas.deposito'),
                TextInput::make('deposito_plazo_dias')->label(__('dinero.politicas.deposito_plazo'))->integer()->minValue(0)->maxValue(365)->default(0)->required(),
                Toggle::make('predeterminada')->label(__('dinero.politicas.predeterminada'))->helperText(__('dinero.politicas.predeterminada_ayuda')),
                Textarea::make('texto')->label(__('dinero.politicas.texto'))->rows(4)->maxLength(5000)->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('propiedad'))
            ->defaultSort('nombre')
            ->columns([
                TextColumn::make('nombre')->label(__('dinero.politicas.nombre'))->searchable(),
                self::columnaPropiedad(),
                TextColumn::make('dias_cancelacion_gratuita')->label(__('dinero.politicas.dias_gratis'))->alignEnd(),
                TextColumn::make('penalizacion_porcentaje')->label(__('dinero.politicas.penalizacion'))->suffix(' %')->alignEnd(),
                TextColumn::make('no_show_porcentaje')->label(__('dinero.politicas.no_show'))->suffix(' %')->alignEnd(),
                IconColumn::make('predeterminada')->label(__('dinero.politicas.predeterminada'))->boolean(),
            ])
            ->emptyStateHeading(__('dinero.politicas.vacio'))
            ->recordActions([
                Action::make('textoPolitica')->label(__('operacion.politicas.ver_texto'))->icon(Heroicon::OutlinedDocumentText)->color('gray')
                    ->modalHeading(fn (Politica $record): string => $record->nombre)
                    ->modalSubmitAction(false)
                    ->fillForm(fn (Politica $record): array => ['texto' => app(GestorPoliticas::class)->textoDe($record)])
                    ->schema([Textarea::make('texto')->label(__('operacion.politicas.texto_para_copiar'))->rows(8)->readOnly()]),
                EditAction::make()->visible(fn (Politica $record): bool => self::canEdit($record)),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPoliticas::route('/'),
            'create' => CreatePolitica::route('/create'),
            'edit' => EditPolitica::route('/{record}/edit'),
        ];
    }
}
