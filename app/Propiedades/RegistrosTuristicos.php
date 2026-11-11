<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Propiedades;

use App\Enums\NivelAviso;
use App\Models\Aviso;
use App\Models\Propiedad;
use App\Models\RegistroTuristico;
use App\Models\Reserva;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Qué número de registro se enseña y cuándo avisar de que vence (D-091).
 */
final class RegistrosTuristicos
{
    private const TIPO_AVISO = 'registro_por_vencer';

    private const DIAS_AVISO = 30;

    /**
     * En las páginas públicas, todos los que se pueden enseñar. En un documento, los de la propiedad y los de la unidad de esa reserva: el
     * del departamento de al lado no pinta nada en este recibo.
     *
     * @return Collection<int, RegistroTuristico>
     */
    public static function visibles(Propiedad $propiedad, ?Reserva $reserva = null): Collection
    {
        return RegistroTuristico::query()->with('unidad')
            ->where('propiedad_id', $propiedad->id)
            ->where('mostrar', true)
            ->when($reserva !== null, fn (Builder $query): Builder => $query->where(
                fn (Builder $query): Builder => $query->whereNull('unidad_id')->orWhere('unidad_id', $reserva?->unidad_id)
            ))
            // Los de toda la propiedad, primero: en MySQL y MariaDB los vacíos van antes.
            ->orderBy('unidad_id')
            ->orderBy('id')
            ->get();
    }

    public static function revisar(int $propiedadId): void
    {
        $porVencer = RegistroTuristico::query()->where('propiedad_id', $propiedadId)->whereNotNull('vigente_hasta')
            ->whereDate('vigente_hasta', '<=', now()->addDays(self::DIAS_AVISO))->exists();
        if ($porVencer) {
            Aviso::abrir($propiedadId, self::TIPO_AVISO, NivelAviso::Advertencia);
        } else {
            Aviso::resolver($propiedadId, self::TIPO_AVISO);
        }
    }

    public static function revisarTodas(): void
    {
        foreach (Propiedad::query()->pluck('id') as $propiedadId) {
            self::revisar((int) $propiedadId);
        }
    }
}
