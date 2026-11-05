<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

return [
    'nombres' => [
        'operacion' => [
            'ver' => 'Vedere le schermate operative (Oggi, calendario, pulizie)',
        ],
        'datos' => [
            'ver' => 'Vedere prenotazioni, ospiti e altri dati',
        ],
        'indicadores' => [
            'ver' => 'Vedere indicatori e report',
        ],
        'sincronizacion' => [
            'gestionar' => 'Gestire la sincronizzazione con l’installazione principale',
        ],
        'reservas' => [
            'gestionar' => 'Creare e modificare prenotazioni, ospiti e pagamenti',
        ],
        'catalogo' => [
            'gestionar' => 'Creare e modificare unità e tariffe',
        ],
        'limpieza' => [
            'gestionar' => 'Gestire pulizie e manutenzione',
        ],
        'mensajes' => [
            'gestionar' => 'Inviare messaggi agli ospiti',
        ],
        'ajustes' => [
            'gestionar' => 'Modelli, automazioni, calendari e motore di prenotazione',
        ],
        'huespedes' => [
            'gestionar' => 'Registrazione degli arrivi, documenti e privacy',
        ],
        'cuentas' => [
            'gestionar' => 'Estratti conto dei proprietari e calendari dei pagamenti',
        ],
    ],
    'roles' => [
        'modelo' => 'ruolo',
        'modelos' => 'Ruoli',
        'nombre' => 'Nome',
        'permisos' => 'Permessi',
        'en_uso' => 'Utenti con questo ruolo',
        'ayuda' => 'Ciò che cambi qui vale per tutte le persone con questo ruolo appena salvi.',
    ],
    'usuario' => [
        'ajustes' => 'Impostazioni di questa persona',
        'ajustes_ayuda' => 'Facoltativo. Solo per chi ha bisogno di qualcosa di diverso dal proprio ruolo.',
        'agregados' => 'Oltre al suo ruolo, può',
        'quitados' => 'Anche se il suo ruolo lo consente, non può',
        'solo_propias' => 'Solo le proprie prenotazioni e i propri ospiti',
        'solo_propias_ayuda' => 'Oggi e gli elenchi mostrano solo ciò che ha creato. Il calendario mostra ciò che è occupato, senza nomi.',
        'sin_iniciadas' => 'Non modificare prenotazioni già iniziate',
        'sin_iniciadas_ayuda' => 'Quelle già arrivate o che arrivano oggi. Registrare arrivo, partenza e pagamenti resta consentito.',
        'horas_modificar' => 'Ore per modificare una prenotazione',
        'horas_cancelar' => 'Ore per annullare una prenotazione',
        'horas_ayuda' => 'Contate da quando è stata creata. Vuoto: senza limite.',
    ],
    'limites' => [
        'no_modificar' => 'I tuoi permessi non consentono di modificare questa prenotazione.',
        'ocupada' => 'Occupato',
    ],
    'sesiones' => [
        'titulo' => 'Sessioni e dispositivi',
        'abiertas' => 'Sessioni aperte',
        'sin_sesiones' => 'Nessuna sessione da mostrare.',
        'recordados' => 'Dispositivi ricordati',
        'recordados_ayuda' => 'Dove è stato selezionato «Ricordami» all’accesso. Possono rientrare senza password fino alla data indicata.',
        'sin_dispositivos' => 'Nessun dispositivo ricordato.',
        'usuario' => 'Utente',
        'dispositivo' => 'Dispositivo',
        'ultimo_uso' => 'Ultimo utilizzo',
        'caduca' => 'Scade',
        'acciones' => 'Azioni',
        'esta' => 'Questa sessione',
        'cerrar' => 'Chiudi',
        'olvidar' => 'Dimentica',
        'cerrar_demas' => 'Chiudi le mie altre sessioni e gli altri dispositivi',
        'cerradas' => 'Le tue altre sessioni e gli altri dispositivi sono stati chiusi.',
        'dias' => 'Giorni per cui si ricorda un dispositivo',
        'dias_ayuda' => 'Con 0 non se ne ricorda nessuno e si dimenticano quelli esistenti.',
        'guardar' => 'Salva',
        'guardado' => 'Salvato.',
    ],
    'dos_pasos' => [
        'quitar' => 'Rimuovi la verifica in due passaggi',
        'quitar_texto' => 'Per chi ha perso il telefono e i codici di recupero. Potrà accedere solo con la password finché non la riattiva nel profilo.',
        'quitada' => 'La verifica in due passaggi di :usuario è stata rimossa.',
    ],
    'auditoria' => [
        'exportar' => 'Esporta CSV',
        'plazo' => 'Tempo di conservazione',
        'dias' => 'Giorni',
        'dias_ayuda' => 'Il più vecchio si cancella da solo. Minimo 30 giorni.',
        'plazo_guardado' => 'Salvato.',
    ],
];
