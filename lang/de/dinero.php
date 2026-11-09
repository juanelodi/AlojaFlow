<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

return [
    'segunda' => [
        'moneda' => 'Zweite Währung',
        'moneda_ayuda' => 'Optional, etwa USD. Sie wird neben den Preisen angezeigt, und Bargeld kann darin angenommen werden; alles wird in der Währung der Unterkunft summiert.',
        'tipo' => 'Wechselkurs',
        'tipo_ayuda' => 'Wie viel 1 :segunda in :moneda wert ist. Sie geben ihn ein: ändern Sie ihn, wenn er sich ändert.',
        'recibido_en' => 'Erhalten in',
        'tipo_pago' => 'Wechselkurs dieser Zahlung',
        'se_anota' => 'In der Buchung werden :importe erfasst.',
        'recibido' => ':importe zu :tipo',
        'recibido_titulo' => 'Erhalten',
        'efectivo_en' => 'Bargeld in :moneda (separat zählen)',
    ],
    'politicas' => [
        'no_reembolsable' => 'Nicht erstattbar',
        'no_reembolsable_ayuda' => 'Bei Stornierung, jederzeit, wird der Gesamtbetrag berechnet.',
        'modelo' => 'Richtlinie',
        'modelos' => 'Richtlinien',
        'nombre' => 'Name',
        'dias_gratis' => 'Kostenlos stornierbar bis (Tage vorher)',
        'penalizacion' => 'Gebühr danach (%)',
        'no_show' => 'Gebühr bei Nichterscheinen (%)',
        'deposito' => 'Anzahlung (%)',
        'deposito_plazo' => 'Frist für die Anzahlung (Tage)',
        'texto' => 'Text für den Gast',
        'predeterminada' => 'Standard',
        'predeterminada_ayuda' => 'Gilt, wenn der Kanal der Buchung keine eigene hat.',
        'vacio' => 'Noch keine Richtlinien.',
        'resumen_cancelacion' => 'Laut Richtlinie „:politica“ beträgt die Gebühr :penalizacion. Bereits bezahlt: :pagado; zu erstatten: :devolver; offen: :cobrar.',
        'no_show_accion' => 'Nicht erschienen',
        'no_show_ayuda' => 'Vermerkt, dass der Gast nicht erschienen ist. Bei einer eigenständigen Unterkunft wird außerdem die Buchung storniert und die Nächte werden frei.',
        'no_show_nota' => 'Notiz',
        'no_show_ok' => 'Als nicht erschienen markiert. Gebühr: :importe.',
        'error_no_show' => 'Das ist erst ab dem Ankunftstag möglich und nur, wenn kein Check-in erfasst wurde.',
    ],
];
