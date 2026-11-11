<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

return [
    'titulo' => 'Plan',
    'vacio' => 'Noch kein Plan vorhanden. Erstellen Sie einen und platzieren Sie Ihre Zimmer.',
    'nuevo' => 'Neuer Plan',
    'nombre' => 'Plan',
    'nombre_ayuda' => 'Zum Beispiel: Erdgeschoss.',
    'imagen' => 'Bild des Plans (optional)',
    'imagen_ayuda' => 'Eine Zeichnung oder ein Foto. Ohne Bild werden die Zimmer auf einem einfarbigen Hintergrund platziert.',
    'creado' => 'Plan erstellt: Ziehen Sie jedes Zimmer an seinen Platz.',
    'editar' => 'Zimmer platzieren',
    'terminar' => 'Beenden',
    'editar_ayuda' => 'Ziehen Sie jedes Zimmer an seinen Platz. Es wird automatisch gespeichert.',
    'borrar' => 'Diesen Plan löschen',
    'estados' => [
        'libre' => 'Frei',
        'llega' => 'Anreise heute',
        'sale' => 'Abreise heute',
        'ocupada' => 'Belegt',
    ],
    'colores' => [
        'modelo' => 'Kundenfarbe',
        'modelos' => 'Kundenfarben',
        'ayuda' => 'Färbt die Buchungen im Kalender nach einer Angabe im Gästedatensatz, zum Beispiel „Kundentyp: Firma“.',
        'campo' => 'Feld des Datensatzes',
        'campo_ayuda' => 'Der Name des benutzerdefinierten Felds, wie er im Datensatz steht.',
        'valor' => 'Wert',
        'color' => 'Farbe',
        'vacio' => 'Noch keine Farben.',
    ],
];
