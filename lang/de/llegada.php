<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

return [
    'titulo' => 'Online-Check-in',
    'ayuda' => 'Registrieren Sie sich im Voraus und gehen Sie direkt auf Ihr Zimmer. Nur die Unterkunft sieht Ihre Daten.',
    'ya_recibido' => 'Wir haben Ihre Daten bereits erhalten.',
    'titular' => 'Buchende Person',
    'acompanante' => 'Begleitperson :n',
    'nombre' => 'Vorname',
    'apellido1' => 'Nachname',
    'apellido2' => 'Zweiter Nachname',
    'fecha_nacimiento' => 'Geburtsdatum',
    'nacionalidad' => 'Staatsangehörigkeit',
    'documento_tipo' => 'Ausweisart',
    'documento_numero' => 'Ausweisnummer',
    'foto' => 'Foto des Ausweises (optional)',
    'correo' => 'E-Mail',
    'telefono' => 'Telefon',
    'domicilio' => 'Adresse',
    'domicilio_calle' => 'Straße und Hausnummer',
    'domicilio_ciudad' => 'Stadt',
    'domicilio_region' => 'Bundesland oder Region',
    'domicilio_cp' => 'Postleitzahl',
    'domicilio_pais' => 'Land',
    'hora' => 'Wann kommen Sie ungefähr an?',
    'notas' => 'Was wir wissen sollten',
    'acepto' => 'Ich habe gelesen und akzeptiere: :documento',
    'confirmo' => 'Ich bestätige, dass diese Daten korrekt sind.',
    'privacidad_nota' => 'Das Foto Ihres Ausweises wird verschlüsselt gespeichert und kurz nach Ihrer Abreise gelöscht.',
    'enviar' => 'Meine Daten senden',
    'verificar_titulo' => 'Code eingeben',
    'verificar_ayuda' => 'Wir haben einen 6-stelligen Code an :correo gesendet. Bestätigen Sie, dass Sie die Dokumente akzeptieren.',
    'codigo' => 'Code',
    'verificar' => 'Bestätigen',
    'reenviar' => 'Neuen Code senden',
    'codigo_incorrecto' => 'Der Code ist falsch oder abgelaufen.',
    'codigo_no_enviado' => 'Der Code konnte nicht gesendet werden. Versuchen Sie es in einigen Minuten erneut.',
    'codigo_reenviado' => 'Wir haben Ihnen einen neuen Code gesendet.',
    'otp_asunto' => 'Ihr Code für :propiedad',
    'otp_cuerpo' => "Ihr Code lautet: :codigo\n\nEr läuft in :minutos Minuten ab. Wenn Sie das nicht waren, ignorieren Sie diese Nachricht.\n\n:propiedad",
    'listo_titulo' => 'Fertig! Wir haben Ihre Daten erhalten.',
    'listo_ayuda' => 'Wenn Sie etwas ändern müssen, geben Sie der Unterkunft Bescheid. Wir freuen uns auf Sie.',
    'tarjeta' => 'Meldeschein',
    'firma' => 'Unterschrift des Gastes',
    'tarjeta_nota' => 'Vom Gast beim Online-Check-in angegebene Daten.',
    'anonimizado' => 'Anonymisiert #:id',
    'constancia' => [
        'firmante' => 'Akzeptiert von',
        'reserva' => 'Buchung',
        'fecha' => 'Datum und Uhrzeit (UTC)',
        'verificacion' => 'Art der Prüfung',
        'ip' => 'IP-Adresse',
        'version' => 'Dokumentversion',
        'huella' => 'Fingerabdruck des Textes (SHA-256)',
    ],
];
