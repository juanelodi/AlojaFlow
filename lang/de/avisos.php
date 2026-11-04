<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

return [
    'sincronizacion_fallida' => 'Die Synchronisierung mit der Hauptinstallation schlägt fehl (:codigo): :mensaje',
    'principal_sin_inicio_sesion' => 'Die Hauptinstallation :url verlangt keine Anmeldung: Jeder, der die Adresse kennt, kann ihre Datenkopie herunterladen. Aktivieren Sie die Anmeldung in HotelDruid (Konfigurieren → Benutzer).',
    'ical_falla' => 'Der Kalender „:fuente“ konnte seit einiger Zeit nicht gelesen werden. Prüfen Sie ihn unter iCal-Kalender.',
    'choques' => 'Es gibt doppelt belegte Daten. Prüfen Sie sie unter Konflikte.',
    'envios_fallidos' => 'Einige Nachrichten konnten nach mehreren Versuchen nicht gesendet werden. Prüfen Sie sie unter Nachrichten.',
    'pago_no_coincide' => 'Eine Online-Zahlung der Buchung :reserva stimmt nicht mit dem erwarteten Betrag überein. Prüfen Sie sie in Ihrem Konto beim Zahlungsanbieter.',
    'pago_duplicado' => 'Eine Online-Zahlung für die Buchung :reserva übersteigt den offenen Betrag. Prüfen Sie sie unter Zahlungen und erstatten Sie bei Bedarf die Differenz über Ihr Konto beim Zahlungsanbieter.',
    'pago_en_cancelada' => 'Für die bereits stornierte Buchung :reserva ist eine Online-Zahlung eingegangen. Entscheiden Sie mit dem Gast, ob Sie sie reaktivieren oder das Geld erstatten.',
    'pago_sin_lugar' => 'Für die Buchung :reserva ist eine Online-Zahlung eingegangen, aber ihre Nächte waren bereits verkauft. Klären Sie das mit dem Gast.',
    'motor_saturado' => 'In einer Stunde sind zu viele Online-Buchungen eingegangen, und das Formular wurde für eine Stunde angehalten. Prüfen Sie unter Buchungen, ob sie echt sind; nicht bestätigte werden automatisch storniert.',
    'copia_no_borrada' => 'Die Kopie konnte auf der Hauptinstallation nicht gelöscht werden; sie bleibt in deren Ordner dati/. Prüfen Sie die Berechtigungen dieses Ordners.',
];
