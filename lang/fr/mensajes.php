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
            'nombre' => 'Confirmation de réservation',
            'asunto' => 'Votre réservation à {propiedad} est confirmée',
            'cuerpo' => "Bonjour {nombre},\n\nVotre réservation {numero} à {propiedad} est confirmée.\n\nArrivée : {llegada}\nDépart : {salida}\nPersonnes : {personas}\nTotal : {total}\nReste à payer : {pendiente}\n\nConsultez votre réservation ici : {enlace_reserva}\n\nPour toute question, écrivez à {correo_propiedad} ou appelez le {telefono_propiedad}.\n\nAu plaisir de vous accueillir !\n{propiedad}",
        ],
        'solicitud' => [
            'nombre' => 'Demande reçue',
            'asunto' => 'Nous avons reçu votre demande de réservation à {propiedad}',
            'cuerpo' => "Bonjour {nombre},\n\nNous avons reçu votre demande pour le {llegada}. Nous vous confirmerons dès que nous l’aurons examinée.\n\n{propiedad}",
        ],
        'recordatorio' => [
            'nombre' => 'Rappel d’arrivée',
            'asunto' => 'Au plaisir de vous accueillir à {propiedad}',
            'cuerpo' => "Bonjour {nombre},\n\nVotre arrivée approche : le {llegada}.\n\nReste à payer : {pendiente}\n\nEnregistrez-vous à l’avance ici : {enlace_llegada}\n\nPour toute question, appelez le {telefono_propiedad}.\n\n{propiedad}",
        ],
        'gracias' => [
            'nombre' => 'Merci de votre visite',
            'asunto' => 'Merci d’avoir séjourné à {propiedad}',
            'cuerpo' => "Bonjour {nombre},\n\nMerci d’avoir séjourné chez nous. Nous espérons vous revoir bientôt.\n\n{propiedad}",
        ],
        'cancelacion' => [
            'nombre' => 'Réservation annulée',
            'asunto' => 'Votre réservation à {propiedad} a été annulée',
            'cuerpo' => "Bonjour {nombre},\n\nVotre réservation {numero} du {llegada} a été annulée. Si vous pensez qu’il s’agit d’une erreur, écrivez à {correo_propiedad}.\n\n{propiedad}",
        ],
    ],
];
