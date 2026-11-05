<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

return [
    'aviso' => [
        'titulo' => 'Meldung zu An- und Abreisen',
        'desde' => 'Ab',
        'dias' => 'Tage',
        'anadir' => 'Zu jeder Einheit hinzufügen',
        'nombre' => 'Name',
        'personas' => 'Personen',
        'origen' => 'Herkunft',
        'noches' => 'Nächte',
        'telefono' => 'Telefon',
        'comentario' => 'Kommentar',
        'mostrar_limpieza' => 'Reinigung anzeigen',
        'texto' => 'Text der Meldung (Sie können ihn vor dem Senden anpassen)',
        'telefono_destino' => 'WhatsApp des Empfängers',
        'salen' => 'Abreisen',
        'llegan' => 'Anreisen',
        'limpieza' => 'Reinigung',
        'n_personas' => ':count Person|:count Personen',
        'sin_movimiento' => 'An diesen Tagen keine Bewegung.',
        'omitidos_ayuda' => 'Soll ein interner oder Wartungskunde hier nicht erscheinen? Markieren Sie ihn unter',
    ],

    'omisiones' => [
        'modelo' => 'ausgelassener Kunde',
        'modelos' => 'Ausgelassene Kunden',
        'ayuda' => 'Wählen Sie, wo dieser Kunde nicht erscheinen soll: zum Beispiel Ihre Angehörigen oder Wartungsaufenthalte. Nichts wird gelöscht, und die Buchung belegt weiterhin ihre Tage im Kalender.',
        'por' => 'Wer?',
        'por_huesped' => 'Ein Kunde',
        'por_tipo' => 'Alle eines Typs',
        'campo' => 'Feld der Karteikarte',
        'campo_ayuda' => 'Der Name des benutzerdefinierten Felds, wie bei den Kalenderfarben. Zum Beispiel «Kundentyp».',
        'valor' => 'Wert',
        'donde' => 'Wo wird ausgelassen?',
        'en_aviso' => 'Meldung zu An- und Abreisen',
        'en_aviso_ayuda' => 'Erscheint nicht in der Meldung für das Team.',
        'en_informes' => 'Berichte und Kennzahlen',
        'en_informes_ayuda' => 'Seine Nächte und Einnahmen zählen nicht. Zahlungen und Kasse schon, denn dieses Geld ist real.',
        'en_mensajes' => 'Automatische Nachrichten',
        'en_mensajes_ayuda' => 'Automatisierungen erreichen ihn nicht. Von Hand gesendete schon.',
        'motivo' => 'Grund',
        'quien' => 'Kunde',
        'vacio' => 'Es gibt keine ausgelassenen Kunden',
    ],

    'listas' => [
        'modelo' => 'Checkliste',
        'modelos' => 'Checklisten',
        'ayuda' => 'Was bei jedem Aufgabentyp zu prüfen ist. Erscheint als Kästchen in der Ansicht Reinigung, bei Aufgaben, die ab jetzt erstellt werden.',
        'tipo' => 'Aufgabentyp',
        'nombre' => 'Name',
        'puntos' => 'Punkte',
        'punto' => 'Punkt',
        'anadir' => 'Punkt hinzufügen',
        'vacio' => 'Es gibt keine Checklisten',
    ],

    'recurrentes' => [
        'modelo' => 'wiederkehrende Wartung',
        'modelos' => 'Wiederkehrende Wartung',
        'ayuda' => 'Arbeiten, die sich wiederholen, wie Filter wechseln oder Schädlinge bekämpfen. Die Aufgabe wird eine Woche vorher erstellt.',
        'unidad_ayuda' => 'Leer lassen, wenn es den ganzen Ort betrifft.',
        'titulo' => 'Arbeit',
        'descripcion' => 'Beschreibung',
        'cada_dias' => 'Alle wie viele Tage',
        'proxima' => 'Nächstes Datum',
        'vacio' => 'Es gibt keine wiederkehrende Wartung',
    ],
];
