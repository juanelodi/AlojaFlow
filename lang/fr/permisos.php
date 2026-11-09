<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

return [
    'nombres' => [
        'operacion' => [
            'ver' => 'Voir les écrans d’exploitation (Aujourd’hui, calendrier, ménage)',
        ],
        'datos' => [
            'ver' => 'Voir les réservations, les clients et les autres données',
        ],
        'indicadores' => [
            'ver' => 'Voir les indicateurs et les rapports',
        ],
        'sincronizacion' => [
            'gestionar' => 'Gérer la synchronisation avec l’installation principale',
        ],
        'reservas' => [
            'gestionar' => 'Créer et modifier les réservations, les clients et les paiements',
        ],
        'catalogo' => [
            'gestionar' => 'Créer et modifier les unités et les tarifs',
        ],
        'limpieza' => [
            'gestionar' => 'Gérer le ménage et la maintenance',
        ],
        'mensajes' => [
            'gestionar' => 'Envoyer des messages aux clients',
        ],
        'ajustes' => [
            'gestionar' => 'Modèles, automatisations, calendriers et moteur de réservation',
        ],
        'huespedes' => [
            'gestionar' => 'Enregistrement des arrivées, documents et confidentialité',
        ],
        'cuentas' => [
            'gestionar' => 'Relevés des propriétaires et calendriers de paiements',
        ],
    ],
    'roles' => [
        'modelo' => 'rôle',
        'modelos' => 'Rôles',
        'nombre' => 'Nom',
        'permisos' => 'Autorisations',
        'en_uso' => 'Utilisateurs ayant ce rôle',
        'ayuda' => 'Ce que vous changez ici s’applique à toutes les personnes ayant ce rôle dès l’enregistrement.',
    ],
    'usuario' => [
        'ajustes' => 'Réglages de cette personne',
        'ajustes_ayuda' => 'Facultatif. Seulement pour une personne qui a besoin d’autre chose que son rôle.',
        'agregados' => 'En plus de son rôle, peut',
        'quitados' => 'Même si son rôle le permet, ne peut pas',
        'solo_propias' => 'Uniquement ses propres réservations et clients',
        'solo_propias_ayuda' => 'Aujourd’hui et les listes n’affichent que ce qu’elle a créé. Le calendrier montre ce qui est occupé, sans les noms.',
        'sin_iniciadas' => 'Ne pas modifier les réservations déjà commencées',
        'sin_iniciadas_ayuda' => 'Celles déjà arrivées ou qui arrivent aujourd’hui. Enregistrer l’arrivée, le départ et les paiements reste permis.',
        'horas_modificar' => 'Heures pour modifier une réservation',
        'horas_cancelar' => 'Heures pour annuler une réservation',
        'horas_ayuda' => 'Comptées depuis sa création. Vide : sans limite.',
    ],
    'limites' => [
        'no_modificar' => 'Vos autorisations ne permettent pas de modifier cette réservation.',
        'ocupada' => 'Occupé',
    ],
    'sesiones' => [
        'titulo' => 'Sessions et appareils',
        'abiertas' => 'Sessions ouvertes',
        'sin_sesiones' => 'Aucune session à afficher.',
        'recordados' => 'Appareils mémorisés',
        'recordados_ayuda' => 'Là où « Se souvenir de moi » a été coché à la connexion. Ils peuvent revenir sans mot de passe jusqu’à la date indiquée.',
        'sin_dispositivos' => 'Aucun appareil mémorisé.',
        'usuario' => 'Utilisateur',
        'dispositivo' => 'Appareil',
        'ultimo_uso' => 'Dernière utilisation',
        'caduca' => 'Expire',
        'acciones' => 'Actions',
        'esta' => 'Cette session',
        'cerrar' => 'Fermer',
        'olvidar' => 'Oublier',
        'cerrar_demas' => 'Fermer mes autres sessions et appareils',
        'cerradas' => 'Vos autres sessions et appareils ont été fermés.',
        'dias' => 'Jours de mémorisation d’un appareil',
        'dias_ayuda' => 'Avec 0, aucun n’est mémorisé et ceux qui existent sont oubliés.',
        'guardar' => 'Enregistrer',
        'guardado' => 'Enregistré.',
    ],
    'dos_pasos' => [
        'quitar' => 'Retirer la vérification en deux étapes',
        'quitar_texto' => 'Pour une personne qui a perdu son téléphone et ses codes de récupération. Elle pourra se connecter avec son seul mot de passe jusqu’à la réactiver dans son profil.',
        'quitada' => 'La vérification en deux étapes de :usuario a été retirée.',
    ],
    'auditoria' => [
        'exportar' => 'Exporter en CSV',
        'plazo' => 'Durée de conservation',
        'dias' => 'Jours',
        'dias_ayuda' => 'Le plus ancien est supprimé automatiquement. Minimum 30 jours.',
        'plazo_guardado' => 'Enregistré.',
    ],
    'crear_contrasena' => [
        'titulo' => 'Créez votre mot de passe',
        'ayuda' => 'C\'est votre première connexion. Choisissez le mot de passe que vous utiliserez désormais avec :correo.',
        'nueva' => 'Nouveau mot de passe',
        'confirmar' => 'Saisissez-le à nouveau',
        'guardar' => 'Enregistrer le mot de passe',
        'lista' => 'C\'est fait : vous pouvez maintenant vous connecter avec votre e-mail et ce mot de passe.',
    ],
];
