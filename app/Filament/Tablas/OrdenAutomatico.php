<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Tablas;

use Filament\Tables\Columns\Column;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Que en toda tabla se pueda ordenar por lo que muestra, sin acordarse de pedirlo columna por columna: toda columna de la base, o de un
 * dato del que depende la fila (la unidad de una reserva), se puede ordenar. Lo calculado, lo cifrado y lo que viene de una lista, no.
 */
final class OrdenAutomatico
{
    /** @var array<class-string<Model>, list<string>> */
    private static array $columnas = [];

    public static function sePuede(Column $columna): bool
    {
        try {
            $modelo = $columna->getTable()->getModel();
            $instancia = new $modelo();
            $nombre = $columna->getName();
            if (! str_contains($nombre, '.')) {
                return self::esColumnaLegible($instancia, $nombre);
            }
            $partes = explode('.', $nombre);
            $campo = (string) array_pop($partes);
            foreach ($partes as $relacion) {
                if (! method_exists($instancia, $relacion)) {
                    return false;
                }
                $relacionada = $instancia->{$relacion}();
                if (! $relacionada instanceof BelongsTo) {
                    return false;
                }
                $instancia = $relacionada->getRelated();
            }

            return self::esColumnaLegible($instancia, $campo);
        } catch (Throwable) {
            return false;
        }
    }

    private static function esColumnaLegible(Model $modelo, string $campo): bool
    {
        $cast = $modelo->getCasts()[$campo] ?? '';
        if (str_contains($cast, 'encrypted') || in_array($cast, ['array', 'json', 'collection', 'object'], true)) {
            return false;
        }

        return in_array($campo, self::columnasDe($modelo), true);
    }

    /**
     * @return list<string>
     */
    private static function columnasDe(Model $modelo): array
    {
        return self::$columnas[$modelo::class] ??= Schema::connection($modelo->getConnectionName())->getColumnListing($modelo->getTable());
    }
}
