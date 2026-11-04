<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
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
        $this->info("Eventos: $eventos · Por fecha: $generados · Enviados: $enviados");

        return self::SUCCESS;
    }
}
