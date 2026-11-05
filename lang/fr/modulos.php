<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

return [
    'titulo' => 'Modules',
    'explicacion' => 'N\'activez que ce que vous utilisez : le reste quitte le menu et ne gêne plus. Désactiver un module n\'efface pas ses données, et si vous le réactivez, tout est encore là. Tant qu\'il est désactivé, ses pages ne s\'ouvrent pas et ses tâches s\'arrêtent.',
    'siempre' => 'Toujours présents : Aujourd\'hui, calendrier, réservations, clients, utilisateurs, sauvegardes, mises à jour, confidentialité et le lien vers le code source.',
    'guardar' => 'Enregistrer',
    'guardado' => 'Modules enregistrés.',
    'repetir' => 'Répondre à nouveau aux questions',
    'nuevo' => 'Nouveau',
    'requiere' => 'Nécessite : :modulos',
    'omitido_al_apagar' => 'Non envoyé car le module a été désactivé.',
    'aviso_nuevos' => 'De nouveaux modules sont arrivés avec la mise à jour : :modulos. Ils arrivent désactivés.',
    'ver' => 'Voir les modules',

    'grupos' => [
        'propiedades' => 'Établissements',
        'reservas' => 'Réservations',
        'operacion' => 'Exploitation',
        'dinero' => 'Argent',
        'sistema' => 'Système',
    ],

    'lista' => [
        'varias_propiedades' => ['nombre' => 'Plusieurs établissements', 'descripcion' => 'Gérer plus d\'un hébergement depuis cette installation.'],
        'subordinacion' => ['nombre' => 'Subordination', 'descripcion' => 'Copier les réservations d’un autre système qui reste le principal : HotelDruid, AlojaFlow 1.2.x ou une autre installation d’AlojaFlow. On n’y écrit jamais. Permet aussi de partager vos établissements avec une autre installation.'],
        'motor' => ['nombre' => 'Moteur de réservation', 'descripcion' => 'Une page où les clients cherchent des dates et réservent seuls.'],
        'pagos' => ['nombre' => 'Paiements en ligne', 'descripcion' => 'Encaisser avec Stripe ou Mercado Pago depuis le moteur de réservation.'],
        'ical' => ['nombre' => 'Calendriers externes', 'descripcion' => 'Bloquer des dates avec les calendriers d\'Airbnb, Booking et Vrbo, et leur donner le vôtre.'],
        'cupones' => ['nombre' => 'Coupons', 'descripcion' => 'Codes de réduction pour les réservations.'],
        'grupos' => ['nombre' => 'Groupes', 'descripcion' => 'Réserver plusieurs unités ensemble et les gérer comme une seule.'],
        'limpieza' => ['nombre' => 'Ménage et maintenance', 'descripcion' => 'État de chaque unité, tâches pour le personnel et règles de ménage.'],
        'aviso_equipo' => ['nombre' => 'Avis des arrivées et départs', 'descripcion' => 'Un bulletin pour l’équipe : qui part, qui arrive et quoi nettoyer, prêt à envoyer par WhatsApp.'],
        'plano' => ['nombre' => 'Plan de l\'établissement', 'descripcion' => 'Voir les unités disposées sur un dessin de votre hébergement.'],
        'llegada' => ['nombre' => 'Arrivée en ligne', 'descripcion' => 'Le client remplit ses données et accepte les documents avant d\'arriver.'],
        'mensajes' => ['nombre' => 'Messages aux clients', 'descripcion' => 'Modèles, envois par e-mail et WhatsApp, et messages automatiques.'],
        'camping' => ['nombre' => 'Campings et emplacements', 'descripcion' => 'Ce qu’offre chaque emplacement, l’équipement du client, le choix sur le plan, les relevés d’électricité et d’eau, les résidents saisonniers et les extras limités.'],
        'propietarios' => ['nombre' => 'Propriétaires', 'descripcion' => 'Si vous gérez des unités pour des tiers : propriétaires et commission, dépenses, relevés, leur portail et leurs séjours.'],
        'estancias_largas' => ['nombre' => 'Longs séjours et mensualités', 'descripcion' => 'Prix à la semaine et au mois, calendrier de paiements avec rappels et une facture par paiement.'],
        'portal' => ['nombre' => 'Ma réservation et guide numérique', 'descripcion' => 'Une page pour chaque client, avec sa réservation et ce qu’il doit savoir, et un code QR dans chaque unité.'],
        'bitacora' => ['nombre' => 'Journal des équipes', 'descripcion' => 'Ce qu’une équipe laisse à la suivante. L’important apparaît dans Aujourd’hui.'],
        'empresas' => ['nombre' => 'Entreprises et agences', 'descripcion' => 'Qui réserve ou paie pour ses clients, avec ses conditions et son relevé de compte.'],
        'opiniones' => ['nombre' => 'Avis des clients', 'descripcion' => 'Une enquête après le séjour, avec un lien vers votre page d’avis.'],
        'caja' => ['nombre' => 'Caisse et clôture du jour', 'descripcion' => 'Caisses, mouvements d\'argent et la clôture quotidienne.'],
        'facturacion' => ['nombre' => 'Factures et reçus', 'descripcion' => 'Données fiscales, factures, reçus et exportations pour le comptable.'],
        'informes' => ['nombre' => 'Rapports et indicateurs', 'descripcion' => 'Occupation, revenus et autres rapports.'],
        'integraciones' => ['nombre' => 'Avis vers d\'autres systèmes', 'descripcion' => 'Envoyer ce qui se passe avec les réservations à d\'autres programmes (webhooks). Si vous ne savez pas ce que c\'est, vous n\'en avez pas besoin.'],
    ],

    'en_uso' => [
        'varias_propiedades' => 'Vous avez déjà :n établissement de plus. Il continuera de fonctionner, mais vous ne pourrez pas en créer d\'autres.|Vous avez déjà :n établissements de plus. Ils continueront de fonctionner, mais vous ne pourrez pas en créer d\'autres.',
        'subordinacion' => ':n établissement en dépend : il ne sera plus mis à jour.|:n établissements en dépendent : ils ne seront plus mis à jour.',
        'ical' => ':n calendrier ne sera plus mis à jour, et les portails ne pourront plus lire les vôtres.|:n calendriers ne seront plus mis à jour, et les portails ne pourront plus lire les vôtres.',
        'grupos' => 'Vous avez déjà :n groupe. Ses réservations restent, mais l\'écran des groupes ne s\'ouvrira pas.|Vous avez déjà :n groupes. Leurs réservations restent, mais l\'écran des groupes ne s\'ouvrira pas.',
        'integraciones' => ':n avis ne sera plus envoyé.|:n avis ne seront plus envoyés.',
    ],

    'preguntas' => [
        'titulo' => 'Adaptons-le à vos besoins',
        'explicacion' => 'Cinq questions rapides pour n\'activer que ce qui vous sert. Vous pourrez le changer plus tard dans Système → Modules.',
        'paso' => 'Question :paso sur :total',
        'atras' => 'Retour',
        'siguiente' => 'Suivant',
        'terminar' => 'Terminer',
        'saltar' => 'Passer et tout laisser activé',
        'listo' => 'C\'est fait. Vous pouvez le changer quand vous voulez dans Système → Modules.',
        'propiedades' => [
            'titulo' => 'Combien d\'hébergements allez-vous gérer ici ?',
            'una' => 'Un seul',
            'una_ayuda' => 'Un hôtel, des cabanes, une maison ou plusieurs chambres au même endroit.',
            'varias' => 'Plusieurs',
            'varias_ayuda' => 'Des hébergements différents, chacun avec ses unités et ses données.',
        ],
        'otro_sistema' => [
            'titulo' => 'Utilisez-vous déjà un autre système qui restera le principal ?',
            'no' => 'Non, AlojaFlow sera mon système',
            'no_ayuda' => 'Vous saisissez ici vos réservations, tarifs et clients.',
            'principal' => 'Oui, HotelDruid ou AlojaFlow 1.2.x',
            'principal_ayuda' => 'AlojaFlow copie ses réservations et ajoute les siennes par-dessus, sans jamais y écrire.',
            'alojaflow' => 'Oui, une autre installation d’AlojaFlow',
            'alojaflow_ayuda' => 'Celle-ci copie un établissement de l’autre avec un code de connexion, sans jamais y écrire.',
        ],
        'reservas' => [
            'titulo' => 'De quoi d\'autre avez-vous besoin pour vos réservations ?',
            'ayuda' => 'Celles que vous prenez par téléphone, par WhatsApp ou en personne peuvent toujours être saisies.',
        ],
        'operacion' => [
            'titulo' => 'Que voulez-vous organiser ici ?',
            'ayuda' => 'Cochez ce que vous faites tous les jours, ou ce que vous aimeriez ne plus tenir sur papier.',
        ],
        'dinero' => [
            'titulo' => 'Comment gérez-vous l\'argent et les papiers ?',
            'ayuda' => 'Les paiements de chaque réservation sont toujours enregistrés. Voici ce qui s\'y ajoute.',
        ],
        'resumen' => [
            'titulo' => 'Voici le résultat',
            'ayuda' => 'Si quelque chose ne vous convient pas, revenez en arrière. Vous pourrez aussi le changer plus tard.',
            'prendidos' => 'Activé',
            'apagados' => 'Désactivé',
            'base' => 'Aujourd\'hui, calendrier, réservations, clients et le reste de l\'essentiel',
        ],
    ],

    'pasos' => [
        'titulo' => 'Premiers pas',
        'avance' => ':hechos sur :total faits',
        'ocultar' => 'Ne plus afficher',
        'ir' => 'Aller',
        'copiar' => 'Copier',
        'copiado' => 'Copié',
        'propiedad' => ['titulo' => 'Créez votre établissement', 'ayuda' => 'Le nom, le pays et la devise de votre hébergement.'],
        'unidades' => ['titulo' => 'Ajoutez vos unités', 'ayuda' => 'Les chambres, cabanes ou appartements que vous louez.'],
        'tarifa' => ['titulo' => 'Définissez un tarif', 'ayuda' => 'Ce que vous facturez par nuit, avec ses saisons si vous en avez.'],
        'motor' => ['titulo' => 'Ouvrez le moteur de réservation', 'ayuda' => 'Pour que les clients réservent seuls depuis votre site.'],
        'correo' => ['titulo' => 'Configurez l\'e-mail', 'ayuda' => 'Sans e-mail, les messages ne partent pas et aucun mot de passe ne peut être récupéré.'],
        'dos_pasos' => ['titulo' => 'Activez la vérification en deux étapes', 'ayuda' => 'Votre compte a accès à tout : c\'est celui qu\'il vaut le mieux protéger.'],
        'cron' => ['titulo' => 'Créez la tâche planifiée', 'ayuda' => 'Elle fait tourner à l\'heure les synchronisations, les messages et les sauvegardes. Dans le panneau de votre hébergeur, créez une tâche planifiée (cron) à l\'intervalle le plus court permis, avec cette commande. Sans elle, ces tâches tournent quand quelqu\'un utilise le système.'],
        'copia' => ['titulo' => 'Faites votre première sauvegarde', 'ayuda' => 'Et gardez-la hors de l\'hébergement.'],
        'equipo' => ['titulo' => 'Invitez votre équipe', 'ayuda' => 'Chacun avec son utilisateur et seulement ce qu\'il doit voir. Si vous travaillez seul, ignorez cette étape.'],
    ],
];
