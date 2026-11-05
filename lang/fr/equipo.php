<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

return [
    'aviso' => [
        'titulo' => 'Avis des arrivées et départs',
        'desde' => 'À partir du',
        'dias' => 'Jours',
        'anadir' => 'Ajouter à chaque unité',
        'nombre' => 'Nom',
        'personas' => 'Personnes',
        'origen' => 'Origine',
        'noches' => 'Nuits',
        'telefono' => 'Téléphone',
        'comentario' => 'Commentaire',
        'mostrar_limpieza' => 'Afficher le ménage',
        'texto' => 'Texte de l’avis (vous pouvez le retoucher avant de l’envoyer)',
        'telefono_destino' => 'WhatsApp du destinataire',
        'salen' => 'Départs',
        'llegan' => 'Arrivées',
        'limpieza' => 'Ménage',
        'n_personas' => ':count personne|:count personnes',
        'sin_movimiento' => 'Aucun mouvement à ces dates.',
        'omitidos_ayuda' => 'Un client interne ou de maintenance ne doit pas apparaître ici ? Marquez-le dans',
    ],

    'omisiones' => [
        'modelo' => 'client omis',
        'modelos' => 'Clients omis',
        'ayuda' => 'Choisissez où ce client ne doit pas apparaître : par exemple, votre famille ou les séjours de maintenance. Rien n’est effacé, et la réservation occupe toujours ses dates dans le calendrier.',
        'por' => 'Qui ?',
        'por_huesped' => 'Un client',
        'por_tipo' => 'Tous ceux d’un type',
        'campo' => 'Champ de la fiche',
        'campo_ayuda' => 'Le nom du champ personnalisé, comme pour les couleurs du calendrier. Par exemple, « Type de client ».',
        'valor' => 'Valeur',
        'donde' => 'Où est-il omis ?',
        'en_aviso' => 'Avis des arrivées et départs',
        'en_aviso_ayuda' => 'N’apparaît pas dans l’avis pour l’équipe.',
        'en_informes' => 'Rapports et indicateurs',
        'en_informes_ayuda' => 'Ses nuits et revenus ne comptent pas. Les encaissements et la caisse, si : cet argent est réel.',
        'en_mensajes' => 'Messages automatiques',
        'en_mensajes_ayuda' => 'Les automatisations ne lui parviennent pas. Ceux que vous envoyez à la main, si.',
        'motivo' => 'Motif',
        'quien' => 'Client',
        'vacio' => 'Il n’y a pas de clients omis',
    ],

    'listas' => [
        'modelo' => 'liste de contrôle',
        'modelos' => 'Listes de contrôle',
        'ayuda' => 'Ce qu’il faut vérifier pour chaque type de tâche. Elle apparaît en cases à cocher sur l’écran Ménage, dans les tâches créées à partir de maintenant.',
        'tipo' => 'Type de tâche',
        'nombre' => 'Nom',
        'puntos' => 'Points',
        'punto' => 'Point',
        'anadir' => 'Ajouter un point',
        'vacio' => 'Il n’y a pas de listes de contrôle',
    ],

    'recurrentes' => [
        'modelo' => 'maintenance récurrente',
        'modelos' => 'Maintenance récurrente',
        'ayuda' => 'Les travaux qui se répètent, comme changer les filtres ou désinsectiser. Sa tâche est créée une semaine avant.',
        'unidad_ayuda' => 'Laissez vide si cela concerne tout le lieu.',
        'titulo' => 'Travail',
        'descripcion' => 'Description',
        'cada_dias' => 'Tous les combien de jours',
        'proxima' => 'Prochaine date',
        'vacio' => 'Il n’y a pas de maintenance récurrente',
    ],
];
