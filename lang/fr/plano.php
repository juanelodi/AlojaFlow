<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

return [
    'titulo' => 'Plan',
    'vacio' => 'Aucun plan pour l’instant. Créez-en un et placez vos chambres.',
    'nuevo' => 'Nouveau plan',
    'nombre' => 'Plan',
    'nombre_ayuda' => 'Par exemple : Rez-de-chaussée.',
    'imagen' => 'Image du plan (facultatif)',
    'imagen_ayuda' => 'Un dessin ou une photo. Sans image, les chambres se placent sur un fond uni.',
    'creado' => 'Plan créé : faites glisser chaque chambre à sa place.',
    'editar' => 'Placer les chambres',
    'terminar' => 'Terminer',
    'editar_ayuda' => 'Faites glisser chaque chambre à sa place. L’enregistrement est automatique.',
    'borrar' => 'Supprimer ce plan',
    'estados' => [
        'libre' => 'Libre',
        'llega' => 'Arrive aujourd’hui',
        'sale' => 'Part aujourd’hui',
        'ocupada' => 'Occupée',
    ],
    'colores' => [
        'modelo' => 'couleur de client',
        'modelos' => 'Couleurs de clients',
        'ayuda' => 'Colore les réservations dans le calendrier selon une donnée de la fiche client, comme « Type de client : Entreprise ».',
        'campo' => 'Champ de la fiche',
        'campo_ayuda' => 'Le nom du champ personnalisé, tel qu’il figure sur la fiche.',
        'valor' => 'Valeur',
        'color' => 'Couleur',
        'vacio' => 'Aucune couleur pour l’instant.',
    ],
];
