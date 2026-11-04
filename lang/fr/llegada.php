<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

return [
    'titulo' => 'Enregistrement en ligne',
    'ayuda' => 'Enregistrez-vous à l’avance et allez directement à votre chambre. Seul l’établissement voit vos données.',
    'ya_recibido' => 'Nous avons déjà reçu vos données.',
    'titular' => 'Personne qui réserve',
    'acompanante' => 'Accompagnant :n',
    'nombre' => 'Prénom',
    'apellido1' => 'Nom',
    'apellido2' => 'Second nom',
    'fecha_nacimiento' => 'Date de naissance',
    'nacionalidad' => 'Nationalité',
    'documento_tipo' => 'Type de pièce d’identité',
    'documento_numero' => 'Numéro de pièce d’identité',
    'foto' => 'Photo de la pièce d’identité (facultatif)',
    'correo' => 'E-mail',
    'telefono' => 'Téléphone',
    'domicilio' => 'Adresse',
    'domicilio_calle' => 'Rue et numéro',
    'domicilio_ciudad' => 'Ville',
    'domicilio_region' => 'État ou région',
    'domicilio_cp' => 'Code postal',
    'domicilio_pais' => 'Pays',
    'hora' => 'À quelle heure arrivez-vous, environ ?',
    'notas' => 'Quelque chose que nous devrions savoir',
    'acepto' => 'J’ai lu et j’accepte : :documento',
    'confirmo' => 'Je confirme que ces données sont exactes.',
    'privacidad_nota' => 'La photo de votre pièce d’identité est conservée chiffrée et supprimée peu après votre départ.',
    'enviar' => 'Envoyer mes données',
    'verificar_titulo' => 'Saisissez le code',
    'verificar_ayuda' => 'Nous avons envoyé un code à 6 chiffres à :correo. Confirmez que c’est bien vous qui acceptez les documents.',
    'codigo' => 'Code',
    'verificar' => 'Confirmer',
    'reenviar' => 'Envoyer un autre code',
    'codigo_incorrecto' => 'Le code est incorrect ou a expiré.',
    'codigo_no_enviado' => 'Le code n’a pas pu être envoyé. Réessayez dans quelques minutes.',
    'codigo_reenviado' => 'Nous vous avons envoyé un nouveau code.',
    'otp_asunto' => 'Votre code pour :propiedad',
    'otp_cuerpo' => "Votre code est : :codigo\n\nIl expire dans :minutos minutes. Si ce n’était pas vous, ignorez ce message.\n\n:propiedad",
    'listo_titulo' => 'C’est fait ! Nous avons reçu vos données.',
    'listo_ayuda' => 'Si vous devez modifier quelque chose, prévenez l’établissement. Au plaisir de vous accueillir.',
    'tarjeta' => 'Fiche d’enregistrement',
    'firma' => 'Signature du client',
    'tarjeta_nota' => 'Données fournies par le client lors de son enregistrement en ligne.',
    'anonimizado' => 'Anonymisé #:id',
    'constancia' => [
        'firmante' => 'Accepté par',
        'reserva' => 'Réservation',
        'fecha' => 'Date et heure (UTC)',
        'verificacion' => 'Mode de vérification',
        'ip' => 'Adresse IP',
        'version' => 'Version du document',
        'huella' => 'Empreinte du texte (SHA-256)',
    ],
];
