<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

return [
    'duplicados' => [
        'titulo' => 'Doppelte Einträge',
        'ayuda' => 'Einträge, die dieselbe Person sein können. Prüfen Sie sie vor dem Zusammenführen: Buchungen, Kontakte und Notizen gehen auf den Eintrag über, den Sie behalten. Das lässt sich nicht rückgängig machen.',
        'vacio' => 'Keine doppelten Einträge gefunden.',
        'contacto' => 'Kontakt',
        'reservas' => 'Buchungen',
        'conservar' => 'Hierin zusammenführen',
        'confirmar' => 'Die anderen Einträge dieser Gruppe werden mit dem von :nombre zusammengeführt und gelöscht. Das lässt sich nicht rückgängig machen.',
        'motivos' => [
            'nombre' => 'Gleicher Name',
            'correo' => 'Gleiche E-Mail',
            'telefono' => 'Gleiche Telefonnummer',
            'documento' => 'Gleiches Dokument',
        ],
    ],
    'anonimizar' => [
        'titulo' => 'Nach Alter anonymisieren',
        'ayuda' => 'Personen, die seit Jahren nicht da waren. Ihre personenbezogenen Daten werden gelöscht und ihre Buchungen bleiben unter «Anonymisiert», damit die Statistiken stimmen. Bereits gespeicherte Dokumente ändern sich nicht, und was von einer Hauptinstallation stammt, wird dort anonymisiert.',
        'anios' => 'Jahre ohne Aufenthalt',
        'encontrados' => '{0} Niemand war so lange nicht da|{1} :n Person war so lange nicht da|[2,*] :n Personen waren so lange nicht da',
        'vacio' => 'Mit dieser Frist gibt es nichts zu anonymisieren.',
        'accion' => 'Alle anonymisieren',
        'confirmar' => '{1} :n Eintrag ohne Aufenthalt seit :anios Jahren wird anonymisiert. Das lässt sich nicht rückgängig machen.|[2,*] :n Einträge ohne Aufenthalt seit :anios Jahren werden anonymisiert, bis zu 200 auf einmal. Das lässt sich nicht rückgängig machen.',
        'hecho' => '{1} :n Eintrag anonymisiert.|[0,*] :n Einträge anonymisiert.',
    ],
    'tipo_cliente' => [
        'titulo' => 'Kundentyp',
        'ayuda' => 'Freie Angaben zu diesem Eintrag, wie «Typ: VIP». Passen sie zu einer Farbe unter Kundenfarben, werden die Buchungen im Kalender so markiert.',
        'agregar' => 'Angabe hinzufügen',
    ],
    'facturas' => [
        'formato' => 'Format',
        'excel' => 'Zum Öffnen in Excel',
        'csv' => 'Standard-CSV für andere Programme (Komma, Dezimalpunkt)',
    ],
    'franja' => [
        'dias' => 'Tage zum Kopieren',
        'dias_ayuda' => 'Ohne Auswahl jeden Tag.',
        'desde' => 'Kopieren ab',
        'hasta' => 'Bis',
        'ayuda' => 'In der Zeit der Unterkunft. Darf über Mitternacht gehen, etwa von 22:00 bis 06:00. Leer: jederzeit. Manuelles Kopieren hat diese Grenze nicht.',
    ],
    'region' => [
        'campo' => 'Bundesstaat oder Region',
        'ayuda' => 'Damit wird die passende Beherbergungssteuer vorgeschlagen.',
    ],
    'colores' => [
        'titulo' => 'Kalenderfarben',
        'ayuda' => 'Sie ändern sich nur für Sie. Leer: die übliche Farbe.',
    ],
    'registro' => [
        'modelo' => 'Tourismusregistrierung',
        'modelos' => 'Tourismusregistrierungen',
        'ayuda' => 'Die Registrierungs- oder Lizenznummer der Unterkunft oder einer Einheit. Sie erscheint auf öffentlichen Seiten und in Dokumenten, und AlojaFlow warnt 30 Tage vor dem Ablauf.',
        'toda' => 'Gesamte Unterkunft',
        'tipo' => 'Art',
        'tipo_ayuda' => 'Wie sie genannt wird: RNT, Registrierungsnummer, Lizenz…',
        'numero' => 'Nummer',
        'vigente_hasta' => 'Gültig bis',
        'vigente_hasta_ayuda' => 'Leer, wenn sie nicht abläuft.',
        'mostrar' => 'Öffentlich zeigen',
        'mostrar_ayuda' => 'Auf öffentlichen Seiten und in Dokumenten.',
        'vacio' => 'Noch keine Registrierungen',
        'responsables' => 'Verantwortliche Person und Beschwerden',
        'responsables_ayuda' => 'Was manche Länder sichtbar verlangen. Erscheint neben der Registrierungsnummer.',
        'responsable' => 'Verantwortliche Person',
        'autoridad_quejas' => 'Beschwerdestelle',
        'autoridad_quejas_ayuda' => 'Zum Beispiel in Mexiko die Profeco und das Tourismussekretariat.',
    ],
];
