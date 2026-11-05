<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\Grupos;

use App\Enums\EstadoReserva;
use App\Filament\Resources\Base\RecursoDePropiedad;
use App\Filament\Resources\Grupos\Pages\CreateGrupo;
use App\Filament\Resources\Grupos\Pages\ListGrupos;
use App\Filament\Resources\Grupos\Pages\ViewGrupo;
use App\Models\Grupo;
use App\Models\Huesped;
use App\Models\Unidad;
use App\Modulos\Modulo;
use App\Reservas\GestorGrupos;
use App\Seguridad\Acceso;
use App\Seguridad\Limites;
use App\Support\Formato;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Reservas de grupo: varias unidades con un mismo coordinador y un solo saldo.
 */
class GrupoResource extends RecursoDePropiedad
{
    protected static ?string $model = Grupo::class;

    protected static ?string $slug = 'grupos';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?int $navigationSort = 3;

    protected static function modulo(): ?Modulo
    {
        return Modulo::Grupos;
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
        return __('ui.grupos.modelo');
    }

    public static function getPluralModelLabel(): string
    {
        return __('ui.grupos.modelos');
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canView(Model $record): bool
    {
        return in_array((int) $record->getAttribute('propiedad_id'), static::propiedadesGestionables(), true);
    }

    protected static function referencias(): array
    {
        return ['coordinador_id' => Huesped::class];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columnSpanFull()->columns(['default' => 1, 'md' => 2])->schema([
                self::campoPropiedad(),
                TextInput::make('nombre')->label(__('ui.grupos.nombre'))->helperText(__('ui.grupos.nombre_ayuda'))->required()->maxLength(150),
                Select::make('coordinador_id')->label(__('ui.grupos.coordinador'))->required()->native(false)->searchable()
                    ->getSearchResultsUsing(fn (string $search, Get $get): array => Limites::huespedesVisibles(Huesped::query(), Acceso::usuarioActual())->where('propiedad_id', self::propiedadDe($get))
                        ->where(fn (Builder $q): Builder => $q->where('nombre', 'like', '%'.$search.'%')->orWhere('apellido1', 'like', '%'.$search.'%'))
                        ->limit(30)->get()->mapWithKeys(fn (Huesped $h): array => [$h->id => $h->nombreCompleto()])->all())
                    ->getOptionLabelUsing(fn (mixed $value, Get $get): ?string => Huesped::query()->where('propiedad_id', self::propiedadDe($get))->find((int) $value)?->nombreCompleto()),
                Select::make('estado')->label(__('ui.comun.estado'))->required()->native(false)
                    ->options([
                        EstadoReserva::Confirmada->value => EstadoReserva::Confirmada->etiqueta(),
                        EstadoReserva::SinConfirmar->value => EstadoReserva::SinConfirmar->etiqueta(),
                    ])
                    ->default(EstadoReserva::Confirmada->value),
                DatePicker::make('llegada')->label(__('ui.comun.llegada'))->required()->live(onBlur: true),
                DatePicker::make('salida')->label(__('ui.comun.salida'))->required()->after('llegada')->live(onBlur: true),
                TextInput::make('personas')->label(__('ui.grupos.personas_por_unidad'))->numeric()->minValue(1)->default(2)->required(),
                CheckboxList::make('unidades')->label(__('ui.grupos.unidades'))->helperText(__('ui.grupos.unidades_ayuda'))->required()->columnSpanFull()->columns(['default' => 2, 'md' => 4])
                    ->options(fn (Get $get): array => blank($get('propiedad_id')) ? [] : Unidad::query()->where('propiedad_id', self::propiedadDe($get))->where('activa', true)->orderBy('orden')->orderBy('nombre')->pluck('nombre', 'id')->all())
                    ->visibleOn('create'),
                Textarea::make('notas')->label(__('ui.huespedes.notas'))->rows(2)->maxLength(5000)->columnSpanFull(),
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        $dinero = fn (string $campo): \Closure => function (Grupo $record) use ($campo): string {
            $saldo = app(GestorGrupos::class)->saldo($record);

            return Formato::dinero($saldo[$campo], $saldo['moneda']);
        };

        return $schema->components([
            Section::make()->columnSpanFull()->columns(['default' => 1, 'sm' => 2, 'lg' => 4])->schema([
                TextEntry::make('codigo')->label(__('ui.grupos.codigo'))->copyable(),
                TextEntry::make('nombre')->label(__('ui.grupos.nombre')),
                TextEntry::make('coordinador')->label(__('ui.grupos.coordinador'))
                    ->state(fn (Grupo $record): ?string => $record->coordinador?->nombreCompleto())->placeholder('—'),
                TextEntry::make('propiedad.nombre')->label(__('ui.comun.propiedad')),
                TextEntry::make('saldo_total')->label(__('ui.comun.total'))->state($dinero('total')),
                TextEntry::make('saldo_pagado')->label(__('ui.comun.pagado'))->state($dinero('pagado')),
                TextEntry::make('saldo_pendiente')->label(__('ui.comun.pendiente'))->state($dinero('pendiente'))->weight('bold'),
                TextEntry::make('notas')->label(__('ui.huespedes.notas'))->placeholder('—'),
            ]),
            Section::make(__('ui.reservas.modelos'))->columnSpanFull()->schema([
                ViewEntry::make('lista')->hiddenLabel()->view('filament.partes.grupo-reservas'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['propiedad', 'coordinador'])->withCount('reservas'))
            ->defaultSort('creado_en', 'desc')
            ->columns([
                TextColumn::make('codigo')->label(__('ui.grupos.codigo'))->searchable(),
                TextColumn::make('nombre')->label(__('ui.grupos.nombre'))->searchable(),
                self::columnaPropiedad(),
                TextColumn::make('coordinador_id')->label(__('ui.grupos.coordinador'))
                    ->formatStateUsing(fn (Grupo $record): string => $record->coordinador?->nombreCompleto() ?? '—'),
                TextColumn::make('reservas_count')->label(__('ui.reservas.modelos'))->alignEnd(),
                TextColumn::make('pendiente')->label(__('ui.comun.pendiente'))->alignEnd()
                    ->state(function (Grupo $record): string {
                        $saldo = app(GestorGrupos::class)->saldo($record);

                        return Formato::dinero($saldo['pendiente'], $saldo['moneda']);
                    }),
            ])
            ->emptyStateHeading(__('ui.grupos.vacio'))
            ->recordActions([ViewAction::make()])
            ->recordUrl(fn (Grupo $record): string => self::getUrl('view', ['record' => $record]));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGrupos::route('/'),
            'create' => CreateGrupo::route('/create'),
            'view' => ViewGrupo::route('/{record}'),
        ];
    }
}
