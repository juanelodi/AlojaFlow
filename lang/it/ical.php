<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

return [
    'ocupado' => 'Non disponibile',
    'error_formato' => 'L’indirizzo non ha restituito un calendario iCal valido.',
    'error_tamano' => 'Il calendario è troppo grande.',
    'calendario_externo' => 'Occupato su :fuente',
    'fuentes' => [
        'modelo' => 'calendario di un portale',
        'modelos' => 'Calendari iCal',
        'ayuda' => 'Incolla il link iCal fornito da ogni portale (Airbnb, Booking.com, Vrbo…). Quelle date non si vendono più qui e, se sono in conflitto con una prenotazione, ricevi un avviso.',
        'unidad' => 'Camera',
        'nombre' => 'Nome',
        'nombre_ayuda' => 'Per esempio: Airbnb.',
        'canal' => 'Canale',
        'url' => 'Link iCal del portale',
        'url_ayuda' => 'Viene salvato cifrato perché contiene una chiave del portale.',
        'url_invalida' => 'Questo indirizzo non è consentito: deve essere pubblico.',
        'frecuencia' => 'Leggi ogni',
        'activa' => 'Attivo',
        'estado' => 'Stato',
        'ultima_lectura' => 'Ultima lettura',
        'vacio' => 'Non ci sono ancora calendari di portali.',
        'leer' => 'Leggi ora',
        'leida' => 'Calendario letto: :n date occupate.',
        'no_leida' => 'Impossibile leggere il calendario',
    ],
    'exportar' => [
        'accion' => 'Link per i portali',
        'propiedad' => 'Struttura',
        'ayuda' => 'Incolla in ogni portale il link della sua camera. Mostra solo le date occupate: mai dati degli ospiti.',
        'vacio' => 'Questa struttura non ha camere.',
        'regenerar' => 'Cambia i link',
        'regenerar_ayuda' => 'I link attuali smetteranno di funzionare e dovrai incollare i nuovi in ogni portale. Usalo se li ha qualcuno che non dovrebbe.',
        'regenerados' => 'Link cambiati.',
    ],
    'choques' => [
        'modelo' => 'conflitto',
        'modelos' => 'Conflitti',
        'desde' => 'Dal',
        'hasta' => 'Al',
        'unidad' => 'Camera',
        'reserva' => 'Prenotazione',
        'reserva_n' => 'Prenotazione :numero',
        'con' => 'In conflitto con',
        'nota' => 'Nota',
        'abiertos' => 'Da risolvere',
        'vacio' => 'Nessun conflitto: nessuna camera è occupata due volte.',
        'resolver' => 'Segna come risolto',
    ],
];
