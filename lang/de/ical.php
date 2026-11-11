<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

return [
    'ocupado' => 'Nicht verfügbar',
    'error_formato' => 'Die Adresse hat keinen gültigen iCal-Kalender zurückgegeben.',
    'error_tamano' => 'Der Kalender ist zu groß.',
    'calendario_externo' => 'Belegt auf :fuente',
    'fuentes' => [
        'modelo' => 'Portal-Kalender',
        'modelos' => 'iCal-Kalender',
        'ayuda' => 'Fügen Sie den iCal-Link jedes Portals ein (Airbnb, Booking.com, Vrbo…). Diese Daten werden hier nicht mehr verkauft, und bei einem Konflikt mit einer Buchung werden Sie benachrichtigt.',
        'unidad' => 'Zimmer',
        'nombre' => 'Name',
        'nombre_ayuda' => 'Zum Beispiel: Airbnb.',
        'canal' => 'Kanal',
        'url' => 'iCal-Link des Portals',
        'url_ayuda' => 'Wird verschlüsselt gespeichert, da er einen Schlüssel des Portals enthält.',
        'url_invalida' => 'Diese Adresse ist nicht erlaubt: Sie muss öffentlich sein.',
        'frecuencia' => 'Lesen alle',
        'activa' => 'Aktiv',
        'estado' => 'Status',
        'ultima_lectura' => 'Zuletzt gelesen',
        'vacio' => 'Noch keine Portal-Kalender.',
        'leer' => 'Jetzt lesen',
        'leida' => 'Kalender gelesen: :n belegte Daten.',
        'no_leida' => 'Der Kalender konnte nicht gelesen werden',
    ],
    'exportar' => [
        'accion' => 'Links für die Portale',
        'propiedad' => 'Unterkunft',
        'ayuda' => 'Fügen Sie in jedem Portal den Link des jeweiligen Zimmers ein. Er zeigt nur belegte Daten: nie Gästedaten.',
        'vacio' => 'Diese Unterkunft hat keine Zimmer.',
        'regenerar' => 'Links ändern',
        'regenerar_ayuda' => 'Die aktuellen Links funktionieren dann nicht mehr, und Sie müssen die neuen in jedem Portal einfügen. Verwenden Sie das, wenn jemand sie hat, der sie nicht haben sollte.',
        'regenerados' => 'Links geändert.',
    ],
    'choques' => [
        'modelo' => 'Konflikt',
        'modelos' => 'Konflikte',
        'desde' => 'Von',
        'hasta' => 'Bis',
        'unidad' => 'Zimmer',
        'reserva' => 'Buchung',
        'reserva_n' => 'Buchung :numero',
        'con' => 'Konflikt mit',
        'nota' => 'Notiz',
        'abiertos' => 'Zu lösen',
        'vacio' => 'Keine Konflikte: Kein Zimmer ist doppelt belegt.',
        'resolver' => 'Als gelöst markieren',
    ],
];
