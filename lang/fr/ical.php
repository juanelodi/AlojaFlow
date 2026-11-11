<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

return [
    'ocupado' => 'Indisponible',
    'error_formato' => 'L’adresse n’a pas renvoyé de calendrier iCal valide.',
    'error_tamano' => 'Le calendrier est trop volumineux.',
    'calendario_externo' => 'Occupé sur :fuente',
    'fuentes' => [
        'modelo' => 'calendrier d’un portail',
        'modelos' => 'Calendriers iCal',
        'ayuda' => 'Collez le lien iCal fourni par chaque portail (Airbnb, Booking.com, Vrbo…). Ces dates ne sont plus vendues ici et, si elles entrent en conflit avec une réservation, vous êtes averti.',
        'unidad' => 'Chambre',
        'nombre' => 'Nom',
        'nombre_ayuda' => 'Par exemple : Airbnb.',
        'canal' => 'Canal',
        'url' => 'Lien iCal du portail',
        'url_ayuda' => 'Il est enregistré chiffré car il contient une clé du portail.',
        'url_invalida' => 'Cette adresse n’est pas autorisée : elle doit être publique.',
        'frecuencia' => 'Lire toutes les',
        'activa' => 'Actif',
        'estado' => 'État',
        'ultima_lectura' => 'Dernière lecture',
        'vacio' => 'Aucun calendrier de portail pour l’instant.',
        'leer' => 'Lire maintenant',
        'leida' => 'Calendrier lu : :n dates occupées.',
        'no_leida' => 'Impossible de lire le calendrier',
    ],
    'exportar' => [
        'accion' => 'Liens pour les portails',
        'propiedad' => 'Établissement',
        'ayuda' => 'Collez dans chaque portail le lien de sa chambre. Il ne montre que les dates occupées : jamais de données de clients.',
        'vacio' => 'Cet établissement n’a pas de chambres.',
        'regenerar' => 'Changer les liens',
        'regenerar_ayuda' => 'Les liens actuels cesseront de fonctionner et vous devrez coller les nouveaux dans chaque portail. Utilisez cette option si quelqu’un qui ne devrait pas les a.',
        'regenerados' => 'Liens changés.',
    ],
    'choques' => [
        'modelo' => 'conflit',
        'modelos' => 'Conflits',
        'desde' => 'Du',
        'hasta' => 'Au',
        'unidad' => 'Chambre',
        'reserva' => 'Réservation',
        'reserva_n' => 'Réservation :numero',
        'con' => 'En conflit avec',
        'nota' => 'Note',
        'abiertos' => 'À résoudre',
        'vacio' => 'Aucun conflit : aucune chambre n’est occupée deux fois.',
        'resolver' => 'Marquer comme résolu',
    ],
];
