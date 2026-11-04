<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

return [
    'fila_protegida' => 'Diese Angabe stammt aus der Hauptinstallation (HotelDruid) und kann nur dort geändert werden.',
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
        'SUB-099' => 'Die Hauptinstallation hat eine Antwort gegeben, die AlojaFlow nicht erkennt.',
    ],
];
