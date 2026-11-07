<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Limpieza;

use App\Enums\LugarOmision;
use App\Models\Omision;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Qué clientes no salen en cada lugar (D-074, D-144): por ficha, o todos los que tienen cierto valor en un campo. «En todo» vale por cualquier lugar.
 */
final class Omisiones
{
    /**
     * @param  list<int>  $propiedades
     * @return list<int>
     */
    public static function huespedes(array $propiedades, LugarOmision $lugar): array
    {
        $ids = [];
        foreach (Omision::query()->whereIn('propiedad_id', $propiedades)->where(fn ($q) => $q->where('en_todo', true)->orWhere($lugar->value, true))->get(['propiedad_id', 'huesped_id', 'campo', 'valor']) as $omision) {
            if ($omision->huesped_id !== null) {
                $ids[] = $omision->huesped_id;
            } elseif ($omision->campo !== null) {
                foreach (DB::table('campos_huesped as c')->join('huespedes as h', 'h.id', '=', 'c.huesped_id')->where('h.propiedad_id', $omision->propiedad_id)
                    ->where('c.campo', $omision->campo)->where('c.valor', $omision->valor)->pluck('c.huesped_id') as $id) {
                    $ids[] = (int) $id;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    public static function estaOmitido(?int $huespedId, int $propiedadId, LugarOmision $lugar): bool
    {
        return $huespedId !== null && in_array($huespedId, self::huespedes([$propiedadId], $lugar), true);
    }

    /**
     * Quita de una consulta a los omitidos. Una reserva sin titular, como un bloqueo, se queda.
     *
     * @template T of Builder
     *
     * @param  T  $consulta
     * @param  list<int>  $propiedades
     * @return T
     */
    public static function excluir(Builder $consulta, string $columnaTitular, array $propiedades, LugarOmision $lugar): Builder
    {
        $ids = self::huespedes($propiedades, $lugar);
        if ($ids !== []) {
            $consulta->where(fn ($q) => $q->whereNull($columnaTitular)->orWhereNotIn($columnaTitular, $ids));
        }

        return $consulta;
    }
}
