<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

return [
    'calendarios_leidos' => 'Calendari letti: :cuantos',
    'mensajes' => 'Eventi: :eventos · Per data: :generados · Inviati: :enviados',
    'administrador_listo' => 'Amministratore pronto: :correo',
    'tareas_nuevas' => 'Nuove attività: :cuantas',
    'ejecuciones_borradas' => 'Esecuzioni di sincronizzazione eliminate: :cuantas',
    'plazos' => 'Termini di conservazione: :detalle',
    'copias_borradas' => 'Copie degli aggiornamenti eliminate: :cuantas',
    'vencidas_canceladas' => 'Prenotazioni scadute annullate: :cuantas',
    'demo_no_activa' => 'Questa installazione non è in modalità demo: non si elimina nulla.',
    'demo_reiniciada' => 'Dimostrazione riavviata.',
    'paquete' => [
        'sin_cambios' => 'CAMBIOS.md non ha la sezione «## :version».',
        'de' => 'Pacchetto di AlojaFlow :version',
        'no_pasa' => 'Il pacchetto non supera la verifica: :errores',
        'instalador' => 'Installatore',
        'instalador_completo' => 'Installer con lo zip dentro',
        'archivos' => 'File',
        'tamano' => 'Dimensione',
        'tamano_valor' => ':zip (:descomprimido una volta decompresso)',
        'listo' => 'Pacchetto pronto e verificato.',
        'para_actualizar' => 'Per aggiornare un’installazione: caricalo in Sistema → :pantalla, o dalla console:',
        'para_instalar' => 'Per installare da zero: carica :archivo sull’hosting e aprilo nel browser.',
    ],
    // Instalar sin navegador y enlace de entrada (D-162).
    'instalar' => [
        'ya_estaba' => 'AlojaFlow era già installato qui: non è stato modificato nulla.',
        'idioma' => 'La lingua deve essere una tra: :idiomas.',
        'lista' => 'AlojaFlow installato. Super amministratore: :correo.',
        'enlace' => 'Link di accesso monouso (scade tra 30 minuti):',
    ],
    'enlace_entrada' => [
        'sin_super' => 'Nessun super amministratore attivo con questa email.',
        'dos_pasos' => 'Questo super amministratore usa la verifica in due passaggi: il link non funziona, accede con la sua password.',
    ],
];
