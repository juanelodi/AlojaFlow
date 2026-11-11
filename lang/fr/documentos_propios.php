<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

return [
    'modelo' => 'document',
    'modelos' => 'Documents personnalisés',
    'nombre' => 'Nom',
    'alcance' => 'D’où il vient',
    'alcance_ayuda' => 'Celui d’une réservation se crée depuis cette réservation, dans « Plus d’actions ». Celui de plusieurs, depuis cette liste, en choisissant les dates.',
    'activo' => 'Actif',
    'cuerpo' => 'Texte du document',
    'vacio' => 'Aucun document pour l’instant',
    'vacio_ayuda' => 'Un contrat, une fiche d’enregistrement ou la liste des arrivées du jour, avec votre propre texte. Vous pouvez commencer par les modèles de départ.',
    'variables' => 'Écrivez le document comme n’importe quel texte. Là où vous voulez une donnée, mettez sa variable entre accolades, comme {huesped}. Pour une langue vide, celle de l’établissement est utilisée.',
    'accion' => 'Document personnalisé',
    'elegir' => 'Document',
    'generar' => 'Générer',
    'vista_previa' => 'Aperçu',
    'sin_reservas_previa' => 'Cet établissement n’a pas encore de réservations pour l’essayer.',
    'desde' => 'Du',
    'hasta' => 'Au',
    'criterio' => 'Quelles réservations',
    'criterios' => [
        'llegadas' => 'Celles qui arrivent à ces dates',
        'salidas' => 'Celles qui partent à ces dates',
        'en_casa' => 'Celles qui séjournent à ces dates',
    ],
    'demasiadas' => 'Cela fait plus de :maximo réservations pour un seul document. Choisissez moins de jours.',
    'de_partida' => 'Créer les modèles de départ',
    'de_partida_ayuda' => 'Crée un contrat, une fiche d’enregistrement et une liste des arrivées, à adapter. Le contrat n’est qu’un point de départ : faites-le relire par votre conseiller.',
    'de_partida_listo' => 'C’est fait : relisez-les et adaptez-les.',
    'si' => 'Oui',

    'campos' => [
        'modelo' => 'champ personnalisé',
        'modelos' => 'Champs personnalisés',
        'seccion' => 'Champs personnalisés',
        'accion' => 'Champs personnalisés',
        'guardados' => 'Champs enregistrés.',
        'nombre' => 'Nom',
        'nombre_ayuda' => 'Par exemple : plaque de la voiture, chambre préférée.',
        'entidad' => 'À quoi il se rapporte',
        'tipo' => 'Ce qu’il contient',
        'activo' => 'Actif',
        'activo_ayuda' => 'Un champ inactif n’est plus demandé, et ce qui a été saisi est conservé.',
        'variable' => 'Variable',
        'vacio' => 'Aucun champ personnalisé pour l’instant',
        'vacio_ayuda' => 'Ce que vous voulez garder sur un client ou une réservation et que le système ne demande pas. Cela apparaît dans leurs fiches, dans les documents et dans les messages.',
    ],

    'base' => [
        'reglamento' => [
            'nombre' => 'Règlement intérieur',
            'intro' => 'Règles de {propiedad} pour la réservation {numero}, du {llegada} au {salida} :',
            'horarios' => 'Respectez les horaires d’arrivée et de départ indiqués.',
            'ruido' => 'Restez silencieux la nuit et respectez les autres clients.',
            'visitas' => 'Seules les personnes enregistrées peuvent rester ; prévenez-nous de toute visite.',
            'fumar' => 'Il est interdit de fumer dans les unités.',
            'danos' => 'Le client répond des dommages qu’il cause.',
            'firma' => 'Pris connaissance le {hoy} :',
        ],
        'limpieza' => [
            'nombre' => 'Feuille de ménage',
            'intro' => 'Départs du {desde} au {hasta} :',
            'salida' => 'Départ',
            'unidad' => 'Unité',
            'personas' => 'Personnes',
            'comentario' => 'Commentaire',
        ],
        'contrato' => [
            'nombre' => 'Contrat d’hébergement',
            'partes' => 'Entre {propiedad}, située à {domicilio_propiedad}, et {huesped}, pièce d’identité {huesped_documento}, il est convenu ce qui suit :',
            'estancia' => 'Séjour dans {unidad}, du {llegada} au {salida} : {noches} nuits, pour {personas} personnes.',
            'precio' => 'Prix total : {total}. Payé à ce jour : {pagado}. Reste à payer : {pendiente}.',
            'personas' => 'Seules les personnes enregistrées dans cette réservation peuvent séjourner.',
            'danos' => 'Le client répond des dommages causés à l’unité.',
            'firma' => 'Signé le {hoy} :',
        ],
        'registro' => [
            'nombre' => 'Fiche d’enregistrement',
            'intro' => 'Réservation {numero} · {unidad} · du {llegada} au {salida}.',
            'persona' => 'Personne',
            'nacimiento' => 'Naissance',
            'nacionalidad' => 'Nationalité',
            'documento' => 'Pièce d’identité',
            'firma' => 'Signature de {huesped} :',
        ],
        'llegadas' => [
            'nombre' => 'Liste des arrivées',
            'intro' => 'Du {desde} au {hasta} : {cuantas} réservations.',
            'llegada' => 'Arrivée',
            'salida' => 'Départ',
            'unidad' => 'Unité',
            'huesped' => 'Client',
            'personas' => 'Personnes',
            'pendiente' => 'Reste à payer',
        ],
    ],
];
