<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Filament\Resources\Empresas;

use App\Enums\EstadoReserva;
use App\Enums\TipoEmpresa;
use App\Filament\Resources\Base\RecursoDePropiedad;
use App\Filament\Resources\Empresas\Pages\CreateEmpresa;
use App\Filament\Resources\Empresas\Pages\EditEmpresa;
use App\Filament\Resources\Empresas\Pages\ListEmpresas;
use App\Filament\Resources\Empresas\RelationManagers\ReservasRelationManager;
use App\Models\Empresa;
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
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Unique;

/**
 * Empresas y agencias que reservan o pagan por sus huéspedes (D-081).
 */
class EmpresaResource extends RecursoDePropiedad
{
    protected static ?string $model = Empresa::class;

    protected static ?string $slug = 'empresas';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?int $navigationSort = 6;

    protected static function modulo(): ?Modulo
    {
        return Modulo::Empresas;
    }

    protected static function permiso(): string
    {
        return Acceso::RESERVAS;
    }

    public static function getNavigationGroup(): ?string
    {
        return __('ui.nav.reservas');
    }

    public static function getModelLabel(): string
    {
        return __('caja.empresas.modelo');
    }

    public static function getPluralModelLabel(): string
    {
        return __('caja.empresas.modelos');
    }

    /**
     * Con reservas no se borra: se perdería quién las debe. Se desactiva.
     */
    public static function canDelete(Model $record): bool
    {
        return parent::canDelete($record) && $record instanceof Empresa && $record->reservas()->doesntExist();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columnSpanFull()->columns(['default' => 1, 'md' => 2])->schema([
                self::campoPropiedad(),
                Select::make('tipo')->label(__('caja.empresas.tipo'))->options(TipoEmpresa::opciones())->required()->native(false)->default(TipoEmpresa::Empresa->value),
                TextInput::make('nombre')->label(__('caja.empresas.nombre'))->required()->maxLength(150)
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('propiedad_id', self::propiedadDe($get))),
                TextInput::make('identificador_fiscal')->label(__('caja.empresas.identificador'))->helperText(__('caja.empresas.identificador_ayuda'))->maxLength(30),
                TextInput::make('contacto')->label(__('caja.empresas.contacto'))->maxLength(150),
                TextInput::make('correo')->label(__('caja.empresas.correo'))->email()->maxLength(150),
                TextInput::make('telefono')->label(__('caja.empresas.telefono'))->tel()->maxLength(40),
                TextInput::make('comision_porcentaje')->label(__('caja.empresas.comision'))->helperText(__('caja.empresas.comision_ayuda'))->numeric()->minValue(0)->maxValue(100)->step('any')->suffix('%'),
                TextInput::make('dias_credito')->label(__('caja.empresas.dias_credito'))->helperText(__('caja.empresas.dias_credito_ayuda'))->numeric()->minValue(0)->maxValue(365),
                Toggle::make('activa')->label(__('ui.tarifas.activa'))->default(true),
                Textarea::make('condiciones')->label(__('caja.empresas.condiciones'))->columnSpanFull()->maxLength(2000),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('propiedad')
                ->withSum(['reservas as saldo' => fn (Builder $q): Builder => $q->where('estado', '!=', EstadoReserva::Cancelada->value)], 'pendiente'))
            ->defaultSort('nombre')
            ->columns([
                TextColumn::make('nombre')->label(__('caja.empresas.nombre'))->searchable(),
                self::columnaPropiedad(),
                TextColumn::make('tipo')->label(__('caja.empresas.tipo'))->badge()->color('gray')
                    ->formatStateUsing(fn (Empresa $record): string => $record->tipo->etiqueta()),
                TextColumn::make('identificador_fiscal')->label(__('caja.empresas.identificador'))->placeholder('—')->searchable(),
                TextColumn::make('contacto')->label(__('caja.empresas.contacto'))->placeholder('—')->toggleable(),
                TextColumn::make('saldo')->label(__('caja.empresas.saldo'))->alignEnd()
                    ->state(fn (Empresa $record): string => Formato::dinero((int) $record->getAttribute('saldo'), $record->propiedad->moneda)),
                IconColumn::make('activa')->label(__('ui.tarifas.activa'))->boolean(),
            ])
            ->filters([SelectFilter::make('tipo')->label(__('caja.empresas.tipo'))->options(TipoEmpresa::opciones())])
            ->emptyStateHeading(__('caja.empresas.vacio'))
            ->emptyStateDescription(__('caja.empresas.vacio_ayuda'))
            ->recordActions([EditAction::make()->visible(fn (Empresa $record): bool => self::canEdit($record))]);
    }

    public static function getRelations(): array
    {
        return [ReservasRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmpresas::route('/'),
            'create' => CreateEmpresa::route('/create'),
            'edit' => EditEmpresa::route('/{record}/edit'),
        ];
    }
}
