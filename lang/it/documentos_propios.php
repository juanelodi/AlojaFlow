<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

return [
    'modelo' => 'documento',
    'modelos' => 'Documenti propri',
    'nombre' => 'Nome',
    'alcance' => 'Da cosa nasce',
    'alcance_ayuda' => 'Quello di una prenotazione si crea da quella prenotazione, in «Altre azioni». Quello di più prenotazioni, da questo elenco, scegliendo le date.',
    'activo' => 'Attivo',
    'cuerpo' => 'Testo del documento',
    'vacio' => 'Ancora nessun documento',
    'vacio_ayuda' => 'Un contratto, una scheda di registrazione o l’elenco degli arrivi del giorno, con il tuo testo. Puoi iniziare da quelli di partenza.',
    'variables' => 'Scrivi il documento come un testo qualsiasi. Dove vuoi un dato, metti la sua variabile tra graffe, come {huesped}. Per una lingua vuota si usa quella della struttura.',
    'accion' => 'Documento proprio',
    'elegir' => 'Documento',
    'generar' => 'Genera',
    'vista_previa' => 'Anteprima',
    'sin_reservas_previa' => 'Questa struttura non ha ancora prenotazioni con cui provarlo.',
    'desde' => 'Dal',
    'hasta' => 'Al',
    'criterio' => 'Quali prenotazioni',
    'criterios' => [
        'llegadas' => 'Quelle che arrivano in queste date',
        'salidas' => 'Quelle che partono in queste date',
        'en_casa' => 'Quelle che soggiornano in queste date',
    ],
    'demasiadas' => 'Sono più di :maximo prenotazioni per un solo documento. Scegli meno giorni.',
    'de_partida' => 'Crea quelli di partenza',
    'de_partida_ayuda' => 'Crea un contratto, una scheda di registrazione e un elenco degli arrivi, da adattare. Il contratto è solo un punto di partenza: fallo rivedere dal tuo consulente.',
    'de_partida_listo' => 'Fatto: rivedili e adattali.',
    'si' => 'Sì',

    'campos' => [
        'modelo' => 'campo proprio',
        'modelos' => 'Campi propri',
        'seccion' => 'Campi propri',
        'accion' => 'Campi propri',
        'guardados' => 'Campi salvati.',
        'nombre' => 'Nome',
        'nombre_ayuda' => 'Per esempio: targa dell’auto, camera preferita.',
        'entidad' => 'A cosa si riferisce',
        'tipo' => 'Cosa contiene',
        'activo' => 'Attivo',
        'activo_ayuda' => 'Uno inattivo non viene più chiesto, e ciò che è stato salvato resta.',
        'variable' => 'Variabile',
        'vacio' => 'Ancora nessun campo proprio',
        'vacio_ayuda' => 'Ciò che vuoi conservare di un ospite o di una prenotazione e che il sistema non chiede. Appare nelle loro schede, nei documenti e nei messaggi.',
    ],

    'base' => [
        'contrato' => [
            'nombre' => 'Contratto di alloggio',
            'partes' => 'Tra {propiedad}, con sede in {domicilio_propiedad}, e {huesped}, con documento {huesped_documento}, si conviene quanto segue:',
            'estancia' => 'Soggiorno in {unidad}, dal {llegada} al {salida}: {noches} notti, per {personas} persone.',
            'precio' => 'Prezzo totale: {total}. Pagato finora: {pagado}. Ancora da pagare: {pendiente}.',
            'personas' => 'Possono soggiornare solo le persone registrate in questa prenotazione.',
            'danos' => 'L’ospite risponde dei danni causati all’unità.',
            'firma' => 'Firmato il {hoy}:',
        ],
        'registro' => [
            'nombre' => 'Scheda di registrazione',
            'intro' => 'Prenotazione {numero} · {unidad} · dal {llegada} al {salida}.',
            'persona' => 'Persona',
            'nacimiento' => 'Nascita',
            'nacionalidad' => 'Nazionalità',
            'documento' => 'Documento',
            'firma' => 'Firma di {huesped}:',
        ],
        'llegadas' => [
            'nombre' => 'Elenco degli arrivi',
            'intro' => 'Dal {desde} al {hasta}: {cuantas} prenotazioni.',
            'llegada' => 'Arrivo',
            'salida' => 'Partenza',
            'unidad' => 'Unità',
            'huesped' => 'Ospite',
            'personas' => 'Persone',
            'pendiente' => 'Da pagare',
        ],
    ],
];
