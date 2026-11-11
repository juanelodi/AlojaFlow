<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Enums;

/**
 * Etiquetas traducidas de los enums: lang/<idioma>/enums.php, clave <Enum>.<valor>.
 */
trait ConEtiqueta
{
    public function etiqueta(): string
    {
        return __('enums.'.class_basename(static::class).'.'.$this->value);
    }

    /**
     * @return array<string, string> valor => etiqueta, para listas de selección
     */
    public static function opciones(): array
    {
        $opciones = [];
        foreach (self::cases() as $caso) {
            $opciones[$caso->value] = $caso->etiqueta();
        }

        return $opciones;
    }
}
