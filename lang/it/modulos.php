<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

return [
    'titulo' => 'Moduli',
    'explicacion' => 'Attiva solo ciò che usi: il resto esce dal menu e non dà fastidio. Disattivare un modulo non cancella i suoi dati e, se lo riattivi, è ancora tutto lì. Finché è disattivato, le sue pagine non si aprono e le sue attività si fermano.',
    'siempre' => 'Sempre presenti: Oggi, calendario, prenotazioni, ospiti, utenti, copie di sicurezza, aggiornamenti, privacy e il link al codice sorgente.',
    'guardar' => 'Salva',
    'guardado' => 'Moduli salvati.',
    'repetir' => 'Rispondi di nuovo alle domande',
    'nuevo' => 'Nuovo',
    'requiere' => 'Richiede: :modulos',
    'omitido_al_apagar' => 'Non inviato perché il modulo è stato disattivato.',
    'aviso_nuevos' => 'Con l\'aggiornamento sono arrivati nuovi moduli: :modulos. Arrivano disattivati.',
    'ver' => 'Vedi i moduli',

    'grupos' => [
        'propiedades' => 'Strutture',
        'reservas' => 'Prenotazioni',
        'operacion' => 'Operatività',
        'dinero' => 'Denaro',
        'sistema' => 'Sistema',
    ],

    'lista' => [
        'varias_propiedades' => ['nombre' => 'Più strutture', 'descripcion' => 'Gestire più di un alloggio da questa installazione.'],
        'subordinacion' => ['nombre' => 'Subordinazione', 'descripcion' => 'Copiare le prenotazioni da un altro sistema che resta il principale: HotelDruid, AlojaFlow 1.2.x o un’altra installazione di AlojaFlow. Non vi si scrive mai. Serve anche a condividere le tue strutture con un’altra installazione.'],
        'motor' => ['nombre' => 'Motore di prenotazione', 'descripcion' => 'Una pagina dove gli ospiti cercano le date e prenotano da soli.'],
        'pagos' => ['nombre' => 'Pagamenti online', 'descripcion' => 'Incassare con Stripe o Mercado Pago dal motore di prenotazione.'],
        'ical' => ['nombre' => 'Calendari esterni', 'descripcion' => 'Bloccare le date con i calendari di Airbnb, Booking e Vrbo, e dare loro il tuo.'],
        'cupones' => ['nombre' => 'Coupon', 'descripcion' => 'Codici sconto per le prenotazioni.'],
        'grupos' => ['nombre' => 'Gruppi', 'descripcion' => 'Prenotare più unità insieme e gestirle come una sola.'],
        'limpieza' => ['nombre' => 'Pulizie e manutenzione', 'descripcion' => 'Stato di ogni unità, attività per il personale e regole di pulizia.'],
        'aviso_equipo' => ['nombre' => 'Avviso di arrivi e partenze', 'descripcion' => 'Un bollettino per il team con chi parte, chi arriva e cosa pulire, pronto da inviare su WhatsApp.'],
        'plano' => ['nombre' => 'Pianta della struttura', 'descripcion' => 'Vedere le unità disposte su un disegno del tuo alloggio.'],
        'llegada' => ['nombre' => 'Arrivo online', 'descripcion' => 'L\'ospite compila i suoi dati e accetta i documenti prima di arrivare.'],
        'mensajes' => ['nombre' => 'Messaggi agli ospiti', 'descripcion' => 'Modelli, invii via e-mail e WhatsApp, e messaggi automatici.'],
        'portal' => ['nombre' => 'La mia prenotazione e guida digitale', 'descripcion' => 'Una pagina per ogni ospite, con la prenotazione e ciò che deve sapere, e un codice QR in ogni unità.'],
        'bitacora' => ['nombre' => 'Registro dei turni', 'descripcion' => 'Ciò che un turno lascia al successivo. L’importante compare in Oggi.'],
        'empresas' => ['nombre' => 'Aziende e agenzie', 'descripcion' => 'Chi prenota o paga per i propri ospiti, con condizioni ed estratto conto.'],
        'opiniones' => ['nombre' => 'Opinioni degli ospiti', 'descripcion' => 'Un sondaggio dopo il soggiorno, con link alla tua pagina di recensioni.'],
        'caja' => ['nombre' => 'Cassa e chiusura del giorno', 'descripcion' => 'Casse, movimenti di denaro e la chiusura giornaliera.'],
        'facturacion' => ['nombre' => 'Fatture e ricevute', 'descripcion' => 'Dati fiscali, fatture, ricevute ed esportazioni per il commercialista.'],
        'informes' => ['nombre' => 'Rapporti e indicatori', 'descripcion' => 'Occupazione, entrate e altri rapporti.'],
        'integraciones' => ['nombre' => 'Avvisi ad altri sistemi', 'descripcion' => 'Inviare ciò che accade con le prenotazioni ad altri programmi (webhook). Se non sai cos\'è, non ti serve.'],
    ],

    'en_uso' => [
        'varias_propiedades' => 'Hai già :n struttura in più. Continuerà a funzionare, ma non potrai crearne altre.|Hai già :n strutture in più. Continueranno a funzionare, ma non potrai crearne altre.',
        'subordinacion' => ':n struttura dipende da questo: smetterà di aggiornarsi.|:n strutture dipendono da questo: smetteranno di aggiornarsi.',
        'ical' => ':n calendario smetterà di aggiornarsi e i portali non potranno più leggere i tuoi.|:n calendari smetteranno di aggiornarsi e i portali non potranno più leggere i tuoi.',
        'grupos' => 'Hai già :n gruppo. Le sue prenotazioni restano, ma la schermata dei gruppi non si aprirà.|Hai già :n gruppi. Le loro prenotazioni restano, ma la schermata dei gruppi non si aprirà.',
        'integraciones' => ':n avviso non verrà più inviato.|:n avvisi non verranno più inviati.',
    ],

    'preguntas' => [
        'titulo' => 'Adattiamolo a te',
        'explicacion' => 'Cinque domande veloci per attivare solo ciò che ti serve. Potrai cambiarlo dopo in Sistema → Moduli.',
        'paso' => 'Domanda :paso di :total',
        'atras' => 'Indietro',
        'siguiente' => 'Avanti',
        'terminar' => 'Fine',
        'saltar' => 'Salta e lascia tutto attivo',
        'listo' => 'Fatto. Puoi cambiarlo quando vuoi in Sistema → Moduli.',
        'propiedades' => [
            'titulo' => 'Quanti alloggi gestirai qui?',
            'una' => 'Uno',
            'una_ayuda' => 'Un hotel, delle baite, una casa o più camere in uno stesso luogo.',
            'varias' => 'Più di uno',
            'varias_ayuda' => 'Alloggi diversi, ciascuno con le sue unità e i suoi dati.',
        ],
        'otro_sistema' => [
            'titulo' => 'Usi già un altro sistema che resterà il principale?',
            'no' => 'No, AlojaFlow sarà il mio sistema',
            'no_ayuda' => 'Qui inserisci le tue prenotazioni, tariffe e ospiti.',
            'principal' => 'Sì, HotelDruid o AlojaFlow 1.2.x',
            'principal_ayuda' => 'AlojaFlow copia le sue prenotazioni e aggiunge le proprie funzioni, senza mai scrivervi.',
            'alojaflow' => 'Sì, un’altra installazione di AlojaFlow',
            'alojaflow_ayuda' => 'Questa copia una struttura dall’altra con un codice di connessione, senza mai scrivervi.',
        ],
        'reservas' => [
            'titulo' => 'Cos\'altro ti serve per le prenotazioni?',
            'ayuda' => 'Quelle che ricevi per telefono, su WhatsApp o di persona si possono sempre inserire.',
        ],
        'operacion' => [
            'titulo' => 'Cosa vuoi organizzare qui?',
            'ayuda' => 'Segna ciò che fai ogni giorno, o ciò che vorresti smettere di tenere su carta.',
        ],
        'dinero' => [
            'titulo' => 'Come gestisci il denaro e i documenti?',
            'ayuda' => 'I pagamenti di ogni prenotazione si annotano sempre. Questo è ciò che si aggiunge.',
        ],
        'resumen' => [
            'titulo' => 'Ecco il risultato',
            'ayuda' => 'Se qualcosa non ti convince, torna indietro. Potrai cambiarlo anche più avanti.',
            'prendidos' => 'Attivo',
            'apagados' => 'Disattivato',
            'base' => 'Oggi, calendario, prenotazioni, ospiti e il resto dell\'essenziale',
        ],
    ],

    'pasos' => [
        'titulo' => 'Primi passi',
        'avance' => ':hechos di :total completati',
        'ocultar' => 'Non mostrare più',
        'ir' => 'Vai',
        'copiar' => 'Copia',
        'copiado' => 'Copiato',
        'propiedad' => ['titulo' => 'Crea la tua struttura', 'ayuda' => 'Il nome, il paese e la valuta del tuo alloggio.'],
        'unidades' => ['titulo' => 'Aggiungi le tue unità', 'ayuda' => 'Le camere, baite o appartamenti che affitti.'],
        'tarifa' => ['titulo' => 'Definisci una tariffa', 'ayuda' => 'Quanto chiedi a notte, con le sue stagioni se ne hai.'],
        'motor' => ['titulo' => 'Apri il motore di prenotazione', 'ayuda' => 'Perché gli ospiti prenotino da soli dal tuo sito.'],
        'correo' => ['titulo' => 'Configura l\'e-mail', 'ayuda' => 'Senza e-mail i messaggi non partono e non si può recuperare una password.'],
        'dos_pasos' => ['titulo' => 'Attiva la verifica in due passaggi', 'ayuda' => 'Il tuo account ha accesso a tutto: è quello che conviene proteggere di più.'],
        'cron' => ['titulo' => 'Crea l\'attività pianificata', 'ayuda' => 'Fa partire in orario sincronizzazioni, messaggi e copie. Nel pannello del tuo hosting, crea un\'attività pianificata (cron) con l\'intervallo più breve consentito, con questo comando. Senza di essa, queste attività partono quando qualcuno usa il sistema.'],
        'copia' => ['titulo' => 'Fai la tua prima copia di sicurezza', 'ayuda' => 'E conservala fuori dall\'hosting.'],
        'equipo' => ['titulo' => 'Invita il tuo team', 'ayuda' => 'Ognuno con il suo utente e solo con ciò che deve vedere. Se lavori da solo, ignora questo passo.'],
    ],
];
