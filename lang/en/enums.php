<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

return [
    'ClaseCargoCanal' => [
        'servicio' => 'Service charge',
        'procesamiento_pago' => 'Payment processing charge',
        'promocion' => 'Promotion or visibility',
        'impuesto_sobre_cargos' => 'Tax on its charges',
        'retencion_renta' => 'Income tax withholding',
        'retencion_iva' => 'VAT withholding',
        'otro' => 'Other',
    ],
    'CalculoCargoCanal' => [
        'porcentaje' => 'Percentage',
        'fijo_reserva' => 'Fixed amount per booking',
        'fijo_noche' => 'Fixed amount per night',
    ],
    'BaseCargoCanal' => [
        'tarifa_base' => 'Base rate (accommodation, no extras or taxes)',
        'sin_impuestos' => 'Total before taxes',
        'total_final' => 'Final total (what the guest pays)',
        'comisiones' => 'Channel commission and charges',
    ],
    'TipoEmpresa' => ['empresa' => 'Company', 'agencia' => 'Agency'],
    'ModoPropiedad' => ['nativa' => 'Independent', 'subordinada' => 'Subordinate'],
    'MetodoPago' => ['efectivo' => 'Cash', 'tarjeta' => 'Card', 'transferencia' => 'Transfer', 'otro' => 'Other'],
    'ModoTarifa' => ['por_noche' => 'Per night (with extra person)', 'por_persona' => 'Per person'],
    'BaseCargo' => ['por_estancia' => 'Per stay', 'por_noche' => 'Per night', 'por_persona' => 'Per person', 'por_persona_noche' => 'Per person per night', 'porcentaje_alojamiento' => 'Percentage of lodging'],
    'TipoMovimiento' => ['gasto' => 'Expense', 'ingreso' => 'Income'],
    'TipoCupon' => ['porcentaje' => 'Percentage', 'importe' => 'Fixed amount'],
    'PaquetePais' => ['mexico' => 'Mexico', 'espana' => 'Spain', 'estados_unidos' => 'United States', 'generico' => 'Generic'],
    'EstadoPrincipal' => ['activa' => 'Active', 'pausada' => 'Paused'],
    'MetodoPeticion' => ['post' => 'POST (recommended)', 'get' => 'GET (compatibility mode)'],
    'EstadoEjecucion' => ['en_curso' => 'Running', 'correcta' => 'Successful', 'fallida' => 'Failed', 'omitida' => 'Skipped'],
    'OrigenEjecucion' => ['programada' => 'Scheduled', 'manual' => 'Manual'],
    'TipoReserva' => ['reserva' => 'Booking', 'bloqueo' => 'Block'],
    'EstadoReserva' => ['sin_confirmar' => 'Unconfirmed', 'confirmada' => 'Confirmed', 'cancelada' => 'Cancelled'],
    'TipoEventoReserva' => ['creada' => 'New', 'modificada' => 'Modified', 'cancelada' => 'Cancelled', 'eliminada' => 'Deleted in the main installation', 'reactivada' => 'Reactivated'],
    'NivelAviso' => ['informacion' => 'Information', 'advertencia' => 'Warning', 'error' => 'Error'],
    'EntidadCampo' => ['huesped' => 'A guest', 'reserva' => 'A booking'],
    'TipoCampoPropio' => ['texto' => 'Text', 'si_no' => 'Yes or no'],
    'AlcanceDocumento' => ['reserva' => 'For one booking', 'periodo' => 'For several bookings'],
    'TipoContacto' => ['telefono' => 'Phone', 'correo' => 'Email', 'fax' => 'Fax'],
    'TipoPrincipal' => ['hoteldruid' => 'HotelDruid or AlojaFlow 1.2.x', 'alojaflow' => 'Another AlojaFlow installation'],
    'OrigenFila' => ['hoteldruid' => 'HotelDruid', 'alojaflow' => 'AlojaFlow', 'otra_instalacion' => 'Another installation'],
    'EstadoActualizacion' => ['preparada' => 'Prepared', 'aplicando' => 'Applying', 'archivos_aplicados' => 'Unfinished', 'completada' => 'Completed', 'fallida' => 'Failed', 'revertida' => 'Rolled back'],

    'EstadoLimpieza' => [
        'limpia' => 'Clean',
        'sucia' => 'Dirty',
        'inspeccion' => 'To inspect',
    ],

    'TipoTarea' => [
        'limpieza_salida' => 'Check-out cleaning',
        'limpieza_estancia' => 'Cleaning during the stay',
        'limpieza_profunda' => 'Deep cleaning',
        'inspeccion' => 'Inspection',
        'mantenimiento' => 'Maintenance',
        'otra' => 'Other',
    ],

    'EstadoTarea' => [
        'pendiente' => 'Pending',
        'en_curso' => 'In progress',
        'hecha' => 'Done',
        'cancelada' => 'Cancelled',
    ],

    'FrecuenciaLimpieza' => [
        'diaria' => 'Every day',
        'semanal' => 'Some days of the week',
        'fechas' => 'Chosen days',
    ],

    'ClaseArchivo' => [
        'documento_identidad' => 'Identity document',
        'firma' => 'Signature',
        'copia_aceptacion' => 'Signed copy',
        'foto_tarea' => 'Photo',
        'plano' => 'Floor plan',
        'exportacion' => 'Export',
        'comprobante' => 'Payment receipt',
        'foto_publica' => 'Public page photo',
        'comprobante_gasto' => 'Expense receipt',
    ],

    'CanalEnvio' => [
        'correo' => 'Email',
        'whatsapp_enlace' => 'WhatsApp',
        'whatsapp_api' => 'WhatsApp (Meta API)',
        'webhook' => 'Webhook',
    ],

    'EstadoEnvio' => [
        'pendiente' => 'To send',
        'enviado' => 'Sent',
        'fallido' => 'Failed',
        'omitido' => 'Not sent',
        'manual' => 'Ready to copy',
    ],

    'DisparadorAutomatizacion' => [
        'reserva' => [
            'creada' => 'When the booking is created',
            'confirmada' => 'When it is confirmed',
            'modificada' => 'When it changes',
            'cancelada' => 'When it is cancelled',
        ],
        'antes_llegada' => 'Days before arrival',
        'llegada' => 'On the day of arrival',
        'salida' => 'On the day of departure',
        'despues_salida' => 'Days after departure',
        'antes_cuota' => 'Days before a scheduled payment',
        'cuota_vencida' => 'Days after a payment is overdue',
    ],

    'AccionAutomatizacion' => [
        'crear_tarea' => 'Create a task for the team',
        'nota_bitacora' => 'Leave an important note in the shift log',
        'correo_huesped' => 'Email to the guest',
        'correo_fijo' => 'Email to a fixed address',
    ],

    'TipoEnlace' => [
        'pago' => 'Payment',
        'opinion' => 'Review',
        'ical' => 'iCal calendar',
        'llegada' => 'Online check-in',
        'contrato' => 'Contract',
        'grupo' => 'Group',
        'mi_reserva' => 'My booking',
        'propietario' => 'Owner portal',
        'guia' => 'Guide',
    ],

    'EstadoFuenteIcal' => [
        'sin_leer' => 'Not read',
        'correcta' => 'OK',
        'error' => 'With error',
    ],

    'TipoChoque' => [
        'bloqueo_reserva' => 'Portal and booking',
        'reserva_reserva' => 'Two bookings',
    ],

    'TipoDocumentoLegal' => [
        'aviso_privacidad' => 'Privacy notice',
        'contrato' => 'Accommodation terms',
        'reglamento' => 'House rules',
        'politica_cancelacion' => 'Cancellation policy',
        'contrato_grupo' => 'Group contract',
    ],

    'EstadoRegistroLlegada' => [
        'pendiente' => 'Not finished',
        'recibido' => 'To review',
        'revisado' => 'Reviewed',
    ],

    'VerificacionAceptacion' => [
        'casilla' => 'Box ticked',
        'otp_correo' => 'Code by email',
    ],

    'TipoSolicitudPrivacidad' => [
        'exportacion' => 'Export',
        'anonimizacion' => 'Anonymization',
    ],



    'EstadoGrupo' => [
        'tentativo' => 'Tentative',
        'confirmado' => 'Confirmed',
        'cancelado' => 'Cancelled',
    ],
    'ModoPrecioGrupo' => [
        'tarifa' => 'The rate of each unit',
        'total' => 'A total price, shared by nights',
        'por_capacidad' => 'A price per night by how many fit',
    ],
    'EstadoComprobante' => [
        'por_confirmar' => 'To be confirmed',
        'confirmado' => 'Confirmed',
        'rechazado' => 'Rejected',
    ],
    'EstadoEnvioAutoridad' => [
        'pendiente' => 'To send',
        'falta_datos' => 'Details missing',
        'enviado' => 'Sent, no answer yet',
        'aceptado' => 'Reported',
        'rechazado' => 'Rejected',
        'por_anular' => 'To cancel',
        'anulando' => 'Cancelling',
        'anulado' => 'Cancelled',
    ],
    'MomentoGuia' => [
        'siempre' => 'Always',
        'antes' => 'Before arrival',
        'durante' => 'During the stay',
        'despues' => 'On departure',
    ],
    'TipoSeccionGuia' => [
        'llegada' => 'Getting there',
        'wifi' => 'Wi-Fi',
        'reglas' => 'House rules',
        'equipo' => 'Using the equipment',
        'recomendaciones' => 'Recommendations',
        'contactos' => 'Contacts',
        'salida' => 'Before you leave',
        'otro' => 'Other',
    ],
    'TipoLugar' => [
        'tienda' => 'Tent',
        'vehiculo' => 'RV or camper',
        'cabana' => 'Cabin',
        'glamping' => 'Glamping',
        'otro' => 'Other',
    ],
    'TipoEquipo' => [
        'tienda' => 'Tent',
        'camper' => 'Camper or van',
        'remolque' => 'Trailer',
        'casa_rodante' => 'Motorhome',
        'otro' => 'Other',
    ],
    'ServicioMedido' => [
        'luz' => 'Electricity',
        'agua' => 'Water',
        'gas' => 'Gas',
    ],
    'FaseEstadoCuenta' => [
        'borrador' => 'Draft',
        'enviado' => 'Sent',
        'pagado' => 'Paid',
    ],
];
