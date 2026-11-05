<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

return [
    'duplicados' => [
        'titulo' => 'Schede duplicate',
        'ayuda' => 'Schede che possono essere la stessa persona. Controllale prima di unirle: prenotazioni, contatti e note passano a quella che conservi, e non si può annullare.',
        'vacio' => 'Nessuna scheda duplicata trovata.',
        'contacto' => 'Contatto',
        'reservas' => 'Prenotazioni',
        'conservar' => 'Unisci in questa',
        'confirmar' => 'Le altre schede di questo gruppo saranno unite a quella di :nombre ed eliminate. Non si può annullare.',
        'motivos' => [
            'nombre' => 'Stesso nome',
            'correo' => 'Stessa e-mail',
            'telefono' => 'Stesso telefono',
            'documento' => 'Stesso documento',
        ],
    ],
    'anonimizar' => [
        'titulo' => 'Anonimizza per anzianità',
        'ayuda' => 'Chi non viene da anni. I suoi dati personali vengono cancellati e le sue prenotazioni restano a nome di «Anonimizzato», così le statistiche restano corrette. I documenti già salvati non cambiano, e ciò che viene da un’installazione principale si anonimizza lì.',
        'anios' => 'Anni senza soggiorni',
        'encontrados' => '{0} Nessuno manca da così tanto|{1} :n persona manca da così tanto|[2,*] :n persone mancano da così tanto',
        'vacio' => 'Niente da anonimizzare con questo periodo.',
        'accion' => 'Anonimizza tutti',
        'confirmar' => '{1} Verrà anonimizzata :n scheda senza soggiorni da :anios anni. Non si può annullare.|[2,*] Verranno anonimizzate :n schede senza soggiorni da :anios anni, fino a 200 alla volta. Non si può annullare.',
        'hecho' => '{1} :n scheda anonimizzata.|[0,*] :n schede anonimizzate.',
    ],
    'tipo_cliente' => [
        'titulo' => 'Tipo di cliente',
        'ayuda' => 'Dati liberi di questa scheda, come «Tipo: VIP». Se corrispondono a un colore di Colori dei clienti, le sue prenotazioni vengono segnate così nel calendario.',
        'agregar' => 'Aggiungi dato',
    ],
    'facturas' => [
        'formato' => 'Formato',
        'excel' => 'Da aprire in Excel',
        'csv' => 'CSV standard, per altri programmi (virgola, punto decimale)',
    ],
    'franja' => [
        'dias' => 'Giorni di copia',
        'dias_ayuda' => 'Senza nessuno selezionato, tutti i giorni.',
        'desde' => 'Copia dalle',
        'hasta' => 'Fino alle',
        'ayuda' => 'Nell’ora della struttura. Può superare la mezzanotte, come dalle 22:00 alle 06:00. Vuoto, a qualsiasi ora. La copia manuale non ha questo limite.',
    ],
    'region' => [
        'campo' => 'Stato o regione',
        'ayuda' => 'Serve a proporre l’imposta di soggiorno corrispondente.',
    ],
    'colores' => [
        'titulo' => 'Colori del calendario',
        'ayuda' => 'Cambiano solo per te. Vuoto, il colore di sempre.',
    ],
    'registro' => [
        'modelo' => 'registrazione turistica',
        'modelos' => 'Registrazioni turistiche',
        'ayuda' => 'Il numero di registrazione o licenza della struttura o di un’unità. Compare nelle pagine pubbliche e nei documenti, e AlojaFlow avvisa 30 giorni prima della scadenza.',
        'toda' => 'Tutta la struttura',
        'tipo' => 'Tipo',
        'tipo_ayuda' => 'Come è conosciuto: CIN, CIR, licenza…',
        'numero' => 'Numero',
        'vigente_hasta' => 'Valido fino al',
        'vigente_hasta_ayuda' => 'Vuoto se non scade.',
        'mostrar' => 'Mostralo al pubblico',
        'mostrar_ayuda' => 'Nelle pagine pubbliche e nei documenti.',
        'vacio' => 'Ancora nessuna registrazione',
        'responsables' => 'Responsabile e reclami',
        'responsables_ayuda' => 'Ciò che alcuni paesi chiedono di tenere in vista. Compare accanto al numero di registrazione.',
        'responsable' => 'Responsabile',
        'autoridad_quejas' => 'Autorità per i reclami',
        'autoridad_quejas_ayuda' => 'Per esempio, in Messico, la Profeco e la Segreteria del Turismo.',
    ],
];
