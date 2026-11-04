<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

return [
    'titulo' => 'Check-in online',
    'ayuda' => 'Registrati in anticipo e vai direttamente in camera. Solo la struttura vede i tuoi dati.',
    'ya_recibido' => 'Abbiamo già ricevuto i tuoi dati.',
    'titular' => 'Chi prenota',
    'acompanante' => 'Accompagnatore :n',
    'nombre' => 'Nome',
    'apellido1' => 'Cognome',
    'apellido2' => 'Secondo cognome',
    'fecha_nacimiento' => 'Data di nascita',
    'nacionalidad' => 'Nazionalità',
    'documento_tipo' => 'Tipo di documento',
    'documento_numero' => 'Numero di documento',
    'foto' => 'Foto del documento (facoltativa)',
    'correo' => 'E-mail',
    'telefono' => 'Telefono',
    'domicilio' => 'Indirizzo',
    'domicilio_calle' => 'Via e numero',
    'domicilio_ciudad' => 'Città',
    'domicilio_region' => 'Stato o regione',
    'domicilio_cp' => 'Codice postale',
    'domicilio_pais' => 'Paese',
    'hora' => 'A che ora arrivi, più o meno?',
    'notas' => 'Qualcosa che dovremmo sapere',
    'acepto' => 'Ho letto e accetto: :documento',
    'confirmo' => 'Confermo che questi dati sono corretti.',
    'privacidad_nota' => 'La foto del tuo documento è conservata cifrata e cancellata poco dopo la partenza.',
    'enviar' => 'Invia i miei dati',
    'verificar_titulo' => 'Inserisci il codice',
    'verificar_ayuda' => 'Abbiamo inviato un codice di 6 cifre a :correo. Conferma che sei tu ad accettare i documenti.',
    'codigo' => 'Codice',
    'verificar' => 'Conferma',
    'reenviar' => 'Invia un altro codice',
    'codigo_incorrecto' => 'Il codice è errato o scaduto.',
    'codigo_no_enviado' => 'Non è stato possibile inviare il codice. Riprova tra qualche minuto.',
    'codigo_reenviado' => 'Ti abbiamo inviato un nuovo codice.',
    'otp_asunto' => 'Il tuo codice per :propiedad',
    'otp_cuerpo' => "Il tuo codice è: :codigo\n\nScade tra :minutos minuti. Se non sei stato tu, ignora questo messaggio.\n\n:propiedad",
    'listo_titulo' => 'Fatto! Abbiamo ricevuto i tuoi dati.',
    'listo_ayuda' => 'Se devi cambiare qualcosa, avvisa la struttura. Ti aspettiamo.',
    'tarjeta' => 'Scheda di registrazione',
    'firma' => 'Firma dell’ospite',
    'tarjeta_nota' => 'Dati forniti dall’ospite durante il check-in online.',
    'anonimizado' => 'Anonimizzato #:id',
    'constancia' => [
        'firmante' => 'Accettato da',
        'reserva' => 'Prenotazione',
        'fecha' => 'Data e ora (UTC)',
        'verificacion' => 'Come è stato verificato',
        'ip' => 'Indirizzo IP',
        'version' => 'Versione del documento',
        'huella' => 'Impronta del testo (SHA-256)',
    ],
];
