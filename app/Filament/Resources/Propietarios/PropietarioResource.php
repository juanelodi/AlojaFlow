<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Filament\Resources\Propietarios;

use App\Filament\Campos\CampoDinero;
use App\Filament\Resources\Base\RecursoDePropiedad;
use App\Filament\Resources\Propietarios\Pages\CreatePropietario;
use App\Filament\Resources\Propietarios\Pages\EditPropietario;
use App\Filament\Resources\Propietarios\Pages\ListPropietarios;
use App\Filament\Resources\Propietarios\RelationManagers\UnidadesRelationManager;
use App\Models\Propietario;
use App\Modulos\Modulo;
use App\Seguridad\Acceso;
use App\Support\Formato;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
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
use Illuminate\Validation\Rules\Unique;

/**
 * Los dueños de lo que se administra, con su acuerdo (D-114).
 */
class PropietarioResource extends RecursoDePropiedad
{
    protected static ?string $model = Propietario::class;

    protected static ?string $slug = 'propietarios';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?int $navigationSort = 9;

    protected static function modulo(): ?Modulo
    {
        return Modulo::Propietarios;
    }

    protected static function permiso(): string
    {
        return Acceso::CATALOGO;
    }

    public static function getNavigationGroup(): ?string
    {
        return __('ui.nav.dinero');
    }

    public static function getModelLabel(): string
    {
        return __('propietarios.modelo');
    }

    public static function getPluralModelLabel(): string
    {
        return __('propietarios.modelos');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columnSpanFull()->columns(['default' => 1, 'md' => 2])->schema([
                self::campoPropiedad(),
                TextInput::make('nombre')->label(__('propietarios.campos.nombre'))->required()->maxLength(200)
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('propiedad_id', self::propiedadDe($get))),
                TextInput::make('correo')->label(__('propietarios.campos.correo'))->email()->maxLength(150),
                TextInput::make('telefono')->label(__('propietarios.campos.telefono'))->tel()->maxLength(50),
                TextInput::make('identificador_fiscal')->label(__('propietarios.campos.identificador'))->maxLength(30),
                Select::make('idioma')->label(__('propietarios.campos.idioma'))->native(false)->options(Formato::idiomas()),
            ]),
            Section::make(__('propietarios.campos.acuerdo'))->description(__('propietarios.campos.acuerdo_ayuda'))->columnSpanFull()->columns(['default' => 1, 'md' => 2])->schema([
                TextInput::make('comision_porcentaje')->label(__('propietarios.campos.comision'))->helperText(__('propietarios.campos.comision_ayuda'))->numeric()->minValue(0)->maxValue(100)->step('any')->suffix('%')->default(0)->required(),
                CampoDinero::make('cargo_fijo')->label(__('propietarios.campos.cargo_fijo'))->helperText(__('propietarios.campos.cargo_fijo_ayuda'))->default(0),
                Toggle::make('comision_sobre_neto')->label(__('propietarios.campos.sobre_neto'))->helperText(__('propietarios.campos.sobre_neto_ayuda'))->default(true),
                Toggle::make('puede_bloquear')->label(__('propietarios.campos.puede_bloquear'))->helperText(__('propietarios.campos.puede_bloquear_ayuda'))->default(true),
                Toggle::make('activo')->label(__('propietarios.campos.activo'))->default(true),
                Textarea::make('notas')->label(__('propietarios.campos.notas'))->rows(3)->maxLength(5000)->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('propiedad')->withCount('unidades'))
            ->defaultSort('nombre')
            ->columns([
                TextColumn::make('nombre')->label(__('propietarios.campos.nombre'))->searchable()->sortable(),
                self::columnaPropiedad(),
                TextColumn::make('unidades_count')->label(__('propietarios.unidades.titulo'))->alignEnd(),
                TextColumn::make('comision_porcentaje')->label(__('propietarios.campos.comision'))->alignEnd()->formatStateUsing(fn (Propietario $record): string => Formato::tasa($record->comision_porcentaje)),
                IconColumn::make('acceso_activo')->label(__('propietarios.acceso.columna'))->boolean(),
                IconColumn::make('activo')->label(__('propietarios.campos.activo'))->boolean(),
            ])
            ->emptyStateHeading(__('propietarios.vacio'))
            ->emptyStateDescription(__('propietarios.vacio_ayuda'))
            ->recordActions([EditAction::make()->visible(fn (Propietario $record): bool => self::canEdit($record))]);
    }

    public static function getRelations(): array
    {
        return [UnidadesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPropietarios::route('/'),
            'create' => CreatePropietario::route('/create'),
            'edit' => EditPropietario::route('/{record}/edit'),
        ];
    }
}
