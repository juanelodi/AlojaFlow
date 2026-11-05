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
    'pago_duplicado' => 'Un pagamento online della prenotazione :reserva supera quanto dovuto. Controllalo in Pagamenti e, se serve, rimborsa la differenza dal tuo conto del gateway.',
    'pago_en_cancelada' => 'È arrivato un pagamento online per la prenotazione :reserva, che era già annullata. Decidi con l’ospite se riattivarla o rimborsarlo.',
    'pago_sin_lugar' => 'È stato ricevuto un pagamento online della prenotazione :reserva, ma le sue notti erano già state vendute. Risolvi con l’ospite.',
    'motor_saturado' => 'Sono arrivate troppe prenotazioni online in un’ora e il modulo è stato fermato per un’ora. Controlla in Prenotazioni se sono reali; quelle non confermate si annullano da sole.',
    'copia_no_borrada' => 'Non è stato possibile eliminare la copia nell’installazione principale; resta nella sua cartella dati/. Controlla i permessi di quella cartella.',
    'registro_por_vencer' => 'Un numero di registrazione o licenza di questa struttura scade tra meno di 30 giorni, o è già scaduto. Rinnovalo e aggiorna la data in Registrazioni turistiche.',
    'comprobantes_por_revisar' => 'Ci sono ricevute di pagamento da controllare. Vedile in Finanze → Ricevute di pagamento.',
    'grupo_habitaciones_liberadas' => 'Del gruppo :grupo sono state liberate :habitaciones camere senza responsabile, perché il termine è passato.',
    'autoridad_rechazadas' => 'Il registro dei viaggiatori ha rifiutato delle comunicazioni. Controllale in Operazioni → Registro dei viaggiatori.',
    'autoridad_faltan_datos' => 'Ad alcune comunicazioni del registro mancano dati. Completali in Operazioni → Registro dei viaggiatori.',
    'autoridad_atrasadas' => 'Ci sono comunicazioni al registro fuori termine: sono 24 ore. Gestiscile in Operazioni → Registro dei viaggiatori.',
    'autoridad_sin_conexion' => 'Impossibile contattare il registro dopo vari tentativi. Controlla la connessione in Operazioni → Registro dei viaggiatori.',
];
