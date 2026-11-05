<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

return [
    'politicas' => [
        'no_reembolsable' => 'Non remboursable',
        'no_reembolsable_ayuda' => 'En cas d’annulation, à tout moment, le total est facturé.',
        'modelo' => 'politique',
        'modelos' => 'Politiques',
        'nombre' => 'Nom',
        'dias_gratis' => 'Annulation gratuite jusqu’à (jours avant)',
        'penalizacion' => 'Pénalité ensuite (%)',
        'no_show' => 'Pénalité en cas de non-présentation (%)',
        'deposito' => 'Acompte (%)',
        'deposito_plazo' => 'Délai de paiement de l’acompte (jours)',
        'texto' => 'Texte pour le client',
        'predeterminada' => 'Par défaut',
        'predeterminada_ayuda' => 'S’applique lorsque le canal de la réservation n’a pas la sienne.',
        'vacio' => 'Aucune politique pour l’instant.',
        'resumen_cancelacion' => 'Selon la politique « :politica », la pénalité est de :penalizacion. Déjà payé :pagado : à rembourser :devolver, à encaisser :cobrar.',
        'no_show_accion' => 'Non présenté',
        'no_show_ayuda' => 'Indique que le client ne s’est pas présenté. Dans un établissement indépendant, cela annule aussi la réservation et libère les nuits.',
        'no_show_nota' => 'Note',
        'no_show_ok' => 'Marqué comme non présenté. Pénalité : :importe.',
        'error_no_show' => 'Cela n’est possible qu’à partir du jour d’arrivée, et si l’arrivée n’a pas été enregistrée.',
    ],
];
