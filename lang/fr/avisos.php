<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

return [
    'sincronizacion_fallida' => 'La synchronisation avec l’installation principale échoue (:codigo) : :mensaje',
    'principal_sin_inicio_sesion' => 'L’installation principale :url n’exige pas de connexion : toute personne qui connaît l’adresse peut télécharger sa copie de données. Activez la connexion dans HotelDruid (Configurer → Utilisateurs).',
    'ical_falla' => 'Le calendrier « :fuente » n’a pas pu être lu depuis un moment. Vérifiez-le dans Calendriers iCal.',
    'choques' => 'Des dates sont occupées deux fois. Vérifiez-les dans Conflits.',
    'envios_fallidos' => 'Certains messages n’ont pas pu être envoyés après plusieurs tentatives. Vérifiez-les dans Messages.',
    'pago_no_coincide' => 'Un paiement en ligne de la réservation :reserva ne correspond pas au montant attendu. Vérifiez-le dans votre compte de la passerelle.',
    'pago_sin_lugar' => 'Un paiement en ligne a été reçu pour la réservation :reserva, mais ses nuits avaient déjà été vendues. Réglez cela avec le client.',
    'copia_no_borrada' => 'La copie n’a pas pu être supprimée sur l’installation principale ; elle reste dans son dossier dati/. Vérifiez les droits de ce dossier.',
];
