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
    'copia_no_borrada' => 'The backup could not be deleted from the main installation; it remains in its dati/ folder. Check that folder’s permissions.',
];
