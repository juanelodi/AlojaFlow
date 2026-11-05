<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

return [
    'duplicados' => [
        'titulo' => 'Fiches en double',
        'ayuda' => 'Fiches qui peuvent être la même personne. Vérifiez-les avant de les fusionner : réservations, contacts et notes passent à celle que vous conservez, et c’est irréversible.',
        'vacio' => 'Aucune fiche en double trouvée.',
        'contacto' => 'Contact',
        'reservas' => 'Réservations',
        'conservar' => 'Fusionner dans celle-ci',
        'confirmar' => 'Les autres fiches de ce groupe seront fusionnées avec celle de :nombre, puis supprimées. C’est irréversible.',
        'motivos' => [
            'nombre' => 'Même nom',
            'correo' => 'Même e-mail',
            'telefono' => 'Même téléphone',
            'documento' => 'Même document',
        ],
    ],
    'anonimizar' => [
        'titulo' => 'Anonymiser par ancienneté',
        'ayuda' => 'Les personnes qui ne sont pas venues depuis des années. Leurs données personnelles sont supprimées et leurs réservations restent au nom de « Anonymisé », pour que les statistiques restent justes. Les documents déjà enregistrés ne changent pas, et ce qui vient d’une installation principale s’anonymise là-bas.',
        'anios' => 'Années sans séjour',
        'encontrados' => '{0} Personne n’est absent depuis si longtemps|{1} :n personne est absente depuis si longtemps|[2,*] :n personnes sont absentes depuis si longtemps',
        'vacio' => 'Rien à anonymiser avec ce délai.',
        'accion' => 'Tout anonymiser',
        'confirmar' => '{1} :n fiche sans séjour depuis :anios ans sera anonymisée. C’est irréversible.|[2,*] :n fiches sans séjour depuis :anios ans seront anonymisées, jusqu’à 200 à la fois. C’est irréversible.',
        'hecho' => '{1} :n fiche anonymisée.|[0,*] :n fiches anonymisées.',
    ],
    'tipo_cliente' => [
        'titulo' => 'Type de client',
        'ayuda' => 'Données libres de cette fiche, comme « Type : VIP ». Si elles correspondent à une couleur de Couleurs des clients, ses réservations sont ainsi marquées dans le calendrier.',
        'agregar' => 'Ajouter une donnée',
    ],
    'facturas' => [
        'formato' => 'Format',
        'excel' => 'Excel en paramètres européens (point-virgule, virgule décimale)',
        'csv' => 'CSV standard (virgule, point décimal)',
    ],
    'franja' => [
        'dias' => 'Jours de copie',
        'dias_ayuda' => 'Sans aucun coché, tous les jours.',
        'desde' => 'Copier à partir de',
        'hasta' => 'Jusqu’à',
        'ayuda' => 'À l’heure de l’établissement. Peut franchir minuit, comme de 22:00 à 06:00. Vide, à toute heure. La copie manuelle n’a pas cette limite.',
    ],
    'region' => [
        'campo' => 'État ou région',
        'ayuda' => 'Elle sert à proposer la taxe de séjour correspondante.',
    ],
    'colores' => [
        'titulo' => 'Couleurs du calendrier',
        'ayuda' => 'Elles ne changent que pour vous. Vide, la couleur habituelle.',
    ],
    'registro' => [
        'modelo' => 'enregistrement touristique',
        'modelos' => 'Enregistrements touristiques',
        'ayuda' => 'Le numéro d’enregistrement ou de licence de l’établissement ou d’une unité. Il s’affiche sur les pages publiques et les documents, et AlojaFlow prévient 30 jours avant son expiration.',
        'toda' => 'Tout l’établissement',
        'tipo' => 'Type',
        'tipo_ayuda' => 'Son nom courant : RNT, numéro de meublé, licence…',
        'numero' => 'Numéro',
        'vigente_hasta' => 'Valable jusqu’au',
        'vigente_hasta_ayuda' => 'Vide s’il n’expire pas.',
        'mostrar' => 'L’afficher au public',
        'mostrar_ayuda' => 'Sur les pages publiques et les documents.',
        'vacio' => 'Aucun enregistrement pour le moment',
        'responsables' => 'Responsable et réclamations',
        'responsables_ayuda' => 'Ce que certains pays exigent d’afficher. Présenté à côté du numéro d’enregistrement.',
        'responsable' => 'Responsable',
        'autoridad_quejas' => 'Autorité pour les réclamations',
        'autoridad_quejas_ayuda' => 'Par exemple, au Mexique, la Profeco et le Secrétariat au Tourisme.',
    ],
];
