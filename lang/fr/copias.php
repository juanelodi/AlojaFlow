<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

return [
    'titulo' => 'Sauvegardes',
    'explicacion' => 'Chaque sauvegarde contient toute la base de données et les fichiers envoyés, dans un zip que vous pouvez télécharger et recharger ici ou dans une autre installation.',
    'estado_automaticas' => 'Une sauvegarde automatique est créée chaque nuit et les :cuantas dernières sont conservées.',
    'estado_manuales' => 'Les sauvegardes automatiques sont désactivées : elles ne sont créées qu’à votre demande.',
    'aviso_fuera' => 'Ces sauvegardes se trouvent sur ce même serveur. Téléchargez-en une de temps en temps et gardez-la ailleurs, avec le fichier de configuration : c’est votre seule protection si le serveur est perdu.',
    'crear' => 'Créer une sauvegarde maintenant',
    'creada' => 'Sauvegarde créée : :nombre',
    'subir' => 'Envoyer une sauvegarde',
    'subir_ayuda' => 'Envoyez un zip téléchargé auparavant, de cette installation d’AlojaFlow ou d’une autre. Il est seulement enregistré : pour l’utiliser, cliquez ensuite sur « Restaurer ».',
    'archivo' => 'Fichier de sauvegarde (zip)',
    'subida' => 'Sauvegarde envoyée : :nombre',
    'ajustes' => 'Réglages',
    'automaticas' => 'Créer une sauvegarde automatique chaque nuit',
    'automaticas_ayuda' => 'Nécessite la tâche planifiée du serveur ou que quelqu’un utilise le système ce jour-là.',
    'conservar' => 'Nombre de sauvegardes à conserver',
    'conservar_ayuda' => 'À la création d’une nouvelle, les plus anciennes sont supprimées. Chaque sauvegarde contient des données personnelles : n’en gardez pas plus que nécessaire.',
    'ajustes_guardados' => 'Réglages enregistrés.',
    'descargar_env' => 'Télécharger la configuration',
    'descargar_env_ayuda' => 'Le fichier .env contient la clé qui chiffre les documents et mots de passe enregistrés, ainsi que le mot de passe de la base de données. Sans cette clé, une sauvegarde restaurée sur un autre serveur ne pourra pas lire ces données. Gardez-le en lieu sûr, à l’écart des sauvegardes.',
    'contrasena' => 'Votre mot de passe',
    'lista' => 'Sauvegardes enregistrées',
    'vacio' => 'Aucune sauvegarde pour l’instant.',
    'fecha' => 'Date',
    'origen' => 'Origine',
    'version' => 'Version',
    'tamano' => 'Taille',
    'acciones' => 'Actions',
    'otra_clave' => 'D’une autre installation : ses données chiffrées ne pourront pas être lues avec la clé actuelle.',
    'origenes' => [
        'manual' => 'Manuelle',
        'programada' => 'Automatique',
        'antes_de_restaurar' => 'Avant restauration',
    ],
    'descargar' => 'Télécharger',
    'restaurar' => 'Restaurer',
    'restaurar_titulo' => 'Restaurer cette sauvegarde ?',
    'restaurar_texto' => 'Toutes les données actuelles sont remplacées par celles de la sauvegarde. Une autre sauvegarde de l’état actuel est d’abord enregistrée, au cas où il faudrait revenir en arrière. À la fin, vous devrez vous reconnecter.',
    'eliminar' => 'Supprimer',
    'eliminar_titulo' => 'Supprimer cette sauvegarde ?',
    'eliminada' => 'Sauvegarde supprimée.',
    'error' => [
        'no_escribe' => 'Impossible d’écrire la sauvegarde dans storage/app/private/copias. Vérifiez les droits et l’espace libre.',
        'no_existe' => 'Cette sauvegarde n’existe plus.',
        'no_es_copia' => 'Ce fichier n’est pas une sauvegarde d’AlojaFlow ou il est endommagé.',
        'mas_nueva' => 'Cette sauvegarde provient de la version :version, plus récente que celle installée. Mettez AlojaFlow à jour avant de la restaurer.',
        'restaurar' => 'Impossible de restaurer la sauvegarde ; les données sont revenues à leur état précédent. Détail : :detalle',
    ],
];
