<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Actualizacion;

use Illuminate\Support\Facades\Artisan;

/**
 * Baja e instala una versión sin nadie delante (D-151): los mismos pasos que «Actualizar ahora». La baja de
 * GitHub por pedazos, la revisa la firma de siempre (Revisor) y la instala alojaflow:actualizar, que hace la
 * copia de la base, aplica con diario y termina en otro proceso con el código nuevo.
 */
class InstaladorVersion
{
    /** Un zip de 30 MB son 4 pedazos; esto deja margen para reintentos sin quedarse para siempre. */
    private const PEDAZOS_MAXIMOS = 200;

    public function __construct(private readonly DescargaPorPedazos $descarga)
    {
    }

    /**
     * @throws ErrorActualizacion si no se pudo bajar o instalar
     */
    public function instalar(string $version): void
    {
        $paquete = null;
        for ($vuelta = 0; $vuelta < self::PEDAZOS_MAXIMOS && $paquete === null; $vuelta++) {
            $paquete = $this->descarga->avanzar($version)['paquete'];
        }
        if ($paquete === null) {
            throw new ErrorActualizacion(__('actualizaciones.error.descarga_red'));
        }
        $codigo = Artisan::call('alojaflow:actualizar', ['paquete' => $paquete, '--force' => true]);
        if ($codigo !== 0) {
            throw new ErrorActualizacion(trim(mb_substr(Artisan::output(), -1000)));
        }
    }
}
