<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Filament\Resources\Base;

use App\Models\Propiedad;
use App\Modulos\Modulo;
use App\Modulos\Modulos;
use App\Seguridad\Acceso;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Catálogo de una propiedad independiente: solo se ve y se cambia lo de las propiedades en las que
 * el usuario tiene el permiso.
 */
abstract class RecursoDePropiedad extends Resource
{
    /** En español no se escribe cada palabra con mayúscula: «Llegadas en línea». */
    protected static bool $hasTitleCaseModelLabel = false;

    protected static function permiso(): string
    {
        return Acceso::CATALOGO;
    }

    /**
     * El módulo al que pertenece. Apagado, el recurso se queda sin propiedades: no sale en el menú y no abre (D-069).
     */
    protected static function modulo(): ?Modulo
    {
        return null;
    }

    /**
     * @return list<int>
     */
    public static function propiedadesGestionables(): array
    {
        return Modulos::disponible(static::modulo()) ? Acceso::propiedadesIndependientes(Acceso::usuarioActual(), static::permiso()) : [];
    }

    public static function getNavigationGroup(): ?string
    {
        return __('ui.nav.configuracion');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereIn('propiedad_id', static::propiedadesGestionables());
    }

    public static function canViewAny(): bool
    {
        return static::propiedadesGestionables() !== [];
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit(Model $record): bool
    {
        return in_array((int) $record->getAttribute('propiedad_id'), static::propiedadesGestionables(), true);
    }

    public static function canDelete(Model $record): bool
    {
        return static::canEdit($record);
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    /**
     * La propiedad que dice el formulario, solo si el usuario tiene acceso a ella.
     */
    protected static function propiedadDe(Get $get): int
    {
        return Acceso::propiedadElegida($get('propiedad_id'), static::propiedadesGestionables());
    }

    /**
     * Campos del formulario que apuntan a otra fila de la misma propiedad.
     *
     * @return array<string, class-string<Model>>
     */
    protected static function referencias(): array
    {
        return [];
    }

    /**
     * El navegador manda lo que quiera: al guardar se comprueba que cada dato sea de esa propiedad.
     *
     * @param  array<string, mixed>  $data
     */
    public static function comprobarReferencias(array $data, int $propiedadId): void
    {
        foreach (static::referencias() as $campo => $modelo) {
            $valor = $data[$campo] ?? null;
            if (filled($valor) && ! $modelo::query()->where('propiedad_id', $propiedadId)->whereKey($valor)->exists()) {
                abort(403);
            }
        }
    }

    public static function campoPropiedad(): Select
    {
        return Select::make('propiedad_id')->label(__('ui.comun.propiedad'))->required()->native(false)->live()
            ->options(fn (): array => Propiedad::query()->whereIn('id', static::propiedadesGestionables())->orderBy('nombre')->pluck('nombre', 'id')->all())
            ->default(function (): ?int {
                $permitidas = static::propiedadesGestionables();

                return count($permitidas) === 1 ? $permitidas[0] : null;
            })
            ->disabledOn('edit');
    }

    public static function columnaPropiedad(): TextColumn
    {
        return TextColumn::make('propiedad.nombre')->label(__('ui.comun.propiedad'))
            ->visible(fn (): bool => count(static::propiedadesGestionables()) > 1);
    }
}
