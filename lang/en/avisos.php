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
    'pago_sin_lugar' => 'An online payment was received for booking :reserva, but its nights had already been sold. Resolve it with the guest.',
    'copia_no_borrada' => 'The backup could not be deleted from the main installation; it remains in its dati/ folder. Check that folder’s permissions.',
];
