<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

return [
    'titulo' => 'Ménage',
    'tarea_salida' => 'Nettoyer :unidad (départ)',
    'tarea_estancia' => 'Nettoyer :unidad (client sur place)',
    'solo_mias' => 'Seulement les miennes',
    'vacio' => 'Rien à afficher.',
    'sale_hoy' => 'Part aujourd’hui.',
    'llega_hoy' => 'Arrive aujourd’hui.',
    'alojada' => 'Client sur place.',
    'libre' => 'Libre.',
    'fuera_de_servicio' => 'Hors service',
    'urgente_corto' => 'Urgent',
    'ver_foto' => 'Photo :n',
    'empezar' => 'Commencer',
    'terminar' => 'Terminer',
    'marcar_limpia' => 'Marquer propre',
    'marcar_sucia' => 'Marquer sale',
    'volver_servicio' => 'Remettre en service',
    'poner_fuera' => 'Mettre hors service',
    'marcada' => ':unidad : :estado.',
    'terminada' => 'Terminé : :tarea.',
    'incidencia' => 'Signaler un problème',
    'incidencia_de' => 'Problème dans :unidad',
    'incidencia_reportar' => 'Signaler',
    'incidencia_titulo' => 'Que se passe-t-il ?',
    'incidencia_titulo_ayuda' => 'Par exemple : la douche fuit.',
    'incidencia_descripcion' => 'Détails',
    'incidencia_ok' => 'Problème signalé.',
    'fotos' => 'Photos',
    'urgente' => 'C’est urgent',
    'dejar_fuera' => 'Laisser l’unité hors service (elle ne pourra pas être vendue)',
    'aviso_whatsapp' => 'Envoyer par WhatsApp',
    'aviso_titulo' => ':propiedad · ménage du :fecha',
    'aviso_vacio' => 'Aucune tâche.',
    'tareas' => [
        'modelo' => 'tâche',
        'modelos' => 'Tâches',
        'tipo' => 'Type',
        'titulo' => 'Tâche',
        'descripcion' => 'Description',
        'fecha' => 'Date',
        'estado' => 'État',
        'unidad' => 'Unité',
        'asignada' => 'Assignée à',
        'sin_asignar' => 'Non assignée',
        'urgente' => 'Urgente',
        'vacio' => 'Aucune tâche.',
        'abiertas' => 'À faire',
    ],
    'regla' => [
        'accion' => 'Ménage pendant le séjour',
        'frecuencia' => 'À quelle fréquence ?',
        'sin_limpieza' => 'Uniquement au départ',
        'dias' => 'Jours',
        'guardada' => 'Ménage du séjour enregistré.',
    ],
    'inspeccion' => 'Vérifier les unités après le ménage',
    'inspeccion_ayuda' => 'Une unité nettoyée reste « À vérifier » jusqu’à ce que quelqu’un l’inspecte.',
];
