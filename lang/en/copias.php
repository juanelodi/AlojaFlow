<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

return [
    'titulo' => 'Backups',
    'explicacion' => 'Each backup holds the whole database and the uploaded files, in a zip you can download and load again here or in another installation.',
    'estado_automaticas' => 'An automatic backup is created every night and the last :cuantas are kept.',
    'estado_manuales' => 'Automatic backups are off: they are only created when you ask.',
    'aviso_fuera' => 'These backups live on this same server. Download one from time to time and keep it elsewhere, together with the configuration file: it is the only protection if the server is lost.',
    'crear' => 'Create backup now',
    'creada' => 'Backup created: :nombre',
    'subir' => 'Upload a backup',
    'subir_ayuda' => 'Upload a zip downloaded earlier, from this or another AlojaFlow installation. It is only stored: to use it, click «Restore» afterwards.',
    'archivo' => 'Backup file (zip)',
    'subida' => 'Backup uploaded: :nombre',
    'ajustes' => 'Settings',
    'automaticas' => 'Create an automatic backup every night',
    'automaticas_ayuda' => 'It needs the server\'s scheduled task, or someone using the system that day.',
    'conservar' => 'How many backups to keep',
    'conservar_ayuda' => 'When a new one is created, the oldest are deleted. Each backup holds personal data: do not keep more than you need.',
    'ajustes_guardados' => 'Settings saved.',
    'descargar_env' => 'Download configuration',
    'descargar_env_ayuda' => 'The .env file holds the key that encrypts stored documents and passwords, and the database password. Without that key, a backup restored on another server cannot read that data. Keep it somewhere safe, apart from the backups.',
    'contrasena' => 'Your password',
    'lista' => 'Saved backups',
    'vacio' => 'No backups yet.',
    'fecha' => 'Date',
    'origen' => 'Source',
    'version' => 'Version',
    'tamano' => 'Size',
    'acciones' => 'Actions',
    'otra_clave' => 'From another installation: its encrypted data cannot be read with the current key.',
    'origenes' => [
        'manual' => 'Manual',
        'programada' => 'Automatic',
        'antes_de_restaurar' => 'Before restoring',
    ],
    'descargar' => 'Download',
    'restaurar' => 'Restore',
    'restaurar_titulo' => 'Restore this backup?',
    'restaurar_texto' => 'All current data is replaced with the backup\'s. Another backup of the current state is saved first, in case you need to go back. You will have to sign in again when it finishes.',
    'eliminar' => 'Delete',
    'eliminar_titulo' => 'Delete this backup?',
    'eliminada' => 'Backup deleted.',
    'error' => [
        'no_escribe' => 'The backup could not be written to storage/app/private/copias. Check permissions and free space.',
        'no_existe' => 'That backup no longer exists.',
        'no_es_copia' => 'That file is not an AlojaFlow backup, or it is damaged.',
        'mas_nueva' => 'This backup comes from version :version, newer than the installed one. Update AlojaFlow before restoring it.',
        'restaurar' => 'The backup could not be restored; the data went back to how it was. Detail: :detalle',
    ],
];
