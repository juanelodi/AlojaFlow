<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actualizacion\UltimaVersion;
use App\Demo\ModoDemo;
use Illuminate\Console\Command;

/**
 * Una vez al día pregunta cuál es la última versión (D-140). Solo avisa: nunca instala nada.
 */
class BuscarVersion extends Command
{
    protected $signature = 'alojaflow:buscar-version';

    protected $description = 'Consulta la última versión publicada para avisar al super administrador';

    public function handle(UltimaVersion $ultimaVersion): int
    {
        if (ModoDemo::activo() || ! $ultimaVersion->avisoActivo()) {
            $this->info(__('actualizaciones.consola.aviso_apagado'));

            return self::SUCCESS;
        }
        if (! $ultimaVersion->consultar()) {
            // No es una falla de esta instalación: mañana se vuelve a preguntar.
            $this->warn(__('actualizaciones.consola.consulta_fallida'));

            return self::SUCCESS;
        }
        $nueva = $ultimaVersion->disponible();
        $this->info($nueva !== null ? __('actualizaciones.hay_version', ['version' => $nueva['version']]) : __('actualizaciones.al_dia'));

        return self::SUCCESS;
    }
}
