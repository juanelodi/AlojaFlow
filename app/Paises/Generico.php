<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Paises;

/**
 * Para cualquier otro país: una ficha exportable y textos editables.
 */
final class Generico extends PaisBase
{
    protected function clave(): string
    {
        return 'generico';
    }

    public function tiposDocumento(): array
    {
        return [
            'pasaporte' => __('legal.documentos.pasaporte'),
            'identificacion' => __('legal.documentos.identificacion'),
            'otro' => __('legal.documentos.otro'),
        ];
    }
}
