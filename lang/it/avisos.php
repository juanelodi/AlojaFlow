<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

return [
    'sincronizacion_fallida' => 'La sincronizzazione con l’installazione principale non riesce (:codigo): :mensaje',
    'principal_sin_inicio_sesion' => 'L’installazione principale :url non richiede l’accesso: chiunque conosca l’indirizzo può scaricare la sua copia dei dati. Attivalo in HotelDruid (Configura → Utenti).',
    'ical_falla' => 'Da un po\' di tempo non si riesce a leggere il calendario «:fuente». Controllalo in Calendari iCal.',
    'choques' => 'Ci sono date occupate due volte. Controllale in Conflitti.',
    'envios_fallidos' => 'Alcuni messaggi non sono stati inviati dopo vari tentativi. Controllali in Messaggi.',
    'pago_no_coincide' => 'Un pagamento online della prenotazione :reserva non corrisponde all’importo previsto. Controllalo nel tuo account del gateway.',
    'pago_sin_lugar' => 'È stato ricevuto un pagamento online della prenotazione :reserva, ma le sue notti erano già state vendute. Risolvi con l’ospite.',
    'copia_no_borrada' => 'Non è stato possibile eliminare la copia nell’installazione principale; resta nella sua cartella dati/. Controlla i permessi di quella cartella.',
];
