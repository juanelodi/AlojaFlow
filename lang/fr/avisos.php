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
    'pago_duplicado' => 'Un paiement en ligne de la réservation :reserva dépasse ce qui était dû. Vérifiez-le dans Paiements et, si nécessaire, remboursez la différence depuis votre compte de la passerelle.',
    'pago_en_cancelada' => 'Un paiement en ligne a été reçu pour la réservation :reserva, qui était déjà annulée. Décidez avec le client s’il faut la réactiver ou le rembourser.',
    'pago_sin_lugar' => 'Un paiement en ligne a été reçu pour la réservation :reserva, mais ses nuits avaient déjà été vendues. Réglez cela avec le client.',
    'motor_saturado' => 'Trop de réservations en ligne sont arrivées en une heure et le formulaire a été arrêté pendant une heure. Vérifiez dans Réservations si elles sont réelles ; celles qui ne sont pas confirmées s’annulent toutes seules.',
    'copia_no_borrada' => 'La copie n’a pas pu être supprimée sur l’installation principale ; elle reste dans son dossier dati/. Vérifiez les droits de ce dossier.',
    'registro_por_vencer' => 'Un numéro d’enregistrement ou de licence de cet établissement expire dans moins de 30 jours, ou a déjà expiré. Renouvelez-le et mettez à jour la date dans Enregistrements touristiques.',
    'comprobantes_por_revisar' => 'Des justificatifs de paiement sont à vérifier. Voir Finances → Justificatifs de paiement.',
    'grupo_habitaciones_liberadas' => ':habitaciones chambres sans responsable du groupe :grupo ont été libérées, car la date limite est passée.',
    'autoridad_rechazadas' => 'Le registre des voyageurs a rejeté des communications. Vérifiez-les dans Exploitation → Registre des voyageurs.',
    'autoridad_faltan_datos' => 'Il manque des données à certaines communications du registre. Complétez-les dans Exploitation → Registre des voyageurs.',
    'autoridad_atrasadas' => 'Des communications au registre sont en retard : le délai est de 24 heures. Traitez-les dans Exploitation → Registre des voyageurs.',
    'autoridad_sin_conexion' => 'Le registre des voyageurs est injoignable après plusieurs tentatives. Vérifiez la connexion dans Exploitation → Registre des voyageurs.',
];
