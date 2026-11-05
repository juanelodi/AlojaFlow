<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

return [
    'TipoEmpresa' => ['empresa' => 'Empresa', 'agencia' => 'Agencia'],
    'ModoPropiedad' => ['nativa' => 'Independiente', 'subordinada' => 'Subordinada'],
    'MetodoPago' => ['efectivo' => 'Efectivo', 'tarjeta' => 'Tarjeta', 'transferencia' => 'Transferencia', 'otro' => 'Otro'],
    'ModoTarifa' => ['por_noche' => 'Por noche (con persona extra)', 'por_persona' => 'Por persona'],
    'BaseCargo' => ['por_estancia' => 'Por estancia', 'por_noche' => 'Por noche', 'por_persona' => 'Por persona', 'por_persona_noche' => 'Por persona y noche', 'porcentaje_alojamiento' => 'Porcentaje del alojamiento'],
    'TipoMovimiento' => ['gasto' => 'Gasto', 'ingreso' => 'Ingreso'],
    'TipoCupon' => ['porcentaje' => 'Porcentaje', 'importe' => 'Importe fijo'],
    'PaquetePais' => ['mexico' => 'México', 'espana' => 'España', 'estados_unidos' => 'Estados Unidos', 'generico' => 'Genérico'],
    'EstadoPrincipal' => ['activa' => 'Activa', 'pausada' => 'Pausada'],
    'MetodoPeticion' => ['post' => 'POST (recomendado)', 'get' => 'GET (modo compatible)'],
    'EstadoEjecucion' => ['en_curso' => 'En curso', 'correcta' => 'Correcta', 'fallida' => 'Fallida', 'omitida' => 'Omitida'],
    'OrigenEjecucion' => ['programada' => 'Programada', 'manual' => 'Manual'],
    'TipoReserva' => ['reserva' => 'Reserva', 'bloqueo' => 'Bloqueo'],
    'EstadoReserva' => ['sin_confirmar' => 'Sin confirmar', 'confirmada' => 'Confirmada', 'cancelada' => 'Cancelada'],
    'TipoEventoReserva' => ['creada' => 'Nueva', 'modificada' => 'Modificada', 'cancelada' => 'Cancelada', 'eliminada' => 'Eliminada en la principal', 'reactivada' => 'Reactivada'],
    'NivelAviso' => ['informacion' => 'Información', 'advertencia' => 'Advertencia', 'error' => 'Error'],
    'TipoContacto' => ['telefono' => 'Teléfono', 'correo' => 'Correo', 'fax' => 'Fax'],
    'TipoPrincipal' => ['hoteldruid' => 'HotelDruid o AlojaFlow 1.2.x', 'alojaflow' => 'Otra instalación de AlojaFlow'],
    'OrigenFila' => ['hoteldruid' => 'HotelDruid', 'alojaflow' => 'AlojaFlow', 'otra_instalacion' => 'Otra instalación'],
    'EstadoActualizacion' => ['preparada' => 'Preparada', 'aplicando' => 'Aplicando', 'archivos_aplicados' => 'Sin terminar', 'completada' => 'Completada', 'fallida' => 'Fallida', 'revertida' => 'Revertida'],

    'EstadoLimpieza' => [
        'limpia' => 'Limpia',
        'sucia' => 'Sucia',
        'inspeccion' => 'Por revisar',
    ],

    'TipoTarea' => [
        'limpieza_salida' => 'Limpieza de salida',
        'limpieza_estancia' => 'Limpieza durante la estancia',
        'limpieza_profunda' => 'Limpieza profunda',
        'inspeccion' => 'Inspección',
        'mantenimiento' => 'Mantenimiento',
        'otra' => 'Otra',
    ],

    'EstadoTarea' => [
        'pendiente' => 'Pendiente',
        'en_curso' => 'En curso',
        'hecha' => 'Hecha',
        'cancelada' => 'Cancelada',
    ],

    'FrecuenciaLimpieza' => [
        'diaria' => 'Todos los días',
        'semanal' => 'Algunos días de la semana',
        'fechas' => 'Días elegidos',
    ],

    'ClaseArchivo' => [
        'documento_identidad' => 'Documento de identidad',
        'firma' => 'Firma',
        'copia_aceptacion' => 'Copia firmada',
        'foto_tarea' => 'Foto',
        'plano' => 'Plano',
        'factura_xml' => 'Factura (XML)',
        'factura_pdf' => 'Factura (PDF)',
        'exportacion' => 'Exportación',
    ],

    'CanalEnvio' => [
        'correo' => 'Correo',
        'whatsapp_enlace' => 'WhatsApp',
        'webhook' => 'Aviso a otro sistema',
    ],

    'EstadoEnvio' => [
        'pendiente' => 'Por enviar',
        'enviado' => 'Enviado',
        'fallido' => 'Falló',
        'omitido' => 'No se mandó',
        'manual' => 'Listo para copiar',
    ],

    'DisparadorAutomatizacion' => [
        'reserva' => [
            'creada' => 'Al crear la reserva',
            'confirmada' => 'Al confirmarla',
            'modificada' => 'Al cambiarla',
            'cancelada' => 'Al cancelarla',
        ],
        'antes_llegada' => 'Días antes de la llegada',
        'llegada' => 'El día de la llegada',
        'salida' => 'El día de la salida',
        'despues_salida' => 'Días después de la salida',
    ],

    'AccionAutomatizacion' => [
        'correo_huesped' => 'Correo al huésped',
        'correo_fijo' => 'Correo a una dirección fija',
    ],

    'TipoEnlace' => [
        'ical' => 'Calendario iCal',
        'llegada' => 'Llegada en línea',
        'contrato' => 'Contrato',
        'factura' => 'Factura',
    ],

    'EstadoFuenteIcal' => [
        'sin_leer' => 'Sin leer',
        'correcta' => 'Correcto',
        'error' => 'Con error',
    ],

    'TipoChoque' => [
        'bloqueo_reserva' => 'Portal y reserva',
        'reserva_reserva' => 'Dos reservas',
    ],

    'TipoDocumentoLegal' => [
        'aviso_privacidad' => 'Aviso de privacidad',
        'contrato' => 'Condiciones de hospedaje',
        'reglamento' => 'Reglamento interno',
        'politica_cancelacion' => 'Política de cancelación',
    ],

    'EstadoRegistroLlegada' => [
        'pendiente' => 'Sin terminar',
        'recibido' => 'Por revisar',
        'revisado' => 'Revisado',
    ],

    'VerificacionAceptacion' => [
        'casilla' => 'Casilla marcada',
        'otp_correo' => 'Código por correo',
    ],

    'TipoSolicitudPrivacidad' => [
        'exportacion' => 'Exportación',
        'anonimizacion' => 'Anonimización',
    ],

    'EstadoFactura' => [
        'emitida' => 'Emitida',
        'registrada' => 'Timbrada',
        'cancelada' => 'Cancelada',
    ],

    'TipoDocumentoFiscal' => [
        'factura' => 'Factura',
        'recibo' => 'Recibo sin valor fiscal',
    ],

    'ModoFiscal' => [
        'pruebas' => 'Pruebas',
        'produccion' => 'Producción',
    ],
];
