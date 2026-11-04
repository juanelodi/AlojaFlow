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
    'pago_sin_lugar' => 'Se recibió un pago en línea de la reserva :reserva, pero sus noches ya se habían vendido. Resuélvelo con el huésped.',
    'copia_no_borrada' => 'No se pudo borrar la copia en la principal; queda en su carpeta dati/. Revisa los permisos de esa carpeta.',
];
