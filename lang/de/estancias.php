<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

return [
    'tarifa' => [
        'seccion' => 'Langzeitaufenthalte',
        'seccion_ayuda' => 'Wochenpreis ab 7 Nächten und Monatspreis ab 28. Ein voller Monat kostet so viel, egal ob er 28 oder 31 Nächte hat; zusätzliche Nächte werden mit einem Dreißigstel berechnet. Leer lassen, um pro Nacht zu berechnen.',
        'semana' => 'Wochenpreis',
        'mes' => 'Monatspreis',
        'temporada_ayuda' => 'Eine Saison ohne diese Preise berechnet ihren Preis pro Nacht, auch bei einem Langzeitaufenthalt; Sie können ihr eigene geben.',
    ],
    'impuesto' => [
        'exento' => 'Nicht berechnen ab (Nächte)',
        'exento_ayuda' => 'Ein Aufenthalt mit so vielen Nächten zahlt sie nicht. Manche Orte befreien so Gäste ab 30 Nächten; klären Sie das mit Ihrem Steuerberater. Leer: wird immer berechnet.',
    ],
    'motor' => [
        'semana' => 'Pro Woche: :precio',
        'mes' => 'Pro Monat: :precio',
        'por_persona' => 'pro Person',
        'aplicado' => [
            'semana' => 'Mit Wochenpreis',
            'mes' => 'Mit Monatspreis',
            'temporada' => 'Mit Preis für die ganze Saison',
        ],
    ],
    'cuotas' => [
        'titulo' => 'Zahlungsplan',
        'vacio' => 'Kein Zahlungsplan',
        'vacio_ayuda' => 'Für einen Langzeitaufenthalt: teilt den Gesamtbetrag in monatliche oder wöchentliche Zahlungen auf, mit Erinnerungen und einer Quittung pro Zahlung.',
        'periodo' => 'vom :desde bis :hasta',
        'vence' => 'Fällig',
        'generar' => 'Zahlungsplan erstellen',
        'generar_ayuda' => 'Teilt den Gesamtbetrag der Buchung in gleiche Zahlungen auf; die letzte deckt ab, was keinen vollen Zeitraum füllt. Ersetzt die vorhandenen Zahlungen. Bezahltes wird zuerst auf die frühesten Fälligkeiten angerechnet.',
        'cada_cuanto' => 'Wie oft gezahlt wird',
        'cada_mes' => 'Jeden Monat',
        'cada_semana' => 'Jede Woche',
        'primer_vencimiento' => 'Erste Fälligkeit',
        'generadas' => '{0} Nichts aufzuteilen.|{1} Zahlungsplan fertig, mit :count Zahlung.|[2,*] Zahlungsplan fertig, mit :count Zahlungen.',
        'agregar' => 'Zahlung hinzufügen',
        'editar' => 'Bearbeiten',
        'quitar' => 'Entfernen',
        'concepto' => 'Bezeichnung',
        'concepto_ayuda' => 'So sieht es der Gast, zum Beispiel „Kaution“.',
        'no_cuadra' => 'Achtung: Der Plan ergibt :suma, der Gesamtbetrag der Buchung ist :total. Passen Sie die Zahlungen an oder erstellen Sie den Plan neu.',
        'saldo' => 'Restbetrag des Aufenthalts',
        'recordatorio' => 'Hallo :nombre, hier ist :propiedad. Wir erinnern Sie an Ihre Zahlung von :importe (:periodo), fällig am :fecha. Danke!',
        'estados' => [
            'pagada' => 'Bezahlt',
            'atrasada' => 'Überfällig',
            'parcial' => 'Teilweise',
            'pendiente' => 'Offen',
        ],
    ],
    'publico' => [
        'titulo' => 'Ihre Zahlungen',
        'periodo' => 'Zeitraum',
        'vence' => 'Fällig am :fecha',
    ],
    'pagina' => [
        'titulo' => 'Monatszahlungen',
        'ayuda' => 'Was Langzeitaufenthalte mit Zahlungsplan noch schulden. AlojaFlow erinnert; das Kassieren entscheiden Sie.',
        'recordatorios' => 'E-Mail-Erinnerungen',
        'recordatorios_ayuda' => 'Sendet dem Gast 3 Tage vor jeder Zahlung eine E-Mail und eine weitere am Tag danach, falls sie nicht eingegangen ist. Tage und Text können Sie unter Automatisierungen und Vorlagen ändern.',
        'recordatorios_prender' => 'Erinnerungen einschalten',
        'recordatorios_ok' => 'Erinnerungen eingeschaltet.',
        'vencidas' => 'Überfällig',
        'proximas' => 'In den nächsten :dias Tagen',
        'sin_vencidas' => 'Nichts überfällig.',
        'sin_proximas' => 'Nichts fällig.',
        'falta' => 'Offen',
    ],
    'mensajes' => [
        'cuota_por_vencer' => [
            'nombre' => 'Zahlungserinnerung',
            'asunto' => 'Ihre nächste Zahlung bei {propiedad}',
            'cuerpo' => "Hallo {nombre},\n\nwir erinnern Sie an Ihre Zahlung von {cuota_importe} ({cuota_periodo}), fällig am {cuota_vence}.\n\n{enlace_mi_reserva}\n\nDanke.\n{propiedad}",
        ],
        'cuota_vencida' => [
            'nombre' => 'Überfällige Zahlung',
            'asunto' => 'Ihre Zahlung bei {propiedad} ist noch offen',
            'cuerpo' => "Hallo {nombre},\n\nIhre Zahlung von {cuota_importe} ({cuota_periodo}) war am {cuota_vence} fällig und ist bei uns noch nicht eingegangen. Falls Sie bereits gezahlt haben, senden Sie uns bitte Ihren Beleg und ignorieren Sie diese Nachricht.\n\n{enlace_mi_reserva}\n\nDanke.\n{propiedad}",
        ],
    ],
];
