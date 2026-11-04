<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

return [
    'titulo' => 'Reinigung',
    'tarea_salida' => ':unidad reinigen (Abreise)',
    'tarea_estancia' => ':unidad reinigen (Gast im Haus)',
    'solo_mias' => 'Nur meine',
    'vacio' => 'Nichts anzuzeigen.',
    'sale_hoy' => 'Reist heute ab.',
    'llega_hoy' => 'Reist heute an.',
    'alojada' => 'Gast im Haus.',
    'libre' => 'Frei.',
    'fuera_de_servicio' => 'Außer Betrieb',
    'urgente_corto' => 'Dringend',
    'ver_foto' => 'Foto :n',
    'empezar' => 'Beginnen',
    'terminar' => 'Beenden',
    'marcar_limpia' => 'Als sauber markieren',
    'marcar_sucia' => 'Als schmutzig markieren',
    'volver_servicio' => 'Wieder in Betrieb nehmen',
    'poner_fuera' => 'Außer Betrieb setzen',
    'marcada' => ':unidad: :estado.',
    'terminada' => 'Erledigt: :tarea.',
    'incidencia' => 'Problem melden',
    'incidencia_de' => 'Problem in :unidad',
    'incidencia_reportar' => 'Melden',
    'incidencia_titulo' => 'Was ist los?',
    'incidencia_titulo_ayuda' => 'Zum Beispiel: Die Dusche tropft.',
    'incidencia_descripcion' => 'Details',
    'incidencia_ok' => 'Problem gemeldet.',
    'fotos' => 'Fotos',
    'urgente' => 'Es ist dringend',
    'dejar_fuera' => 'Einheit außer Betrieb lassen (kann nicht verkauft werden)',
    'aviso_whatsapp' => 'Per WhatsApp senden',
    'aviso_titulo' => ':propiedad · Reinigung vom :fecha',
    'aviso_vacio' => 'Keine Aufgaben.',
    'tareas' => [
        'modelo' => 'Aufgabe',
        'modelos' => 'Aufgaben',
        'tipo' => 'Typ',
        'titulo' => 'Aufgabe',
        'descripcion' => 'Beschreibung',
        'fecha' => 'Datum',
        'estado' => 'Status',
        'unidad' => 'Einheit',
        'asignada' => 'Zugewiesen an',
        'sin_asignar' => 'Nicht zugewiesen',
        'urgente' => 'Dringend',
        'vacio' => 'Keine Aufgaben.',
        'abiertas' => 'Zu erledigen',
    ],
    'regla' => [
        'accion' => 'Reinigung während des Aufenthalts',
        'frecuencia' => 'Wie oft?',
        'sin_limpieza' => 'Nur bei der Abreise',
        'dias' => 'Tage',
        'guardada' => 'Reinigung während des Aufenthalts gespeichert.',
    ],
    'inspeccion' => 'Einheiten nach der Reinigung prüfen',
    'inspeccion_ayuda' => 'Eine gereinigte Einheit bleibt „Zu prüfen“, bis jemand sie kontrolliert.',
];
