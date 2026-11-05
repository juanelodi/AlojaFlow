<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Pagos;

/**
 * Una pasarela a la que no se llega con un enlace, sino mandando un formulario firmado desde el navegador. La página que lo manda es `motor.ir`.
 */
interface PagoConFormulario
{
    /**
     * @param  array<string, string>  $parametros  lo que `iniciar` dejó en los datos del cobro
     * @return array{accion: string, campos: array<string, string>}
     */
    public function formulario(array $parametros): array;
}
