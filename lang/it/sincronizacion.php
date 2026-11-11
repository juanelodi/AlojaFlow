<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

return [
    'fila_protegida' => 'Questo dato viene dall’installazione principale e si può cambiare solo lì.',
    'redireccion' => 'L’installazione principale ha risposto con un reindirizzamento verso :destino. Controlla l’indirizzo.',
    'version_no_admitida' => 'L’installazione principale ha lo schema :version, che AlojaFlow non supporta (supporta 3.08 e 3.081).',
    'copia_cortada' => 'La copia è arrivata tronca: manca la fine.',
    'falta_tabla' => 'Alla copia manca la tabella :tabla.',
    'destino' => [
        'url_invalida' => 'L’indirizzo dell’installazione principale non è valido.',
        'sin_barra_final' => 'L’indirizzo deve puntare alla cartella dell’installazione e terminare con «/».',
        'solo_https' => 'L’indirizzo deve iniziare con https://.',
        'sin_resolver' => 'Il server :host non è stato trovato.',
        'red_local' => 'Il server :host è in una rete locale; è consentito solo se l’installazione lo autorizza e l’installazione principale ha attivato «Consenti rete locale».',
    ],
    'respondio' => 'È successo :paso, e la risposta è stata: «:detalle».',
    'pasos' => [
        'probar' => 'provando la connessione',
        'validar' => 'controllando l’indirizzo',
        'crear' => 'richiedendo la copia',
        'descargar' => 'scaricando la copia',
        'borrar' => 'cancellando la copia',
        'descomprimir' => 'decomprimendo la copia',
        'leer' => 'leggendo la copia',
        'interno' => 'elaborando la copia',
    ],
    'errores' => [
        'SUB-001' => 'Impossibile connettersi all’installazione principale.',
        'SUB-002' => 'Il certificato HTTPS dell’installazione principale non è valido.',
        'SUB-003' => 'L’installazione principale ha rifiutato l’utente o la password, l’utente non può creare copie oppure ha attiva la verifica in due passaggi. La sincronizzazione resta in pausa per non bloccare l’account.',
        'SUB-005' => 'L’installazione principale ha raggiunto il limite giornaliero di copie. Riprende domani; conviene ridurre la frequenza.',
        'SUB-006' => 'L’anno richiesto non esiste nell’installazione principale.',
        'SUB-007' => 'L’installazione principale non può scrivere la copia nella sua cartella dati.',
        'SUB-008' => 'La copia supera la dimensione massima consentita o è arrivata incompleta.',
        'SUB-009' => 'La copia compressa è arrivata danneggiata.',
        'SUB-010' => 'La risposta non è una copia valida di HotelDruid.',
        'SUB-011' => 'La versione dello schema dell’installazione principale non è compatibile.',
        'SUB-012' => 'La copia è incompleta.',
        'SUB-013' => 'Impossibile eliminare la copia nell’installazione principale.',
        'SUB-014' => 'C’è già una sincronizzazione in corso per questa installazione principale.',
        'SUB-015' => 'L’installazione principale ha impiegato troppo tempo a rispondere.',
        'SUB-016' => 'L’indirizzo dell’installazione principale non è consentito.',
        'SUB-017' => 'L’altra installazione non ha risposto come previsto. Controlla che abbia AlojaFlow 1.0.12 o successivo e il modulo di subordinazione attivo.',
        'SUB-018' => 'L’altra installazione ha rifiutato il codice di connessione. Potrebbe essere stato revocato: chiedine uno nuovo.',
        'SUB-098' => 'AlojaFlow non è riuscito a elaborare ciò che ha ricevuto dall’installazione principale. Il guasto è di AlojaFlow, non dell’installazione principale: è stato annotato nel registro.',
        'SUB-099' => 'L’installazione principale ha dato una risposta che AlojaFlow non riconosce.',
    ],

    'compartir' => [
        'titulo' => 'Condividi con un’altra installazione',
        'ayuda' => 'Un’altra installazione di AlojaFlow può copiare questa struttura, in sola lettura: le prenotazioni si cambiano sempre qui. Foto e file non viaggiano mai.',
        'sin_conexiones' => 'Non la condividi ancora con nessuno',
        'nombre' => 'Nome',
        'nombre_ayuda' => 'Per riconoscerla, ad esempio «Sede centrale».',
        'documentos' => 'Includi i documenti d’identità',
        'documentos_ayuda' => 'Se disattivato, non viaggiano né il numero né gli altri dati del documento. Attivalo solo se servono all’altra installazione.',
        'ultimo_uso' => 'Ultimo uso',
        'estado' => 'Stato',
        'activa' => 'Attiva',
        'revocada' => 'Revocata',
        'crear' => 'Condividi',
        'revocar' => 'Revoca',
        'revocar_texto' => 'L’altra installazione non potrà più copiare. Ciò che ha già copiato resta lì.',
        'codigo_titulo' => 'Codice di connessione',
        'codigo_texto' => 'Copialo e incollalo nell’altra installazione, quando crei la struttura subordinata. Per sicurezza non viene più mostrato.',
        'codigo_listo' => 'L’ho copiato',
    ],

    'formulario' => [
        'tipo' => 'Da quale sistema copiare',
        'codigo' => 'Codice di connessione',
        'codigo_ayuda' => 'Si genera nell’altra installazione: modificando la struttura, in «Condividi con un’altra installazione». Se è già collegata, lascialo vuoto per conservare quello attuale.',
        'codigo_invalido' => 'Questo non è un codice di connessione di AlojaFlow. Copialo per intero.',
    ],
];
