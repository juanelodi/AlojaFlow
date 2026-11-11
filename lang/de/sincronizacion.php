<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

return [
    'fila_protegida' => 'Diese Angabe stammt aus der Hauptinstallation und kann nur dort geändert werden.',
    'redireccion' => 'Die Hauptinstallation hat mit einer Weiterleitung nach :destino geantwortet. Prüfen Sie die Adresse.',
    'version_no_admitida' => 'Die Hauptinstallation hat das Schema :version, das AlojaFlow nicht unterstützt (unterstützt werden 3.08 und 3.081).',
    'copia_cortada' => 'Die Kopie kam abgeschnitten an: Das Ende fehlt.',
    'falta_tabla' => 'In der Kopie fehlt die Tabelle :tabla.',
    'destino' => [
        'url_invalida' => 'Die Adresse der Hauptinstallation ist ungültig.',
        'sin_barra_final' => 'Die Adresse muss auf den Ordner der Installation zeigen und mit „/“ enden.',
        'solo_https' => 'Die Adresse muss mit https:// beginnen.',
        'sin_resolver' => 'Der Server :host wurde nicht gefunden.',
        'red_local' => 'Der Server :host befindet sich in einem lokalen Netz; das ist nur erlaubt, wenn die Installation es zulässt und bei der Hauptinstallation „Lokales Netz erlauben“ aktiviert ist.',
    ],
    'respondio' => 'Das geschah :paso, und die Antwort war: „:detalle“.',
    'pasos' => [
        'probar' => 'beim Testen der Verbindung',
        'validar' => 'beim Prüfen der Adresse',
        'crear' => 'beim Anfordern der Sicherung',
        'descargar' => 'beim Herunterladen der Sicherung',
        'borrar' => 'beim Löschen der Sicherung',
        'descomprimir' => 'beim Entpacken der Sicherung',
        'leer' => 'beim Lesen der Sicherung',
        'interno' => 'beim Verarbeiten der Sicherung',
    ],
    'errores' => [
        'SUB-001' => 'Keine Verbindung zur Hauptinstallation möglich.',
        'SUB-002' => 'Das HTTPS-Zertifikat der Hauptinstallation ist ungültig.',
        'SUB-003' => 'Die Hauptinstallation hat den Benutzer oder das Passwort abgelehnt, der Benutzer darf keine Kopien erstellen oder hat die Zwei-Schritt-Verifizierung aktiviert. Die Synchronisierung wird pausiert, um das Konto nicht zu sperren.',
        'SUB-005' => 'Die Hauptinstallation hat ihr Tageslimit für Kopien erreicht. Morgen geht es weiter; es empfiehlt sich, die Häufigkeit zu verringern.',
        'SUB-006' => 'Das abgefragte Jahr existiert in der Hauptinstallation nicht.',
        'SUB-007' => 'Die Hauptinstallation kann die Kopie nicht in ihren Datenordner schreiben.',
        'SUB-008' => 'Die Kopie überschreitet die zulässige Maximalgröße oder kam unvollständig an.',
        'SUB-009' => 'Die komprimierte Kopie kam beschädigt an.',
        'SUB-010' => 'Die Antwort ist keine gültige HotelDruid-Kopie.',
        'SUB-011' => 'Die Schemaversion der Hauptinstallation ist nicht kompatibel.',
        'SUB-012' => 'Die Kopie ist unvollständig.',
        'SUB-013' => 'Die Kopie konnte auf der Hauptinstallation nicht gelöscht werden.',
        'SUB-014' => 'Für diese Hauptinstallation läuft bereits eine Synchronisierung.',
        'SUB-015' => 'Die Hauptinstallation hat zu lange für die Antwort gebraucht.',
        'SUB-016' => 'Die Adresse der Hauptinstallation ist nicht erlaubt.',
        'SUB-017' => 'Die andere Installation hat nicht wie erwartet geantwortet. Prüfen Sie, ob sie AlojaFlow 1.0.12 oder neuer hat und das Modul Unterordnung eingeschaltet ist.',
        'SUB-018' => 'Die andere Installation hat den Verbindungscode abgelehnt. Er wurde vielleicht widerrufen: Fordern Sie einen neuen an.',
        'SUB-098' => 'AlojaFlow konnte nicht verarbeiten, was es von der Hauptinstallation erhalten hat. Der Fehler liegt bei AlojaFlow, nicht bei der Hauptinstallation: Er wurde im Protokoll vermerkt.',
        'SUB-099' => 'Die Hauptinstallation hat eine Antwort gegeben, die AlojaFlow nicht erkennt.',
    ],

    'compartir' => [
        'titulo' => 'Mit einer anderen Installation teilen',
        'ayuda' => 'Eine andere AlojaFlow-Installation kann diese Unterkunft kopieren, nur zum Lesen: Buchungen werden weiterhin hier geändert. Fotos und Dateien werden nie übertragen.',
        'sin_conexiones' => 'Sie teilen sie noch mit niemandem',
        'nombre' => 'Name',
        'nombre_ayuda' => 'Zum Wiedererkennen, zum Beispiel «Zentrale».',
        'documentos' => 'Ausweisdokumente einbeziehen',
        'documentos_ayuda' => 'Ausgeschaltet werden weder die Nummer noch die anderen Dokumentdaten übertragen. Schalten Sie es nur ein, wenn die andere Installation sie braucht.',
        'ultimo_uso' => 'Zuletzt benutzt',
        'estado' => 'Status',
        'activa' => 'Aktiv',
        'revocada' => 'Widerrufen',
        'crear' => 'Teilen',
        'revocar' => 'Widerrufen',
        'revocar_texto' => 'Die andere Installation kann nicht mehr kopieren. Was sie schon kopiert hat, bleibt dort.',
        'codigo_titulo' => 'Verbindungscode',
        'codigo_texto' => 'Kopieren Sie ihn und fügen Sie ihn in der anderen Installation ein, wenn Sie die untergeordnete Unterkunft anlegen. Aus Sicherheitsgründen wird er nicht erneut angezeigt.',
        'codigo_listo' => 'Ich habe ihn kopiert',
    ],

    'formulario' => [
        'tipo' => 'Aus welchem System kopiert wird',
        'codigo' => 'Verbindungscode',
        'codigo_ayuda' => 'Er wird in der anderen Installation erzeugt: beim Bearbeiten der Unterkunft, unter «Mit einer anderen Installation teilen». Ist sie schon verbunden, lassen Sie ihn leer, um den aktuellen zu behalten.',
        'codigo_invalido' => 'Das ist kein AlojaFlow-Verbindungscode. Kopieren Sie ihn vollständig.',
    ],
];
