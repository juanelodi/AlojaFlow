<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

return [
    'titulo' => 'Sicherungen',
    'explicacion' => 'Jede Sicherung enthält die gesamte Datenbank und die hochgeladenen Dateien in einem Zip, das Sie herunterladen und hier oder in einer anderen Installation wieder laden können.',
    'estado_automaticas' => 'Jede Nacht wird eine automatische Sicherung erstellt, und die letzten :cuantas bleiben erhalten.',
    'estado_manuales' => 'Die automatischen Sicherungen sind ausgeschaltet: Sie werden nur auf Anforderung erstellt.',
    'aviso_fuera' => 'Diese Sicherungen liegen auf demselben Server. Laden Sie gelegentlich eine herunter und bewahren Sie sie zusammen mit der Konfigurationsdatei an einem anderen Ort auf: Nur das schützt Sie, wenn der Server verloren geht.',
    'crear' => 'Jetzt Sicherung erstellen',
    'creada' => 'Sicherung erstellt: :nombre',
    'subir' => 'Sicherung hochladen',
    'subir_ayuda' => 'Laden Sie ein zuvor heruntergeladenes Zip dieser oder einer anderen AlojaFlow-Installation hoch. Es wird nur gespeichert: Um es zu verwenden, klicken Sie anschließend auf „Wiederherstellen“.',
    'archivo' => 'Sicherungsdatei (Zip)',
    'subida' => 'Sicherung hochgeladen: :nombre',
    'ajustes' => 'Einstellungen',
    'automaticas' => 'Jede Nacht eine automatische Sicherung erstellen',
    'automaticas_ayuda' => 'Benötigt die geplante Aufgabe des Servers oder dass jemand an diesem Tag das System nutzt.',
    'conservar' => 'Wie viele Sicherungen behalten',
    'conservar_ayuda' => 'Beim Erstellen einer neuen werden die ältesten gelöscht. Jede Sicherung enthält personenbezogene Daten: Bewahren Sie nicht mehr auf als nötig.',
    'ajustes_guardados' => 'Einstellungen gespeichert.',
    'descargar_env' => 'Konfiguration herunterladen',
    'descargar_env_ayuda' => 'Die Datei .env enthält den Schlüssel, mit dem die gespeicherten Dokumente und Passwörter verschlüsselt werden, sowie das Passwort der Datenbank. Ohne diesen Schlüssel kann eine auf einem anderen Server wiederhergestellte Sicherung diese Daten nicht lesen. Bewahren Sie sie an einem sicheren Ort getrennt von den Sicherungen auf.',
    'contrasena' => 'Ihr Passwort',
    'lista' => 'Gespeicherte Sicherungen',
    'vacio' => 'Noch keine Sicherungen.',
    'fecha' => 'Datum',
    'origen' => 'Herkunft',
    'version' => 'Version',
    'tamano' => 'Größe',
    'acciones' => 'Aktionen',
    'otra_clave' => 'Aus einer anderen Installation: Ihre verschlüsselten Daten können mit dem aktuellen Schlüssel nicht gelesen werden.',
    'origenes' => [
        'manual' => 'Manuell',
        'programada' => 'Automatisch',
        'antes_de_restaurar' => 'Vor der Wiederherstellung',
    ],
    'descargar' => 'Herunterladen',
    'restaurar' => 'Wiederherstellen',
    'restaurar_titulo' => 'Diese Sicherung wiederherstellen?',
    'restaurar_texto' => 'Alle aktuellen Daten werden durch die der Sicherung ersetzt. Zuvor wird eine weitere Sicherung des aktuellen Stands gespeichert, falls Sie zurückmüssen. Danach müssen Sie sich erneut anmelden.',
    'eliminar' => 'Löschen',
    'eliminar_titulo' => 'Diese Sicherung löschen?',
    'eliminada' => 'Sicherung gelöscht.',
    'error' => [
        'no_escribe' => 'Die Sicherung konnte nicht in storage/app/private/copias geschrieben werden. Prüfen Sie die Berechtigungen und den freien Speicher.',
        'no_existe' => 'Diese Sicherung gibt es nicht mehr.',
        'no_es_copia' => 'Diese Datei ist keine AlojaFlow-Sicherung oder sie ist beschädigt.',
        'mas_nueva' => 'Diese Sicherung stammt aus der Version :version, die neuer ist als die installierte. Aktualisieren Sie AlojaFlow, bevor Sie sie wiederherstellen.',
        'restaurar' => 'Die Sicherung konnte nicht wiederhergestellt werden; die Daten sind wieder im vorherigen Zustand. Details: :detalle',
    ],
];
