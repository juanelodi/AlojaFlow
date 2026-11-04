<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

return [
    'documentos' => [
        'ine' => 'INE (mexikanischer Wählerausweis)',
        'pasaporte' => 'Reisepass',
        'licencia' => 'Führerschein',
        'fmm' => 'Einreiseformular (FMM)',
        'dni' => 'DNI',
        'nie' => 'NIE',
        'identificacion' => 'Personalausweis',
        'otro' => 'Sonstiges',
    ],
    'campos' => [
        'procedencia' => 'Herkunftsort',
        'motivo_viaje' => 'Reisegrund',
        'placas' => 'Kfz-Kennzeichen',
        'rfc' => 'RFC (falls Sie eine Rechnung benötigen)',
        'soporte' => 'Dokumentennummer',
        'parentesco' => 'Verwandtschaft (bei Minderjährigen)',
    ],
    'generico' => [
        'aviso_privacidad' => [
            'titulo' => 'Datenschutzhinweis',
            'cuerpo' => "{propiedad} ({domicilio}) ist für Ihre personenbezogenen Daten verantwortlich.\n\nWir verwenden sie, um Ihre Buchung und Ihren Aufenthalt zu verwalten, Sie als Gast zu registrieren, abzurechnen und das Gesetz einzuhalten. Wir geben sie nur an Dienstleister weiter, die uns bei der Leistung helfen, und an Behörden, wenn das Gesetz es verlangt. Wir verkaufen sie nicht.\n\nDas Foto Ihres Ausweises wird bis zu 30 Tage nach Ihrer Abreise aufbewahrt, die Meldedaten ein Jahr.\n\nSie können auf Ihre Daten zugreifen, sie berichtigen oder ihre Löschung verlangen, indem Sie an {correo} schreiben.\n\nDieser Text ist eine Vorlage: Er muss an das Recht Ihres Landes angepasst werden.",
        ],
        'contrato' => [
            'titulo' => 'Beherbergungsbedingungen',
            'cuerpo' => "Diese Bedingungen gelten für Ihren Aufenthalt bei {propiedad}.\n\n1. Es gilt der in Ihrer Buchung angegebene Preis.\n2. An- und Abreise erfolgen zu den mitgeteilten Zeiten.\n3. Nur registrierte Personen dürfen übernachten.\n4. Es gelten die bei der Buchung mitgeteilten Stornobedingungen.\n5. Der Gast haftet für von ihm verursachte Schäden.\n\nBei Beschwerden schreiben Sie an {correo}.",
        ],
        'reglamento' => [
            'titulo' => 'Hausordnung',
            'cuerpo' => 'Zum Wohle aller bitten wir Sie bei {propiedad}, die Ruhe anderer zu respektieren, in den Zimmern nicht zu rauchen, alle übernachtenden Personen zu registrieren und uns jeden Schaden zu melden.',
        ],
        'politica_cancelacion' => [
            'titulo' => 'Stornobedingungen',
            'cuerpo' => 'Sie können bis [Anzahl] Tage vor der Ankunft kostenlos stornieren. Danach werden [Prozent] % des Gesamtbetrags berechnet. Bei Nichterscheinen wird die erste Nacht berechnet. Zum Stornieren schreiben Sie an {correo}.',
        ],
    ],
];
