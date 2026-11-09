<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

return [
    'calendarios_leidos' => 'Gelesene Kalender: :cuantos',
    'mensajes' => 'Ereignisse: :eventos · Nach Datum: :generados · Gesendet: :enviados',
    'administrador_listo' => 'Administrator bereit: :correo',
    'tareas_nuevas' => 'Neue Aufgaben: :cuantas',
    'ejecuciones_borradas' => 'Gelöschte Synchronisierungsläufe: :cuantas',
    'plazos' => 'Aufbewahrungsfristen: :detalle',
    'copias_borradas' => 'Gelöschte Update-Kopien: :cuantas',
    'vencidas_canceladas' => 'Stornierte abgelaufene Buchungen: :cuantas',
    'demo_no_activa' => 'Diese Installation ist nicht im Demo-Modus: Es wird nichts gelöscht.',
    'demo_reiniciada' => 'Demo neu gestartet.',
    'paquete' => [
        'sin_cambios' => 'CAMBIOS.md hat keinen Abschnitt „## :version“.',
        'de' => 'AlojaFlow-Paket :version',
        'no_pasa' => 'Das Paket besteht die Prüfung nicht: :errores',
        'instalador' => 'Installationsprogramm',
        'instalador_completo' => 'Installer mit dem Zip darin',
        'archivos' => 'Dateien',
        'tamano' => 'Größe',
        'tamano_valor' => ':zip (:descomprimido entpackt)',
        'listo' => 'Paket fertig und geprüft.',
        'para_actualizar' => 'Um eine Installation zu aktualisieren: Laden Sie es unter System → :pantalla hoch oder in der Konsole:',
        'para_instalar' => 'Um neu zu installieren: Laden Sie :archivo auf das Hosting hoch und öffnen Sie es im Browser.',
    ],
    // Instalar sin navegador y enlace de entrada (D-162).
    'instalar' => [
        'ya_estaba' => 'AlojaFlow war hier bereits installiert: Es wurde nichts geändert.',
        'idioma' => 'Die Sprache muss eine der folgenden sein: :idiomas.',
        'lista' => 'AlojaFlow installiert. Super-Administrator: :correo.',
        'enlace' => 'Einmal-Anmeldelink (läuft in 30 Minuten ab):',
    ],
    'enlace_entrada' => [
        'sin_super' => 'Es gibt keinen aktiven Super-Administrator mit dieser E-Mail-Adresse.',
        'dos_pasos' => 'Dieser Super-Administrator nutzt die Zwei-Faktor-Verifizierung: Der Link funktioniert für ihn nicht, er meldet sich mit seinem Passwort an.',
    ],
    'tareas_pendientes' => [
        'nada' => 'Nichts ausstehend.',
        'tarea' => 'Aufgabe',
        'estado' => 'Status',
        'ventana' => 'Das Fenster liegt zwischen 1 und 1440 Minuten.',
    ],
];
