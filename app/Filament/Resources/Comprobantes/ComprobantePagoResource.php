<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\Comprobantes;

use App\Enums\EstadoComprobante;
use App\Filament\Resources\Base\RecursoDePropiedad;
use App\Filament\Resources\Comprobantes\Pages\ListComprobantes;
use App\Filament\Resources\Grupos\GrupoResource;
use App\Filament\Resources\Reservas\ReservaResource;
use App\Models\ComprobantePago;
use App\Modulos\Modulo;
use App\Modulos\Modulos;
use App\Seguridad\Acceso;
use App\Seguridad\Limites;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Los pagos que alguien dice haber hecho y falta revisar, de grupos y de reservas (D-097). No se crean aquí: llegan de las páginas públicas.
 */
class ComprobantePagoResource extends RecursoDePropiedad
{
    protected static ?string $model = ComprobantePago::class;

    protected static ?string $slug = 'comprobantes';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCheck;

    protected static ?int $navigationSort = 4;

    protected static function permiso(): string
    {
        return Acceso::RESERVAS;
    }

    /**
     * Quien solo ve lo suyo no revisa los pagos de todos. Sin grupos ni motor no llega ninguno, y no hay nada que enseñar.
     */
    public static function propiedadesGestionables(): array
    {
        if (! Modulos::activo(Modulo::Grupos) && ! Modulos::activo(Modulo::Motor)) {
            return [];
        }

        return array_values(array_diff(parent::propiedadesGestionables(), Limites::propiedadesSoloPropias(Acceso::usuarioActual())));
    }

    public static function getNavigationGroup(): ?string
    {
        return __('ui.nav.dinero');
    }

    public static function getModelLabel(): string
    {
        return __('grupos.comprobante.modelo');
    }

    public static function getPluralModelLabel(): string
    {
        return __('grupos.comprobante.titulo_lista');
    }

    public static function getNavigationBadge(): ?string
    {
        $porRevisar = ComprobantePago::query()->whereIn('propiedad_id', self::propiedadesGestionables())->where('estado', EstadoComprobante::PorConfirmar->value)->count();

        return $porRevisar > 0 ? (string) $porRevisar : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
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

    public static function table(Table $table): Table
    {
        return AccionesComprobante::tabla($table)
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['revisor', 'archivo', 'grupo', 'reserva', 'propiedad']))
            ->columns([
                TextColumn::make('de')->label(__('grupos.comprobante.de'))
                    ->state(fn (ComprobantePago $record): string => $record->grupo !== null ? $record->grupo->nombre : (string) $record->reserva?->numero)
                    ->url(fn (ComprobantePago $record): ?string => match (true) {
                        $record->grupo !== null => GrupoResource::getUrl('view', ['record' => $record->grupo]),
                        $record->reserva !== null => ReservaResource::getUrl('view', ['record' => $record->reserva]),
                        default => null,
                    }),
                self::columnaPropiedad(),
                ...AccionesComprobante::columnas(),
            ])
            ->filters([SelectFilter::make('estado')->label(__('ui.comun.estado'))->options(EstadoComprobante::opciones())->default(EstadoComprobante::PorConfirmar->value)]);
    }

    public static function getPages(): array
    {
        return ['index' => ListComprobantes::route('/')];
    }
}
