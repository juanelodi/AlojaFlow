<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
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
    'variables_titulo' => 'Les variables',
    'variables_reserva' => 'De la réservation : {huesped} {nombre} {huesped_telefono} {huesped_correo} {huesped_nacimiento} {huesped_nacionalidad} {huesped_documento} {huesped_domicilio} {numero} {codigo} {llegada} {llegada_corta} {salida} {salida_corta} {noches} {personas} {ninos} {unidad} {tarifa} {origen} {comentario} {total} {pagado} {pendiente} {deposito} {propiedad} {domicilio_propiedad} {telefono_propiedad} {correo_propiedad} {hoy}.',
    'variables_huespedes' => 'Une ligne de tableau, un élément de liste ou un paragraphe qui utilise l’une de celles-ci se répète pour chaque client : {h_numero} {h_nombre} {h_nacimiento} {h_nacionalidad} {h_documento_tipo} {h_documento} {h_domicilio} {h_parentesco}.',
    'variables_cargos' => 'Pour chaque frais : {c_nombre} {c_cantidad} {c_importe}.',
    'variables_pagos' => 'Pour chaque paiement : {p_fecha} {p_metodo} {p_referencia} {p_importe}.',
    'variables_periodo' => 'Dans un document de plusieurs réservations, ce qui utilise les données d’une réservation se répète pour chacune. En plus : {desde} {hasta} {cuantas}.',
    'variables_campos' => 'Vos champs personnalisés : {campo_…}, et dans la ligne de chaque client {h_campo_…} ; la liste des champs donne le nom de chacun. Vos variables de messages fonctionnent aussi.',
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
