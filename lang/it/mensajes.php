<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

return [
    'base' => [
        'confirmacion' => [
            'nombre' => 'Conferma della prenotazione',
            'asunto' => 'La tua prenotazione presso {propiedad} è confermata',
            'cuerpo' => "Ciao {nombre},\n\nLa tua prenotazione {numero} presso {propiedad} è confermata.\n\nArrivo: {llegada}\nPartenza: {salida}\nPersone: {personas}\nTotale: {total}\nDa pagare: {pendiente}\n\nConsulta la tua prenotazione qui: {enlace_reserva}\n\nPer qualsiasi domanda, scrivi a {correo_propiedad} o chiama il {telefono_propiedad}.\n\nTi aspettiamo!\n{propiedad}",
        ],
        'solicitud' => [
            'nombre' => 'Richiesta ricevuta',
            'asunto' => 'Abbiamo ricevuto la tua richiesta di prenotazione presso {propiedad}',
            'cuerpo' => "Ciao {nombre},\n\nAbbiamo ricevuto la tua richiesta per il {llegada}. Ti confermeremo non appena l'avremo esaminata.\n\n{propiedad}",
        ],
        'recordatorio' => [
            'nombre' => 'Promemoria di arrivo',
            'asunto' => 'Ti aspettiamo presso {propiedad}',
            'cuerpo' => "Ciao {nombre},\n\nManca poco al tuo arrivo, il {llegada}.\n\nDa pagare: {pendiente}\n\nRegistrati in anticipo qui: {enlace_llegada}\n\nPer qualsiasi domanda, chiama il {telefono_propiedad}.\n\n{propiedad}",
        ],
        'gracias' => [
            'nombre' => 'Grazie per la visita',
            'asunto' => 'Grazie per aver soggiornato presso {propiedad}',
            'cuerpo' => "Ciao {nombre},\n\nGrazie per aver soggiornato da noi. Speriamo di rivederti presto.\n\n{propiedad}",
        ],
        'cancelacion' => [
            'nombre' => 'Prenotazione annullata',
            'asunto' => 'La tua prenotazione presso {propiedad} è stata annullata',
            'cuerpo' => "Ciao {nombre},\n\nLa tua prenotazione {numero} del {llegada} è stata annullata. Se pensi che sia un errore, scrivi a {correo_propiedad}.\n\n{propiedad}",
        ],
    ],
];
