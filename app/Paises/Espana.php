<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Paises;

use App\Autoridades\RegistroViajeros;
use App\Autoridades\Ses\SesHospedajes;

/**
 * España: comunica a SES.Hospedajes (D-100) y propone la tasa turística (D-101). Los textos legales siguen sin revisar por un asesor (docs/paises/espana.md).
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
        return 3 * 365; // RD 933/2021, art. 5.3: tres años desde que termina el servicio.
    }

    public function registroViajeros(): RegistroViajeros
    {
        return new SesHospedajes();
    }

    public function sugerenciasImpuesto(): array
    {
        return CatalogoTasaTuristica::todas();
    }

    public function declaracionEstancias(): DeclaracionEstancias
    {
        return new DeclaracionEstancias();
    }
}
