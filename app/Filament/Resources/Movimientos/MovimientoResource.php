<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\Movimientos;

use App\Enums\MetodoPago;
use App\Enums\TipoMovimiento;
use App\Filament\Campos\CampoDinero;
use App\Filament\Resources\Base\RecursoDePropiedad;
use App\Filament\Resources\Movimientos\Pages\CreateMovimiento;
use App\Filament\Resources\Movimientos\Pages\EditMovimiento;
use App\Filament\Resources\Movimientos\Pages\ListMovimientos;
use App\Models\Caja;
use App\Models\Movimiento;
use App\Seguridad\Acceso;
use App\Support\Formato;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Gastos e ingresos que no son de una reserva. Los cobros de reservas se registran en cada reserva.
 */
class MovimientoResource extends RecursoDePropiedad
{
    protected static ?string $model = Movimiento::class;

    protected static ?string $slug = 'gastos-e-ingresos';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?int $navigationSort = 2;

    protected static function permiso(): string
    {
        return Acceso::RESERVAS;
    }

    public static function getNavigationGroup(): ?string
    {
        return __('ui.nav.dinero');
    }

    public static function getModelLabel(): string
    {
        return __('ui.movimientos.modelo');
    }

    public static function getPluralModelLabel(): string
    {
        return __('ui.movimientos.modelos');
    }

    protected static function referencias(): array
    {
        return ['caja_id' => Caja::class];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columnSpanFull()->columns(['default' => 1, 'md' => 2])->schema([
                self::campoPropiedad(),
                Select::make('tipo')->label(__('ui.movimientos.tipo'))->options(TipoMovimiento::opciones())->required()->native(false)->default(TipoMovimiento::Gasto->value),
                DatePicker::make('fecha')->label(__('ui.pagos.fecha'))->required()->default(fn (): string => now()->format('Y-m-d')),
                TextInput::make('concepto')->label(__('ui.movimientos.concepto'))->required()->maxLength(255),
                TextInput::make('categoria')->label(__('ui.movimientos.categoria'))->maxLength(100)
                    ->datalist(fn (Get $get): array => Movimiento::query()->where('propiedad_id', self::propiedadDe($get))->whereNotNull('categoria')->distinct()->orderBy('categoria')->pluck('categoria')->all()),
                CampoDinero::make('importe')->label(__('ui.pagos.importe'))->required(),
                Select::make('metodo')->label(__('ui.pagos.metodo'))->options(MetodoPago::opciones())->required()->native(false)->default(MetodoPago::Efectivo->value),
                Select::make('caja_id')->label(__('ui.cajas.modelo_campo'))->native(false)
                    ->options(fn (Get $get): array => Caja::query()->where('propiedad_id', self::propiedadDe($get))->where('activa', true)->orderBy('nombre')->pluck('nombre', 'id')->all()),
                TextInput::make('referencia')->label(__('ui.pagos.referencia'))->maxLength(100),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['propiedad', 'caja']))
            ->defaultSort('fecha', 'desc')
            ->columns([
                TextColumn::make('fecha')->label(__('ui.pagos.fecha'))->sortable()->formatStateUsing(fn (Movimiento $record): string => Formato::fecha($record->fecha)),
                self::columnaPropiedad(),
                TextColumn::make('tipo')->label(__('ui.movimientos.tipo'))->badge()
                    ->formatStateUsing(fn (Movimiento $record): string => $record->tipo->etiqueta())
                    ->color(fn (Movimiento $record): string => $record->tipo === TipoMovimiento::Gasto ? 'warning' : 'success'),
                TextColumn::make('concepto')->label(__('ui.movimientos.concepto'))->searchable()->wrap(),
                TextColumn::make('categoria')->label(__('ui.movimientos.categoria'))->placeholder('—')->searchable(),
                TextColumn::make('metodo')->label(__('ui.pagos.metodo'))->formatStateUsing(fn (Movimiento $record): string => $record->metodo->etiqueta()),
                TextColumn::make('importe')->label(__('ui.pagos.importe'))->alignEnd()->sortable()
                    ->formatStateUsing(fn (Movimiento $record): string => Formato::dinero($record->importe, $record->moneda)),
            ])
            ->filters([
                SelectFilter::make('tipo')->label(__('ui.movimientos.tipo'))->options(TipoMovimiento::opciones()),
                Filter::make('fecha')->label(__('ui.pagos.fecha'))
                    ->schema([
                        DatePicker::make('desde')->label(__('ui.indicadores.desde')),
                        DatePicker::make('hasta')->label(__('ui.indicadores.hasta')),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['desde'] ?? null, fn (Builder $q, string $desde): Builder => $q->where('fecha', '>=', $desde))
                        ->when($data['hasta'] ?? null, fn (Builder $q, string $hasta): Builder => $q->where('fecha', '<=', $hasta))),
            ])
            ->emptyStateHeading(__('ui.movimientos.vacio'))
            ->recordActions([EditAction::make()->visible(fn (Movimiento $record): bool => self::canEdit($record))]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMovimientos::route('/'),
            'create' => CreateMovimiento::route('/create'),
            'edit' => EditMovimiento::route('/{record}/edit'),
        ];
    }
}
