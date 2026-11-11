<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Paises;

/**
 * Estados Unidos, básico: sin registro nacional; las ordenanzas locales no están revisadas
 * (docs/paises/estados-unidos.md).
 */
final class EstadosUnidos extends PaisBase
{
    protected function clave(): string
    {
        return 'estados_unidos';
    }

    public function tiposDocumento(): array
    {
        return [
            'licencia' => __('legal.documentos.licencia'),
            'pasaporte' => __('legal.documentos.pasaporte'),
            'otro' => __('legal.documentos.otro'),
        ];
    }

    public function camposLlegada(): array
    {
        return [
            'placas' => ['etiqueta' => __('legal.campos.placas'), 'requerido' => false, 'solo_titular' => true],
        ];
    }

    public function fechaConMesPrimero(): bool
    {
        return true;
    }

    public function unidadLargo(): string
    {
        return 'ft';
    }
}
