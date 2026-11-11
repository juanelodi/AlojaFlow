<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Camping;

use App\Enums\TipoLugar;
use App\Models\Propiedad;
use App\Models\Unidad;
use App\Paises\Paises;
use Illuminate\Support\Collection;

/**
 * Lo que se le dice al huésped que tiene un lugar (D-110).
 */
final class AtributosLugar
{
    /**
     * Las medidas van como las usan allá: un número y su unidad, sin convertir.
     */
    public static function unidadLargo(Propiedad $propiedad): string
    {
        return $propiedad->getAttribute('paquete_pais') === null ? 'm' : Paises::de($propiedad)->unidadLargo();
    }

    /**
     * @return list<string>
     */
    public static function etiquetas(Unidad $unidad): array
    {
        $etiquetas = [];
        $tipo = TipoLugar::tryFrom((string) $unidad->atributo('tipo'));
        if ($tipo !== null) {
            $etiquetas[] = $tipo->etiqueta();
        }
        if ($unidad->atributo('luz')) {
            $etiquetas[] = filled($unidad->atributo('amperes')) ? __('camping.atributos.luz_amperes', ['amperes' => (int) $unidad->atributo('amperes')]) : __('camping.atributos.luz');
        }
        foreach (['agua', 'drenaje', 'mascotas', 'accesible'] as $clave) {
            if ($unidad->atributo($clave)) {
                $etiquetas[] = __('camping.atributos.'.$clave);
            }
        }
        if (in_array($unidad->atributo('acceso'), ['atravesado', 'reversa'], true)) {
            $etiquetas[] = __('camping.atributos.acceso_'.$unidad->atributo('acceso'));
        }
        if (filled($unidad->atributo('largo_maximo'))) {
            $etiquetas[] = __('camping.atributos.largo_hasta', ['largo' => (float) $unidad->atributo('largo_maximo'), 'unidad' => self::unidadLargo($unidad->propiedad)]);
        }

        return $etiquetas;
    }

    /**
     * Lo que tienen todos: una oferta junta varios lugares, y no se puede prometer lo que solo tiene uno.
     *
     * @param  Collection<int, Unidad>  $unidades
     * @return list<string>
     */
    public static function comunes(Collection $unidades): array
    {
        $comunes = null;
        foreach ($unidades as $unidad) {
            $etiquetas = self::etiquetas($unidad);
            $comunes = $comunes === null ? $etiquetas : array_values(array_intersect($comunes, $etiquetas));
        }

        return $comunes ?? [];
    }

    /**
     * Si esta propiedad tiene lugares con algo que filtrar.
     */
    public static function hayQueFiltrar(Propiedad $propiedad): bool
    {
        return Unidad::query()->with('propiedad')->where('propiedad_id', $propiedad->id)->where('activa', true)->whereNotNull('atributos')->get()
            ->contains(fn (Unidad $unidad): bool => self::etiquetas($unidad) !== []);
    }
}
