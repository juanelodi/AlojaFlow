<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

return [
    'calendarios_leidos' => 'Calendriers lus : :cuantos',
    'mensajes' => 'Événements : :eventos · Par date : :generados · Envoyés : :enviados',
    'administrador_listo' => 'Administrateur prêt : :correo',
    'tareas_nuevas' => 'Nouvelles tâches : :cuantas',
    'ejecuciones_borradas' => 'Exécutions de synchronisation supprimées : :cuantas',
    'plazos' => 'Délais de conservation : :detalle',
    'copias_borradas' => 'Copies de mises à jour supprimées : :cuantas',
    'vencidas_canceladas' => 'Réservations expirées annulées : :cuantas',
    'demo_no_activa' => 'Cette installation n’est pas en mode démo : rien n’est supprimé.',
    'demo_reiniciada' => 'Démonstration réinitialisée.',
    'paquete' => [
        'sin_cambios' => 'CAMBIOS.md n’a pas de section « ## :version ».',
        'de' => 'Paquet d’AlojaFlow :version',
        'no_pasa' => 'Le paquet ne passe pas la vérification : :errores',
        'instalador' => 'Installateur',
        'instalador_completo' => 'Installateur avec le zip à l’intérieur',
        'archivos' => 'Fichiers',
        'tamano' => 'Taille',
        'tamano_valor' => ':zip (:descomprimido une fois décompressé)',
        'listo' => 'Paquet prêt et vérifié.',
        'para_actualizar' => 'Pour mettre à jour une installation : téléversez-le dans Système → :pantalla, ou dans la console :',
        'para_instalar' => 'Pour installer de zéro : téléversez :archivo sur l’hébergement et ouvrez-le dans le navigateur.',
    ],
    // Instalar sin navegador y enlace de entrada (D-162).
    'instalar' => [
        'ya_estaba' => 'AlojaFlow était déjà installé ici : rien n’a été modifié.',
        'idioma' => 'La langue doit être l’une de : :idiomas.',
        'lista' => 'AlojaFlow installé. Super administrateur : :correo.',
        'enlace' => 'Lien de connexion à usage unique (expire dans 30 minutes) :',
    ],
    'enlace_entrada' => [
        'sin_super' => 'Aucun super administrateur actif avec cette adresse.',
        'dos_pasos' => 'Ce super administrateur utilise la vérification en deux étapes : le lien ne fonctionne pas pour lui, il se connecte avec son mot de passe.',
        'ya_tiene_contrasena' => 'Ce super administrateur a déjà créé son mot de passe et se connecte avec. L\'équipe d\'AlojaFlow se connecte en tant que support, avec l\'autorisation du client.',
    ],
    'tareas_pendientes' => [
        'nada' => 'Rien en attente.',
        'tarea' => 'Tâche',
        'estado' => 'État',
        'ventana' => 'La fenêtre va de 1 à 1440 minutes.',
    ],
];
