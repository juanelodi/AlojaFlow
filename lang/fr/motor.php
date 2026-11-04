<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

return [
    'titulo' => 'Réservez votre séjour',
    'idioma' => 'Langue',
    'buscar' => 'Rechercher',
    'reservar' => 'Réserver',
    'capacidad' => 'Jusqu\'à :personas personnes',
    'quedan' => '{1} il en reste 1|[2,*] il en reste :count',
    'sin_resultados' => 'Rien de disponible à ces dates. Essayez-en d\'autres.',
    'tus_datos' => 'Vos coordonnées',
    'n_personas' => ':personas personnes',
    'anticipo' => 'Un acompte de :porcentaje % est demandé pour garantir la réservation.',
    'no_llenar' => 'Ne remplissez pas ce champ',
    'nombre' => 'Prénom',
    'apellido' => 'Nom',
    'correo' => 'E-mail',
    'telefono' => 'Téléphone',
    'extras' => 'Suppléments',
    'cupon' => 'Code de réduction',
    'comentario' => 'Commentaires (heure d\'arrivée, demandes…)',
    'condiciones' => 'les conditions',
    'acepto' => 'J\'accepte les conditions de réservation et l\'utilisation de mes données pour la gérer.',
    'confirmar' => 'Réserver',
    'volver' => 'Retour',
    'origen' => 'Moteur de réservation',
    'concepto' => 'Réservation :numero à :propiedad',
    'tu_reserva' => 'Votre réservation',
    'estado_confirmada' => 'Votre réservation est confirmée. Au plaisir de vous accueillir !',
    'estado_cancelada' => 'Cette réservation est annulée.',
    'estado_pendiente' => 'Nous avons bien reçu votre demande. Nous vous préviendrons dès sa confirmation.',
    'estado_pagar' => 'Votre place est retenue jusqu\'au :fecha. Payez l\'acompte pour confirmer la réservation.',
    'pagar_titulo' => 'Payer :importe',
    'pagar_ayuda' => 'La page sécurisée de la société de paiement s\'ouvre. Vos données de carte ne passent pas par ce site.',
    'pagar_con' => [
        'stripe' => 'Payer par carte (Stripe)',
        'mercadopago' => 'Payer avec Mercado Pago',
    ],
    'instrucciones' => 'Comment payer',
    'pie' => 'Réservations avec AlojaFlow',
    'error' => [
        'cerrado' => 'Les réservations en ligne ne sont pas disponibles pour le moment.',
        'fechas' => 'Vérifiez les dates : le départ doit être après l\'arrivée.',
        'fecha_pasada' => 'Cette date d\'arrivée n\'est plus réservable en ligne.',
        'sin_lugar' => 'Il n\'y a plus de place à ce tarif pour ces dates.',
        'estancia_minima' => 'Séjour minimum : :noches nuits.',
        'pasarela' => 'Le paiement n\'a pas pu s\'ouvrir. Réessayez dans quelques minutes.',
        'nada_que_cobrar' => 'Cette réservation n’a plus rien à payer en ligne.',
    ],
];
