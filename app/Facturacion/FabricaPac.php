<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Facturacion;

use App\Models\EntidadFiscal;

/**
 * El PAC de quien emite (D-098). Cada entidad fiscal contrata el suyo: dos razones sociales en una instalación no comparten cuenta. Sin ninguno, lo que diga
 * `config('alojaflow.pac')`, que de entrada es no timbrar.
 */
final class FabricaPac
{
    public const SW = 'sw';

    public function de(EntidadFiscal $entidad): ConectorPac
    {
        $ajustes = (array) $entidad->configuracion;
        if (($ajustes['pac'] ?? null) === self::SW && filled($ajustes['pac_token'] ?? null)) {
            return new ConectorSw((string) $ajustes['pac_token'], $entidad->modo);
        }

        /** @var ConectorPac $porOmision */
        $porOmision = app((string) config('alojaflow.pac', SinPac::class));

        return $porOmision;
    }
}
