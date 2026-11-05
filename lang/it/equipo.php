<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

return [
    'aviso' => [
        'titulo' => 'Avviso di arrivi e partenze',
        'desde' => 'Dal',
        'dias' => 'Giorni',
        'anadir' => 'Aggiungi a ogni unità',
        'nombre' => 'Nome',
        'personas' => 'Persone',
        'origen' => 'Origine',
        'noches' => 'Notti',
        'telefono' => 'Telefono',
        'comentario' => 'Commento',
        'mostrar_limpieza' => 'Mostra le pulizie',
        'texto' => 'Testo dell’avviso (puoi ritoccarlo prima di inviarlo)',
        'telefono_destino' => 'WhatsApp di chi lo riceve',
        'salen' => 'Partenze',
        'llegan' => 'Arrivi',
        'limpieza' => 'Pulizie',
        'n_personas' => ':count persona|:count persone',
        'sin_movimiento' => 'Nessun movimento in queste date.',
        'omitidos_ayuda' => 'Un cliente interno o di manutenzione non deve comparire qui? Segnalo in',
    ],

    'omisiones' => [
        'modelo' => 'cliente omesso',
        'modelos' => 'Clienti omessi',
        'ayuda' => 'Scegli dove questo cliente non deve comparire: ad esempio i tuoi familiari o i soggiorni di manutenzione. Non si cancella nulla, e la prenotazione occupa sempre le sue date nel calendario.',
        'por' => 'Chi?',
        'por_huesped' => 'Un cliente',
        'por_tipo' => 'Tutti quelli di un tipo',
        'campo' => 'Campo della scheda',
        'campo_ayuda' => 'Il nome del campo personalizzato, come nei colori del calendario. Ad esempio, «Tipo di cliente».',
        'valor' => 'Valore',
        'donde' => 'Dove si omette?',
        'en_aviso' => 'Avviso di arrivi e partenze',
        'en_aviso_ayuda' => 'Non compare nell’avviso per il team.',
        'en_informes' => 'Rapporti e indicatori',
        'en_informes_ayuda' => 'Le sue notti e le sue entrate non contano. Gli incassi e la cassa sì, perché quel denaro è reale.',
        'en_mensajes' => 'Messaggi automatici',
        'en_mensajes_ayuda' => 'Le automazioni non gli arrivano. Quelli che invii a mano sì.',
        'motivo' => 'Motivo',
        'quien' => 'Cliente',
        'vacio' => 'Non ci sono clienti omessi',
    ],

    'listas' => [
        'modelo' => 'lista di controllo',
        'modelos' => 'Liste di controllo',
        'ayuda' => 'Ciò che va controllato per ogni tipo di attività. Compare come caselle nella schermata Pulizie, nelle attività create da ora in poi.',
        'tipo' => 'Tipo di attività',
        'nombre' => 'Nome',
        'puntos' => 'Punti',
        'punto' => 'Punto',
        'anadir' => 'Aggiungi punto',
        'vacio' => 'Non ci sono liste di controllo',
    ],

    'recurrentes' => [
        'modelo' => 'manutenzione ricorrente',
        'modelos' => 'Manutenzione ricorrente',
        'ayuda' => 'Lavori che si ripetono, come cambiare i filtri o disinfestare. La sua attività si crea una settimana prima.',
        'unidad_ayuda' => 'Lasciala vuota se riguarda tutto il luogo.',
        'titulo' => 'Lavoro',
        'descripcion' => 'Descrizione',
        'cada_dias' => 'Ogni quanti giorni',
        'proxima' => 'Prossima data',
        'vacio' => 'Non c’è manutenzione ricorrente',
    ],
];
