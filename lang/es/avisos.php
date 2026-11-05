<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

return [
    'sincronizacion_fallida' => 'La sincronización con la principal falla (:codigo): :mensaje',
    'principal_sin_inicio_sesion' => 'La principal :url no tiene activado el inicio de sesión: cualquiera que conozca la dirección puede descargar su copia de datos. Actívalo en HotelDruid (Configurar → Usuarios).',
    'ical_falla' => 'No se ha podido leer el calendario «:fuente» desde hace rato. Revísalo en Calendarios iCal.',
    'choques' => 'Hay fechas ocupadas dos veces. Revísalas en Choques.',
    'envios_fallidos' => 'Algunos mensajes no se pudieron enviar después de varios intentos. Revísalos en Mensajes.',
    'pago_no_coincide' => 'Un cobro en línea de la reserva :reserva no coincide con el importe esperado. Revísalo en tu cuenta de la pasarela.',
    'pago_duplicado' => 'Un pago en línea de la reserva :reserva supera lo que debía. Revísalo en Pagos y, si hace falta, devuelve la diferencia desde tu cuenta de la pasarela.',
    'pago_en_cancelada' => 'Se recibió un pago en línea de la reserva :reserva, que ya estaba cancelada. Decide con el huésped si la reactivas o le devuelves el dinero.',
    'pago_sin_lugar' => 'Se recibió un pago en línea de la reserva :reserva, pero sus noches ya se habían vendido. Resuélvelo con el huésped.',
    'motor_saturado' => 'Llegaron demasiadas reservas en línea en una hora y se detuvo el formulario por una hora. Revisa si son reales en Reservas; las que no se confirmen se cancelan solas.',
    'copia_no_borrada' => 'No se pudo borrar la copia en la principal; queda en su carpeta dati/. Revisa los permisos de esa carpeta.',
    'registro_por_vencer' => 'Un número de registro o licencia de esta propiedad vence en menos de 30 días, o ya venció. Renuévalo y actualiza la fecha en Registros turísticos.',
];
