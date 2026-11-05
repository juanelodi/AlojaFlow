<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\EstadosCuenta;

use App\Enums\FaseEstadoCuenta;
use App\Filament\Resources\Base\RecursoDePropiedad;
use App\Filament\Resources\EstadosCuenta\Pages\ListEstadosCuenta;
use App\Filament\Resources\EstadosCuenta\Pages\ViewEstadoCuenta;
use App\Models\EstadoCuenta;
use App\Modulos\Modulo;
use App\Seguridad\Acceso;
use App\Support\Formato;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Lo que le toca a cada dueño cada mes (D-115). Se generan aquí: nadie los captura a mano.
 */
class EstadoCuentaResource extends RecursoDePropiedad
{
    protected static ?string $model = EstadoCuenta::class;

    protected static ?string $slug = 'estados-de-cuenta';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    protected static ?int $navigationSort = 10;

    protected static function modulo(): ?Modulo
    {
        return Modulo::Propietarios;
    }

    protected static function permiso(): string
    {
        return Acceso::FACTURACION;
    }

    public static function getNavigationGroup(): ?string
    {
        return __('ui.nav.dinero');
    }

    public static function getModelLabel(): string
    {
        return __('propietarios.estado.modelo');
    }

    public static function getPluralModelLabel(): string
    {
        return __('propietarios.estado.modelos');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columnSpanFull()->columns(['default' => 1, 'md' => 3])->schema([
                TextEntry::make('propietario.nombre')->label(__('propietarios.modelo')),
                TextEntry::make('desde')->label(__('propietarios.estado.mes'))->formatStateUsing(fn (EstadoCuenta $record): string => $record->periodo()),
                TextEntry::make('fase')->label(__('ui.comun.estado'))->badge()->formatStateUsing(fn (EstadoCuenta $record): string => $record->fase->etiqueta())->color(fn (EstadoCuenta $record): string => self::colorDe($record->fase)),
                TextEntry::make('enviado_en')->label(__('propietarios.estado.enviado_en'))->placeholder('—')->formatStateUsing(fn (EstadoCuenta $record): string => Formato::momento($record->enviado_en, $record->propiedad->zona_horaria)),
                TextEntry::make('pagado_en')->label(__('propietarios.estado.pagado_en'))->placeholder('—')->formatStateUsing(fn (EstadoCuenta $record): string => Formato::fecha($record->pagado_en)),
                TextEntry::make('referencia_pago')->label(__('ui.pagos.referencia'))->placeholder('—'),
            ]),
            View::make('filament.estados-cuenta.detalle')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['propiedad', 'propietario']))
            ->defaultSort('desde', 'desc')
            ->columns([
                TextColumn::make('desde')->label(__('propietarios.estado.mes'))->sortable()->formatStateUsing(fn (EstadoCuenta $record): string => $record->periodo()),
                TextColumn::make('propietario.nombre')->label(__('propietarios.modelo'))->searchable(),
                self::columnaPropiedad(),
                TextColumn::make('ingresos')->label(__('propietarios.estado.ingresos'))->alignEnd()->formatStateUsing(fn (EstadoCuenta $record): string => Formato::dinero($record->ingresos, $record->moneda)),
                TextColumn::make('neto')->label(__('propietarios.estado.neto'))->alignEnd()->weight('bold')->formatStateUsing(fn (EstadoCuenta $record): string => Formato::dinero($record->neto, $record->moneda)),
                TextColumn::make('fase')->label(__('ui.comun.estado'))->badge()->formatStateUsing(fn (EstadoCuenta $record): string => $record->fase->etiqueta())->color(fn (EstadoCuenta $record): string => self::colorDe($record->fase)),
            ])
            ->filters([
                SelectFilter::make('fase')->label(__('ui.comun.estado'))->options(FaseEstadoCuenta::opciones()),
            ])
            ->emptyStateHeading(__('propietarios.estado.vacio'))
            ->emptyStateDescription(__('propietarios.estado.vacio_ayuda'))
            ->recordActions([ViewAction::make()]);
    }

    private static function colorDe(FaseEstadoCuenta $fase): string
    {
        return match ($fase) {
            FaseEstadoCuenta::Borrador => 'gray',
            FaseEstadoCuenta::Enviado => 'info',
            FaseEstadoCuenta::Pagado => 'success',
        };
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEstadosCuenta::route('/'),
            'view' => ViewEstadoCuenta::route('/{record}'),
        ];
    }
}
