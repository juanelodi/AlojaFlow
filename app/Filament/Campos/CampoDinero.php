<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Campos;

use App\Models\Propiedad;
use App\Support\Dinero;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;

/**
 * Importe en un formulario: se escribe en decimales y se guarda en la unidad menor de la moneda
 * de la propiedad (D-018).
 */
final class CampoDinero
{
    /** @var array<int, string> */
    private static array $monedas = [];

    /**
     * @param  string  $rutaPropiedad  ruta del campo propiedad_id, relativa a este campo
     */
    public static function make(string $nombre, string $rutaPropiedad = 'propiedad_id'): TextInput
    {
        return TextInput::make($nombre)
            ->numeric()
            ->minValue(0)
            ->step('any')
            ->prefix(fn (Get $get): string => self::moneda($get($rutaPropiedad)))
            ->formatStateUsing(fn (mixed $state, Get $get): mixed => is_numeric($state)
                ? Dinero::aDecimal((int) $state, self::moneda($get($rutaPropiedad)))
                : $state)
            ->dehydrateStateUsing(fn (mixed $state, Get $get): int => Dinero::aUnidadMenor(is_numeric($state) ? (string) $state : null, self::moneda($get($rutaPropiedad))));
    }

    public static function moneda(mixed $propiedadId): string
    {
        $id = (int) $propiedadId;
        if ($id <= 0) {
            return 'MXN';
        }

        return self::$monedas[$id] ??= (string) (Propiedad::query()->whereKey($id)->value('moneda') ?? 'MXN');
    }
}
