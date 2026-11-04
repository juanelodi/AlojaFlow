<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

return [
    'fila_protegida' => 'Questo dato viene dall’installazione principale (HotelDruid) e si può modificare solo lì.',
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
        'SUB-099' => 'L’installazione principale ha dato una risposta che AlojaFlow non riconosce.',
    ],
];
