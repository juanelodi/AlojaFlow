<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

return [
    'modelo' => 'Dokument',
    'modelos' => 'Eigene Dokumente',
    'nombre' => 'Name',
    'alcance' => 'Woraus es entsteht',
    'alcance_ayuda' => 'Das einer Buchung wird aus dieser Buchung erstellt, unter „Weitere Aktionen“. Das mehrerer Buchungen aus dieser Liste, mit Auswahl der Daten.',
    'activo' => 'Aktiv',
    'cuerpo' => 'Text des Dokuments',
    'vacio' => 'Noch keine Dokumente',
    'vacio_ayuda' => 'Ein Vertrag, ein Meldeblatt oder die Ankunftsliste des Tages, mit Ihrem eigenen Text. Sie können mit den Vorlagen beginnen.',
    'variables' => 'Schreiben Sie das Dokument wie jeden Text. Wo Sie eine Angabe möchten, setzen Sie ihre Variable in geschweifte Klammern, etwa {huesped}. Bei einer leeren Sprache wird die der Unterkunft verwendet.',
    'accion' => 'Eigenes Dokument',
    'elegir' => 'Dokument',
    'generar' => 'Erstellen',
    'vista_previa' => 'Vorschau',
    'sin_reservas_previa' => 'Diese Unterkunft hat noch keine Buchungen zum Ausprobieren.',
    'desde' => 'Von',
    'hasta' => 'Bis',
    'criterio' => 'Welche Buchungen',
    'criterios' => [
        'llegadas' => 'Die an diesen Daten ankommen',
        'salidas' => 'Die an diesen Daten abreisen',
        'en_casa' => 'Die an diesen Daten wohnen',
    ],
    'demasiadas' => 'Das sind mehr als :maximo Buchungen für ein Dokument. Wählen Sie weniger Tage.',
    'de_partida' => 'Vorlagen erstellen',
    'de_partida_ayuda' => 'Erstellt einen Vertrag, ein Meldeblatt und eine Ankunftsliste zum Anpassen. Der Vertrag ist nur ein Ausgangspunkt: Lassen Sie ihn von Ihrer Beratung prüfen.',
    'de_partida_listo' => 'Fertig: Prüfen und passen Sie sie an.',
    'si' => 'Ja',

    'campos' => [
        'modelo' => 'eigenes Feld',
        'modelos' => 'Eigene Felder',
        'seccion' => 'Eigene Felder',
        'accion' => 'Eigene Felder',
        'guardados' => 'Felder gespeichert.',
        'nombre' => 'Name',
        'nombre_ayuda' => 'Zum Beispiel: Autokennzeichen, Wunschzimmer.',
        'entidad' => 'Wozu es gehört',
        'tipo' => 'Was es speichert',
        'activo' => 'Aktiv',
        'activo_ayuda' => 'Ein inaktives wird nicht mehr abgefragt, das Gespeicherte bleibt erhalten.',
        'variable' => 'Variable',
        'vacio' => 'Noch keine eigenen Felder',
        'vacio_ayuda' => 'Was Sie zu einem Gast oder einer Buchung festhalten möchten und das System nicht abfragt. Es erscheint in deren Akten, in Dokumenten und in Nachrichten.',
    ],

    'base' => [
        'contrato' => [
            'nombre' => 'Beherbergungsvertrag',
            'partes' => 'Zwischen {propiedad}, Anschrift {domicilio_propiedad}, und {huesped}, Ausweis {huesped_documento}, wird Folgendes vereinbart:',
            'estancia' => 'Aufenthalt in {unidad}, vom {llegada} bis {salida}: {noches} Nächte, für {personas} Personen.',
            'precio' => 'Gesamtpreis: {total}. Bisher bezahlt: {pagado}. Noch offen: {pendiente}.',
            'personas' => 'Nur die in dieser Buchung gemeldeten Personen dürfen wohnen.',
            'danos' => 'Der Gast haftet für Schäden, die er an der Einheit verursacht.',
            'firma' => 'Unterzeichnet am {hoy}:',
        ],
        'registro' => [
            'nombre' => 'Meldeblatt',
            'intro' => 'Buchung {numero} · {unidad} · vom {llegada} bis {salida}.',
            'persona' => 'Person',
            'nacimiento' => 'Geburtsdatum',
            'nacionalidad' => 'Staatsangehörigkeit',
            'documento' => 'Ausweis',
            'firma' => 'Unterschrift von {huesped}:',
        ],
        'llegadas' => [
            'nombre' => 'Ankunftsliste',
            'intro' => 'Vom {desde} bis {hasta}: {cuantas} Buchungen.',
            'llegada' => 'Ankunft',
            'salida' => 'Abreise',
            'unidad' => 'Einheit',
            'huesped' => 'Gast',
            'personas' => 'Personen',
            'pendiente' => 'Noch offen',
        ],
    ],
];
