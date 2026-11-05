<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

return [
    'ModoPropiedad' => [
        'nativa' => 'Indépendant',
        'subordinada' => 'Subordonné',
    ],
    'MetodoPago' => [
        'efectivo' => 'Espèces',
        'tarjeta' => 'Carte',
        'transferencia' => 'Virement',
        'otro' => 'Autre',
    ],
    'ModoTarifa' => [
        'por_noche' => 'Par nuit (avec personne supplémentaire)',
        'por_persona' => 'Par personne',
    ],
    'BaseCargo' => [
        'por_estancia' => 'Par séjour',
        'por_noche' => 'Par nuit',
        'por_persona' => 'Par personne',
        'por_persona_noche' => 'Par personne et par nuit',
        'porcentaje_alojamiento' => 'Pourcentage de l’hébergement',
    ],
    'TipoMovimiento' => [
        'gasto' => 'Dépense',
        'ingreso' => 'Recette',
    ],
    'TipoCupon' => [
        'porcentaje' => 'Pourcentage',
        'importe' => 'Montant fixe',
    ],
    'PaquetePais' => [
        'mexico' => 'Mexique',
        'espana' => 'Espagne',
        'estados_unidos' => 'États-Unis',
        'generico' => 'Générique',
    ],
    'EstadoPrincipal' => [
        'activa' => 'Active',
        'pausada' => 'En pause',
    ],
    'MetodoPeticion' => [
        'post' => 'POST (recommandé)',
        'get' => 'GET (mode compatible)',
    ],
    'EstadoEjecucion' => [
        'en_curso' => 'En cours',
        'correcta' => 'Réussie',
        'fallida' => 'Échouée',
        'omitida' => 'Ignorée',
    ],
    'OrigenEjecucion' => [
        'programada' => 'Planifiée',
        'manual' => 'Manuelle',
    ],
    'TipoReserva' => [
        'reserva' => 'Réservation',
        'bloqueo' => 'Blocage',
    ],
    'EstadoReserva' => [
        'sin_confirmar' => 'À confirmer',
        'confirmada' => 'Confirmée',
        'cancelada' => 'Annulée',
    ],
    'TipoEventoReserva' => [
        'creada' => 'Nouvelle',
        'modificada' => 'Modifiée',
        'cancelada' => 'Annulée',
        'eliminada' => 'Supprimée dans l’installation principale',
        'reactivada' => 'Réactivée',
    ],
    'NivelAviso' => [
        'informacion' => 'Information',
        'advertencia' => 'Avertissement',
        'error' => 'Erreur',
    ],
    'TipoContacto' => [
        'telefono' => 'Téléphone',
        'correo' => 'E-mail',
        'fax' => 'Fax',
    ],
    'TipoPrincipal' => ['hoteldruid' => 'HotelDruid ou AlojaFlow 1.2.x', 'alojaflow' => 'Une autre installation d’AlojaFlow'],
    'OrigenFila' => [
        'hoteldruid' => 'HotelDruid',
        'alojaflow' => 'AlojaFlow', 'otra_instalacion' => 'Autre installation'],
    'EstadoActualizacion' => [
        'preparada' => 'Préparée',
        'aplicando' => 'En cours d’application',
        'archivos_aplicados' => 'Inachevée',
        'completada' => 'Terminée',
        'fallida' => 'Échouée',
        'revertida' => 'Annulée',
    ],
    'EstadoLimpieza' => [
        'limpia' => 'Propre',
        'sucia' => 'Sale',
        'inspeccion' => 'À vérifier',
    ],
    'TipoTarea' => [
        'limpieza_salida' => 'Ménage de départ',
        'limpieza_estancia' => 'Ménage pendant le séjour',
        'limpieza_profunda' => 'Grand ménage',
        'inspeccion' => 'Inspection',
        'mantenimiento' => 'Maintenance',
        'otra' => 'Autre',
    ],
    'EstadoTarea' => [
        'pendiente' => 'À faire',
        'en_curso' => 'En cours',
        'hecha' => 'Terminée',
        'cancelada' => 'Annulée',
    ],
    'FrecuenciaLimpieza' => [
        'diaria' => 'Tous les jours',
        'semanal' => 'Certains jours de la semaine',
    ],
    'ClaseArchivo' => [
        'documento_identidad' => 'Pièce d’identité',
        'firma' => 'Signature',
        'copia_aceptacion' => 'Copie signée',
        'foto_tarea' => 'Photo',
        'plano' => 'Plan',
        'factura_xml' => 'Facture (XML)',
        'factura_pdf' => 'Facture (PDF)',
        'exportacion' => 'Exportation',
    ],
    'CanalEnvio' => [
        'correo' => 'E-mail',
        'whatsapp_enlace' => 'WhatsApp',
        'webhook' => 'Notification à un autre système',
    ],
    'EstadoEnvio' => [
        'pendiente' => 'À envoyer',
        'enviado' => 'Envoyé',
        'fallido' => 'Échec',
        'omitido' => 'Non envoyé',
        'manual' => 'Prêt à copier',
    ],
    'DisparadorAutomatizacion' => [
        'reserva' => [
            'creada' => 'À la création de la réservation',
            'confirmada' => 'À sa confirmation',
            'modificada' => 'À sa modification',
            'cancelada' => 'À son annulation',
        ],
        'antes_llegada' => 'Jours avant l’arrivée',
        'llegada' => 'Le jour de l’arrivée',
        'salida' => 'Le jour du départ',
        'despues_salida' => 'Jours après le départ',
    ],
    'AccionAutomatizacion' => [
        'correo_huesped' => 'E-mail au client',
        'correo_fijo' => 'E-mail à une adresse fixe',
    ],
    'TipoEnlace' => [
        'ical' => 'Calendrier iCal',
        'llegada' => 'Enregistrement en ligne',
        'contrato' => 'Contrat',
        'factura' => 'Facture',
    ],
    'EstadoFuenteIcal' => [
        'sin_leer' => 'Non lu',
        'correcta' => 'Correct',
        'error' => 'En erreur',
    ],
    'TipoChoque' => [
        'bloqueo_reserva' => 'Portail et réservation',
        'reserva_reserva' => 'Deux réservations',
    ],
    'TipoDocumentoLegal' => [
        'aviso_privacidad' => 'Politique de confidentialité',
        'contrato' => 'Conditions d’hébergement',
        'reglamento' => 'Règlement intérieur',
        'politica_cancelacion' => 'Politique d’annulation',
    ],
    'EstadoRegistroLlegada' => [
        'pendiente' => 'Inachevé',
        'recibido' => 'À vérifier',
        'revisado' => 'Vérifié',
    ],
    'VerificacionAceptacion' => [
        'casilla' => 'Case cochée',
        'otp_correo' => 'Code par e-mail',
    ],
    'TipoSolicitudPrivacidad' => [
        'exportacion' => 'Exportation',
        'anonimizacion' => 'Anonymisation',
    ],
    'EstadoFactura' => [
        'emitida' => 'Émise',
        'registrada' => 'Timbrée',
        'cancelada' => 'Annulée',
    ],
    'TipoDocumentoFiscal' => [
        'factura' => 'Facture',
        'recibo' => 'Reçu sans valeur fiscale',
    ],
    'ModoFiscal' => [
        'pruebas' => 'Test',
        'produccion' => 'Production',
    ],
];
