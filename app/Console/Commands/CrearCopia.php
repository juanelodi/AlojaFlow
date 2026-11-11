<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Console\Commands;

use App\Copias\CopiaExterna;
use App\Copias\ErrorCopia;
use App\Copias\GestorCopias;
use App\Support\Ajustes;
use Illuminate\Console\Command;

/**
 * Copia de seguridad completa. El programador la lanza cada noche con --programada, salvo que el
 * administrador haya apagado las copias automáticas.
 */
class CrearCopia extends Command
{
    protected $signature = 'alojaflow:copia {--programada : La lanza el programador: respeta el ajuste de copias automáticas}';

    protected $description = 'Crea una copia de seguridad completa (base de datos y archivos)';

    public function handle(GestorCopias $copias): int
    {
        $programada = (bool) $this->option('programada');
        if ($programada && ! Ajustes::obtener('copias.automaticas', true)) {
            return self::SUCCESS;
        }

        try {
            $copia = $copias->crear($programada ? 'programada' : 'manual');
        } catch (ErrorCopia $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $this->info(__('copias.creada', ['nombre' => $copia['nombre']]));
        // Fuera del servidor (D-152), si se configuró: cifrada.
        $externa = app(CopiaExterna::class);
        if ($externa->configurada()) {
            $externa->subir($copias->ruta($copia['nombre']))
                ? $this->info(__('copias.externa.subida'))
                : $this->warn(__('copias.externa.no_subida', ['motivo' => (string) ($externa->ultima()['error'] ?? '')]));
        }

        return self::SUCCESS;
    }
}
