<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

return [
    'comision_base' => 'Die Provision berechnet sich auf',
    'comision_base_ayuda' => 'Prüfen Sie Ihren Vertrag: Manche Portale berechnen sie auf den Gesamtbetrag, andere ohne Steuern.',
    'cargos' => 'Weitere Gebühren und Einbehalte',
    'cargos_ayuda' => 'Was der Kanal zusätzlich zur Provision einbehält. Damit sehen Sie Brutto- und Nettogewinn jeder Buchung.',
    'anadir' => 'Gebühr hinzufügen',
    'nombre' => 'Name',
    'clase' => 'Art',
    'calculo' => 'Berechnung',
    'valor' => 'Wert (% oder Betrag)',
    'base' => 'Berechnet auf',
    'base_ayuda' => 'Basis- oder Endtarif: Sehen Sie im Vertrag oder in der Abrechnung nach, wie das Portal rechnet.',
    'sugerir' => 'Gebühren eines Portals vorschlagen',
    'sugerir_ayuda' => 'Fügt die üblichen Gebühren dieses Portals zum Anpassen hinzu. Sie sind ein Richtwert: Ihr Vertrag gilt. Kontoabhängige kommen deaktiviert.',
    'plataforma' => 'Portal',
    'elige_propiedad' => 'Wählen Sie zuerst die Unterkunft.',
    'sugeridos' => [
        'pago_booking' => 'Zahlungsgebühr (nur mit Payments by Booking.com)',
        'visibilidad_booking' => 'Genius oder Preferred Partner',
        'pago_expedia' => 'Inkassogebühr von Expedia',
        'visibilidad_expedia' => 'Sichtbarkeitsprogramme',
        'iva_comision' => 'Umsatzsteuer auf Provision und Gebühren',
        'retencion_isr' => 'Einbehalt Einkommensteuer (mit Steuernummer; ohne 20 %)',
        'retencion_iva' => 'Einbehalt Umsatzsteuer (mit Steuernummer; ohne 16 %)',
    ],
    'utilidad' => [
        'titulo' => 'Gewinn',
        'ingreso' => 'Gast zahlt',
        'impuestos' => 'Eingenommene Steuern',
        'bruta' => 'Brutto',
        'neta' => 'Netto',
        'ayuda' => 'Mit den aktuellen Gebühren des Kanals. Einbehalte lassen sich meist in der Steuererklärung anrechnen: Fragen Sie Ihren Steuerberater.',
        'sin_canal' => 'Die Herkunft dieser Buchung passt zu keinem Kanal: Nur die Provision wird abgezogen.',
    ],
];
