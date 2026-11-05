<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

return [
    'sincronizacion_fallida' => 'Synchronisation with the main installation is failing (:codigo): :mensaje',
    'principal_sin_inicio_sesion' => 'The main installation :url does not require login: anyone who knows the address can download its data backup. Enable login in HotelDruid (Configure → Users).',
    'ical_falla' => 'The calendar «:fuente» has not been readable for a while. Check it in iCal calendars.',
    'choques' => 'Some dates are occupied twice. Check them in Clashes.',
    'envios_fallidos' => 'Some messages could not be sent after several attempts. Check them in Messages.',
    'pago_no_coincide' => 'An online charge for booking :reserva does not match the expected amount. Check it in your gateway account.',
    'pago_duplicado' => 'An online payment for booking :reserva exceeds what was owed. Check it in Payments and, if needed, refund the difference from your payment gateway account.',
    'pago_en_cancelada' => 'An online payment was received for booking :reserva, which was already cancelled. Decide with the guest whether to reactivate it or refund the money.',
    'pago_sin_lugar' => 'An online payment was received for booking :reserva, but its nights had already been sold. Resolve it with the guest.',
    'motor_saturado' => 'Too many online bookings arrived in one hour and the form was stopped for an hour. Check in Bookings whether they are real; those not confirmed are cancelled automatically.',
    'copia_no_borrada' => 'The backup could not be deleted from the main installation; it remains in its dati/ folder. Check that folder’s permissions.',
    'registro_por_vencer' => 'A registration or licence number of this property expires in less than 30 days, or has already expired. Renew it and update the date under Tourism registrations.',
    'comprobantes_por_revisar' => 'There are payment receipts to review. See them under Money → Payment receipts.',
    'grupo_habitaciones_liberadas' => ':habitaciones rooms without a responsible person were released from group :grupo, because its deadline passed.',
    'autoridad_rechazadas' => 'The guest register rejected reports. Check them in Operations → Guest register.',
    'autoridad_faltan_datos' => 'Some guest register reports are missing details. Complete them in Operations → Guest register.',
    'autoridad_atrasadas' => 'Guest register reports are overdue: the limit is 24 hours. Handle them in Operations → Guest register.',
    'autoridad_sin_conexion' => 'The guest register could not be reached after several attempts. Check the connection in Operations → Guest register.',
];
