<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Paises;

/**
 * España, básico: recoge los datos, pero todavía no los envía a SES.Hospedajes. Plazos y textos
 * sin verificar contra fuentes oficiales (docs/paises/espana.md).
 */
final class Espana extends PaisBase
{
    protected function clave(): string
    {
        return 'espana';
    }

    public function tiposDocumento(): array
    {
        return [
            'dni' => __('legal.documentos.dni'),
            'nie' => __('legal.documentos.nie'),
            'pasaporte' => __('legal.documentos.pasaporte'),
            'otro' => __('legal.documentos.otro'),
        ];
    }

    public function camposLlegada(): array
    {
        return [
            'soporte' => ['etiqueta' => __('legal.campos.soporte'), 'requerido' => false, 'solo_titular' => false],
            'parentesco' => ['etiqueta' => __('legal.campos.parentesco'), 'requerido' => false, 'solo_titular' => false],
        ];
    }

    public function documentoDeTodos(): bool
    {
        return true;
    }

    public function diasConservarRegistro(): int
    {
        return 3 * 365; // Plazo a verificar: el registro de viajeros pide conservarlo varios años.
    }
}
