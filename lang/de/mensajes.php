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
            'nombre' => 'Buchungsbestätigung',
            'asunto' => 'Ihre Buchung bei {propiedad} ist bestätigt',
            'cuerpo' => "Hallo {nombre},\n\nIhre Buchung {numero} bei {propiedad} ist bestätigt.\n\nAnkunft: {llegada}\nAbreise: {salida}\nPersonen: {personas}\nGesamt: {total}\nNoch zu zahlen: {pendiente}\n\nIhre Buchung finden Sie hier: {enlace_reserva}\n\nBei Fragen schreiben Sie an {correo_propiedad} oder rufen Sie {telefono_propiedad} an.\n\nWir freuen uns auf Sie!\n{propiedad}",
        ],
        'solicitud' => [
            'nombre' => 'Anfrage erhalten',
            'asunto' => 'Wir haben Ihre Buchungsanfrage bei {propiedad} erhalten',
            'cuerpo' => "Hallo {nombre},\n\nWir haben Ihre Anfrage für den {llegada} erhalten. Sobald wir sie geprüft haben, bestätigen wir sie.\n\n{propiedad}",
        ],
        'recordatorio' => [
            'nombre' => 'Erinnerung an die Ankunft',
            'asunto' => 'Wir freuen uns auf Sie bei {propiedad}',
            'cuerpo' => "Hallo {nombre},\n\nIhre Ankunft am {llegada} rückt näher.\n\nNoch zu zahlen: {pendiente}\n\nRegistrieren Sie sich hier im Voraus: {enlace_llegada}\n\nBei Fragen rufen Sie {telefono_propiedad} an.\n\n{propiedad}",
        ],
        'gracias' => [
            'nombre' => 'Danke für Ihren Besuch',
            'asunto' => 'Danke für Ihren Aufenthalt bei {propiedad}',
            'cuerpo' => "Hallo {nombre},\n\nDanke, dass Sie bei uns waren. Wir hoffen, Sie bald wiederzusehen.\n\n{propiedad}",
        ],
        'cancelacion' => [
            'nombre' => 'Buchung storniert',
            'asunto' => 'Ihre Buchung bei {propiedad} wurde storniert',
            'cuerpo' => "Hallo {nombre},\n\nIhre Buchung {numero} vom {llegada} wurde storniert. Wenn Sie glauben, dass dies ein Fehler ist, schreiben Sie an {correo_propiedad}.\n\n{propiedad}",
        ],
    ],
];
