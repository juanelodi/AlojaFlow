<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

return [
    'fila_protegida' => 'Cette donnée vient de l’installation principale et ne peut être modifiée que là-bas.',
    'redireccion' => 'L’installation principale a répondu par une redirection vers :destino. Vérifiez l’adresse.',
    'version_no_admitida' => 'L’installation principale a le schéma :version, qu’AlojaFlow ne prend pas en charge (il prend en charge 3.08 et 3.081).',
    'copia_cortada' => 'La copie est arrivée tronquée : la fin manque.',
    'falta_tabla' => 'Il manque à la copie la table :tabla.',
    'destino' => [
        'url_invalida' => 'L’adresse de l’installation principale n’est pas valide.',
        'sin_barra_final' => 'L’adresse doit pointer vers le dossier de l’installation et se terminer par « / ».',
        'solo_https' => 'L’adresse doit commencer par https://.',
        'sin_resolver' => 'Le serveur :host est introuvable.',
        'red_local' => 'Le serveur :host est sur un réseau local ; ce n’est permis que si l’installation l’autorise et que l’installation principale a activé « Autoriser le réseau local ».',
    ],
    'errores' => [
        'SUB-001' => 'Impossible de se connecter à l’installation principale.',
        'SUB-002' => 'Le certificat HTTPS de l’installation principale n’est pas valide.',
        'SUB-003' => 'L’installation principale a refusé l’utilisateur ou le mot de passe, l’utilisateur ne peut pas créer de copies ou la validation en deux étapes est activée. La synchronisation est mise en pause pour ne pas bloquer le compte.',
        'SUB-005' => 'L’installation principale a atteint sa limite quotidienne de copies. Reprise demain ; il vaut mieux réduire la fréquence.',
        'SUB-006' => 'L’année demandée n’existe pas dans l’installation principale.',
        'SUB-007' => 'L’installation principale ne peut pas écrire la copie dans son dossier de données.',
        'SUB-008' => 'La copie dépasse la taille maximale autorisée ou est arrivée incomplète.',
        'SUB-009' => 'La copie compressée est arrivée endommagée.',
        'SUB-010' => 'La réponse n’est pas une copie valide de HotelDruid.',
        'SUB-011' => 'La version du schéma de l’installation principale n’est pas compatible.',
        'SUB-012' => 'La copie est incomplète.',
        'SUB-013' => 'Impossible de supprimer la copie sur l’installation principale.',
        'SUB-014' => 'Une synchronisation de cette installation principale est déjà en cours.',
        'SUB-015' => 'L’installation principale a mis trop de temps à répondre.',
        'SUB-016' => 'L’adresse de l’installation principale n’est pas autorisée.',
        'SUB-017' => 'L’autre installation n’a pas répondu comme prévu. Vérifiez qu’elle a AlojaFlow 1.0.12 ou plus récent et le module de subordination activé.',
        'SUB-018' => 'L’autre installation a refusé le code de connexion. Il a peut-être été révoqué : demandez-en un nouveau.',
        'SUB-099' => 'L’installation principale a donné une réponse qu’AlojaFlow ne reconnaît pas.',
    ],

    'compartir' => [
        'titulo' => 'Partager avec une autre installation',
        'ayuda' => 'Une autre installation d’AlojaFlow peut copier cet établissement, en lecture seule : les réservations se modifient toujours ici. Les photos et les fichiers ne voyagent jamais.',
        'sin_conexiones' => 'Vous ne le partagez encore avec personne',
        'nombre' => 'Nom',
        'nombre_ayuda' => 'Pour la reconnaître, par exemple « Siège ».',
        'documentos' => 'Inclure les pièces d’identité',
        'documentos_ayuda' => 'Désactivé, ni le numéro ni les autres données de la pièce ne voyagent. Activez-le seulement si l’autre installation en a besoin.',
        'ultimo_uso' => 'Dernière utilisation',
        'estado' => 'État',
        'activa' => 'Active',
        'revocada' => 'Révoquée',
        'crear' => 'Partager',
        'revocar' => 'Révoquer',
        'revocar_texto' => 'L’autre installation ne pourra plus copier. Ce qu’elle a déjà copié reste là-bas.',
        'codigo_titulo' => 'Code de connexion',
        'codigo_texto' => 'Copiez-le et collez-le dans l’autre installation en créant l’établissement subordonné. Par sécurité, il n’est plus affiché.',
        'codigo_listo' => 'Je l’ai copié',
    ],

    'formulario' => [
        'tipo' => 'Depuis quel système copier',
        'codigo' => 'Code de connexion',
        'codigo_ayuda' => 'Il se génère dans l’autre installation : en modifiant l’établissement, dans « Partager avec une autre installation ». S’il est déjà connecté, laissez vide pour conserver l’actuel.',
        'codigo_invalido' => 'Ce n’est pas un code de connexion AlojaFlow. Copiez-le en entier.',
    ],
];
