<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

return [
    'TipoEmpresa' => ['empresa' => 'Unternehmen', 'agencia' => 'Agentur'],
    'ModoPropiedad' => [
        'nativa' => 'Eigenständig',
        'subordinada' => 'Untergeordnet',
    ],
    'MetodoPago' => [
        'efectivo' => 'Bar',
        'tarjeta' => 'Karte',
        'transferencia' => 'Überweisung',
        'otro' => 'Sonstiges',
    ],
    'ModoTarifa' => [
        'por_noche' => 'Pro Nacht (mit zusätzlicher Person)',
        'por_persona' => 'Pro Person',
    ],
    'BaseCargo' => [
        'por_estancia' => 'Pro Aufenthalt',
        'por_noche' => 'Pro Nacht',
        'por_persona' => 'Pro Person',
        'por_persona_noche' => 'Pro Person und Nacht',
        'porcentaje_alojamiento' => 'Prozent der Unterkunft',
    ],
    'TipoMovimiento' => [
        'gasto' => 'Ausgabe',
        'ingreso' => 'Einnahme',
    ],
    'TipoCupon' => [
        'porcentaje' => 'Prozentsatz',
        'importe' => 'Fester Betrag',
    ],
    'PaquetePais' => [
        'mexico' => 'Mexiko',
        'espana' => 'Spanien',
        'estados_unidos' => 'Vereinigte Staaten',
        'generico' => 'Allgemein',
    ],
    'EstadoPrincipal' => [
        'activa' => 'Aktiv',
        'pausada' => 'Pausiert',
    ],
    'MetodoPeticion' => [
        'post' => 'POST (empfohlen)',
        'get' => 'GET (Kompatibilitätsmodus)',
    ],
    'EstadoEjecucion' => [
        'en_curso' => 'Läuft',
        'correcta' => 'Erfolgreich',
        'fallida' => 'Fehlgeschlagen',
        'omitida' => 'Übersprungen',
    ],
    'OrigenEjecucion' => [
        'programada' => 'Geplant',
        'manual' => 'Manuell',
    ],
    'TipoReserva' => [
        'reserva' => 'Buchung',
        'bloqueo' => 'Sperre',
    ],
    'EstadoReserva' => [
        'sin_confirmar' => 'Unbestätigt',
        'confirmada' => 'Bestätigt',
        'cancelada' => 'Storniert',
    ],
    'TipoEventoReserva' => [
        'creada' => 'Neu',
        'modificada' => 'Geändert',
        'cancelada' => 'Storniert',
        'eliminada' => 'In der Hauptinstallation gelöscht',
        'reactivada' => 'Reaktiviert',
    ],
    'NivelAviso' => [
        'informacion' => 'Information',
        'advertencia' => 'Warnung',
        'error' => 'Fehler',
    ],
    'TipoContacto' => [
        'telefono' => 'Telefon',
        'correo' => 'E-Mail',
        'fax' => 'Fax',
    ],
    'TipoPrincipal' => ['hoteldruid' => 'HotelDruid oder AlojaFlow 1.2.x', 'alojaflow' => 'Eine andere AlojaFlow-Installation'],
    'OrigenFila' => [
        'hoteldruid' => 'HotelDruid',
        'alojaflow' => 'AlojaFlow', 'otra_instalacion' => 'Andere Installation'],
    'EstadoActualizacion' => [
        'preparada' => 'Vorbereitet',
        'aplicando' => 'Wird angewendet',
        'archivos_aplicados' => 'Nicht abgeschlossen',
        'completada' => 'Abgeschlossen',
        'fallida' => 'Fehlgeschlagen',
        'revertida' => 'Zurückgesetzt',
    ],
    'EstadoLimpieza' => [
        'limpia' => 'Sauber',
        'sucia' => 'Schmutzig',
        'inspeccion' => 'Zu prüfen',
    ],
    'TipoTarea' => [
        'limpieza_salida' => 'Endreinigung',
        'limpieza_estancia' => 'Zwischenreinigung',
        'limpieza_profunda' => 'Grundreinigung',
        'inspeccion' => 'Kontrolle',
        'mantenimiento' => 'Wartung',
        'otra' => 'Sonstige',
    ],
    'EstadoTarea' => [
        'pendiente' => 'Offen',
        'en_curso' => 'In Arbeit',
        'hecha' => 'Erledigt',
        'cancelada' => 'Abgebrochen',
    ],
    'FrecuenciaLimpieza' => [
        'diaria' => 'Jeden Tag',
        'semanal' => 'An bestimmten Wochentagen',
        'fechas' => 'Gewählte Tage',
    ],
    'ClaseArchivo' => [
        'documento_identidad' => 'Ausweis',
        'firma' => 'Unterschrift',
        'copia_aceptacion' => 'Unterschriebene Kopie',
        'foto_tarea' => 'Foto',
        'plano' => 'Plan',
        'factura_xml' => 'Rechnung (XML)',
        'factura_pdf' => 'Rechnung (PDF)',
        'exportacion' => 'Export',
        'comprobante' => 'Zahlungsbeleg',
        'foto_publica' => 'Foto der öffentlichen Seite',
    ],
    'CanalEnvio' => [
        'correo' => 'E-Mail',
        'whatsapp_enlace' => 'WhatsApp',
        'webhook' => 'Benachrichtigung an anderes System',
    ],
    'EstadoEnvio' => [
        'pendiente' => 'Zu senden',
        'enviado' => 'Gesendet',
        'fallido' => 'Fehlgeschlagen',
        'omitido' => 'Nicht gesendet',
        'manual' => 'Bereit zum Kopieren',
    ],
    'DisparadorAutomatizacion' => [
        'reserva' => [
            'creada' => 'Beim Anlegen der Buchung',
            'confirmada' => 'Bei der Bestätigung',
            'modificada' => 'Bei einer Änderung',
            'cancelada' => 'Bei der Stornierung',
        ],
        'antes_llegada' => 'Tage vor der Ankunft',
        'llegada' => 'Am Ankunftstag',
        'salida' => 'Am Abreisetag',
        'despues_salida' => 'Tage nach der Abreise',
    ],
    'AccionAutomatizacion' => [
        'crear_tarea' => 'Aufgabe für das Team anlegen',
        'nota_bitacora' => 'Wichtige Notiz im Schichtbuch hinterlassen',
        'correo_huesped' => 'E-Mail an den Gast',
        'correo_fijo' => 'E-Mail an eine feste Adresse',
    ],
    'TipoEnlace' => [
        'pago' => 'Zahlung',
        'opinion' => 'Meinung',
        'ical' => 'iCal-Kalender',
        'llegada' => 'Online-Check-in',
        'contrato' => 'Vertrag',
        'factura' => 'Rechnung',
        'grupo' => 'Gruppe',
        'mi_reserva' => 'Meine Buchung',
        'guia' => 'Führer',
    ],
    'EstadoFuenteIcal' => [
        'sin_leer' => 'Ungelesen',
        'correcta' => 'Korrekt',
        'error' => 'Fehlerhaft',
    ],
    'TipoChoque' => [
        'bloqueo_reserva' => 'Portal und Buchung',
        'reserva_reserva' => 'Zwei Buchungen',
    ],
    'TipoDocumentoLegal' => [
        'aviso_privacidad' => 'Datenschutzhinweis',
        'contrato' => 'Beherbergungsbedingungen',
        'reglamento' => 'Hausordnung',
        'politica_cancelacion' => 'Stornobedingungen',
        'contrato_grupo' => 'Gruppenvertrag',
    ],
    'EstadoRegistroLlegada' => [
        'pendiente' => 'Nicht abgeschlossen',
        'recibido' => 'Zu prüfen',
        'revisado' => 'Geprüft',
    ],
    'VerificacionAceptacion' => [
        'casilla' => 'Kästchen angekreuzt',
        'otp_correo' => 'Code per E-Mail',
    ],
    'TipoSolicitudPrivacidad' => [
        'exportacion' => 'Export',
        'anonimizacion' => 'Anonymisierung',
    ],
    'EstadoFactura' => [
        'emitida' => 'Ausgestellt',
        'registrada' => 'Gestempelt',
        'cancelada' => 'Storniert',
    ],
    'TipoDocumentoFiscal' => [
        'factura' => 'Rechnung',
        'recibo' => 'Quittung ohne steuerliche Gültigkeit',
    ],
    'ModoFiscal' => [
        'pruebas' => 'Test',
        'produccion' => 'Produktion',
    ],
    'EstadoGrupo' => [
        'tentativo' => 'Vorläufig',
        'confirmado' => 'Bestätigt',
        'cancelado' => 'Storniert',
    ],
    'ModoPrecioGrupo' => [
        'tarifa' => 'Der Tarif jeder Einheit',
        'total' => 'Ein Gesamtpreis, nach Nächten verteilt',
        'por_capacidad' => 'Ein Preis pro Nacht nach Belegung',
    ],
    'EstadoComprobante' => [
        'por_confirmar' => 'Zu bestätigen',
        'confirmado' => 'Bestätigt',
        'rechazado' => 'Abgelehnt',
    ],
    'MotivoCancelacionCfdi' => [
        '01' => '01 · Mit Fehlern, mit Ersatzrechnung',
        '02' => '02 · Mit Fehlern, ohne Ersatzrechnung',
        '03' => '03 · Der Vorgang fand nicht statt',
        '04' => '04 · Vorgang in einer Sammelrechnung enthalten',
    ],
    'EstadoEnvioAutoridad' => [
        'pendiente' => 'Zu senden',
        'falta_datos' => 'Daten fehlen',
        'enviado' => 'Gesendet, noch keine Antwort',
        'aceptado' => 'Gemeldet',
        'rechazado' => 'Abgelehnt',
        'por_anular' => 'Zu stornieren',
        'anulando' => 'Wird storniert',
        'anulado' => 'Storniert',
    ],
    'MomentoGuia' => [
        'siempre' => 'Immer',
        'antes' => 'Vor der Anreise',
        'durante' => 'Während des Aufenthalts',
        'despues' => 'Bei der Abreise',
    ],
    'TipoSeccionGuia' => [
        'llegada' => 'Anreise',
        'wifi' => 'WLAN',
        'reglas' => 'Hausregeln',
        'equipo' => 'Nutzung der Ausstattung',
        'recomendaciones' => 'Empfehlungen',
        'contactos' => 'Kontakte',
        'salida' => 'Vor der Abreise',
        'otro' => 'Sonstiges',
    ],
];
