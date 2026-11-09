<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

return [
    'titulo' => 'Support AlojaFlow',
    'explicacion' => 'Par défaut, personne de l\'équipe AlojaFlow n\'entre dans votre système. Si vous demandez de l\'aide, vous pouvez autoriser le support à entrer pendant 24 heures. La personne qui vous aide se connecte à son nom, tout ce qu\'elle fait est enregistré dans l\'audit et, à la fin du délai, son utilisateur est désactivé automatiquement.',
    'permitir' => 'Autoriser le support AlojaFlow à entrer pendant 24 heures',
    'extender' => 'Prolonger l\'autorisation de 24 heures à partir de maintenant',
    'permitir_ayuda' => 'L\'équipe AlojaFlow pourra se connecter en tant qu\'administrateur, sans changer les modules ni les administrateurs, pendant les 24 prochaines heures. Vous pouvez retirer l\'autorisation à tout moment.',
    'permitido' => 'C\'est fait : le support peut entrer jusqu\'au :hasta.',
    'retirar' => 'Retirer l\'autorisation',
    'retirar_ayuda' => 'La personne du support connectée est déconnectée immédiatement et ne pourra plus entrer.',
    'retirado' => 'Autorisation retirée.',
    'sin_permiso_estado' => 'Le support n\'est pas autorisé à entrer.',
    'permitido_estado' => 'Le support peut entrer jusqu\'au :hasta.',
    'accesos' => 'Connexions du support (90 derniers jours)',
    'sin_accesos' => 'Le support ne s\'est pas connecté.',
    'quien' => 'Qui',
    'cuando' => 'Quand',
    'hasta' => 'Jusqu\'à',
    'motivo' => 'Motif',
    'dentro' => 'Connecté maintenant',
    'con_permiso' => 'Avec votre autorisation',
    'emergencia' => 'Urgence : :motivo',
    'usuario' => 'Support AlojaFlow — :nombre',
    'solo_instancia' => 'L\'accès du support n\'existe que dans le service hébergé d\'AlojaFlow.',
    'sin_permiso' => 'Le client n\'a pas autorisé le support. Sans autorisation, on n\'entre qu\'en cas d\'urgence, avec le motif (--emergencia).',
    'nombre_invalido' => 'Il manque le nom de la personne qui aide (80 caractères au maximum).',
    'minutos_invalidos' => 'Les minutes vont de 1 à :maximo.',
    'emergencia_asunto' => 'Le support AlojaFlow est entré dans votre système en raison d\'une urgence',
    'emergencia_cuerpo' => "Bonjour,\n\n:nombre, du support AlojaFlow, est entré dans votre système sans votre autorisation en raison d'une urgence.\n\nMotif : :motivo\n\nL'accès se termine le :hasta. Tout ce qu'il fait est enregistré dans l'audit (Système → Audit). Pour toute question, répondez à cet e-mail ou écrivez au support.",
    'consola' => [
        'listo' => 'Accès prêt pour :usuario jusqu\'au :hasta. Le lien ne sert qu\'une fois et expire dans 5 minutes.',
    ],
];
