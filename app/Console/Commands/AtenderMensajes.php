<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Console\Commands;

use App\Comunicacion\Mensajero;
use Illuminate\Console\Command;

/**
 * Genera los mensajes de las automatizaciones y manda los pendientes.
 */
class AtenderMensajes extends Command
{
    protected $signature = 'alojaflow:mensajes';

    protected $description = 'Atiende los eventos de las reservas, las automatizaciones por fecha y la cola de envíos';

    public function handle(Mensajero $mensajero): int
    {
        $eventos = $mensajero->procesarEventos();
        $generados = $mensajero->procesarTiempo();
        $enviados = $mensajero->enviarPendientes();
        $this->info(__('consola.mensajes', ['eventos' => $eventos, 'generados' => $generados, 'enviados' => $enviados]));

        return self::SUCCESS;
    }
}
