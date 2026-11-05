<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

return [
    'titulo' => 'Backup',
    'explicacion' => 'Ogni backup contiene tutto il database e i file caricati, in uno zip che puoi scaricare e ricaricare qui o in un’altra installazione.',
    'estado_automaticas' => 'Ogni notte si crea un backup automatico e si conservano gli ultimi :cuantas.',
    'estado_manuales' => 'I backup automatici sono disattivati: si creano solo quando lo chiedi.',
    'aviso_fuera' => 'Questi backup si trovano su questo stesso server. Scaricane uno ogni tanto e conservalo altrove, insieme al file di configurazione: è l’unica protezione se si perde il server.',
    'crear' => 'Crea backup ora',
    'creada' => 'Backup creato: :nombre',
    'subir' => 'Carica un backup',
    'subir_ayuda' => 'Carica uno zip scaricato in precedenza, da questa o da un’altra installazione di AlojaFlow. Viene solo salvato: per usarlo, premi poi «Ripristina».',
    'archivo' => 'File del backup (zip)',
    'subida' => 'Backup caricato: :nombre',
    'ajustes' => 'Impostazioni',
    'automaticas' => 'Crea un backup automatico ogni notte',
    'automaticas_ayuda' => 'Richiede l’attività pianificata del server o che qualcuno usi il sistema quel giorno.',
    'conservar' => 'Quanti backup conservare',
    'conservar_ayuda' => 'Quando se ne crea uno nuovo, si eliminano i più vecchi. Ogni backup contiene dati personali: non conservarne più del necessario.',
    'ajustes_guardados' => 'Impostazioni salvate.',
    'descargar_env' => 'Scarica la configurazione',
    'descargar_env_ayuda' => 'Il file .env contiene la chiave con cui si cifrano i documenti e le password salvati, e la password del database. Senza quella chiave, un backup ripristinato su un altro server non potrà leggere quei dati. Conservalo in un luogo sicuro, separato dai backup.',
    'contrasena' => 'La tua password',
    'lista' => 'Backup salvati',
    'vacio' => 'Non ci sono ancora backup.',
    'fecha' => 'Data',
    'origen' => 'Origine',
    'version' => 'Versione',
    'tamano' => 'Dimensione',
    'acciones' => 'Azioni',
    'otra_clave' => 'Da un’altra installazione: i suoi dati cifrati non si potranno leggere con la chiave attuale.',
    'origenes' => [
        'manual' => 'Manuale',
        'programada' => 'Automatico',
        'antes_de_restaurar' => 'Prima del ripristino',
        'antes-de-independizar' => 'Prima dell’indipendenza',
        'antes-de-importar' => 'Prima dell’importazione',
    ],
    'descargar' => 'Scarica',
    'restaurar' => 'Ripristina',
    'restaurar_titulo' => 'Ripristinare questo backup?',
    'restaurar_texto' => 'Tutti i dati attuali vengono sostituiti da quelli del backup. Prima si salva un altro backup dello stato attuale, nel caso serva tornare indietro. Alla fine dovrai accedere di nuovo.',
    'eliminar' => 'Elimina',
    'eliminar_titulo' => 'Eliminare questo backup?',
    'eliminada' => 'Backup eliminato.',
    'error' => [
        'no_escribe' => 'Non è stato possibile scrivere il backup in storage/app/private/copias. Controlla i permessi e lo spazio libero.',
        'no_existe' => 'Questo backup non esiste più.',
        'no_es_copia' => 'Questo file non è un backup di AlojaFlow o è danneggiato.',
        'mas_nueva' => 'Questo backup viene dalla versione :version, più recente di quella installata. Aggiorna AlojaFlow prima di ripristinarlo.',
        'restaurar' => 'Non è stato possibile ripristinare il backup; i dati sono tornati com’erano. Dettaglio: :detalle',
    ],
];
