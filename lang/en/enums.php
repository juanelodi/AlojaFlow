<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

return [
    'ModoPropiedad' => ['subordinada' => 'Subordinate to HotelDruid', 'nativa' => 'Native'],
    'PaquetePais' => ['mexico' => 'Mexico', 'espana' => 'Spain', 'estados_unidos' => 'United States', 'generico' => 'Generic'],
    'EstadoPrincipal' => ['activa' => 'Active', 'pausada' => 'Paused'],
    'MetodoPeticion' => ['post' => 'POST (recommended)', 'get' => 'GET (compatibility mode)'],
    'EstadoEjecucion' => ['en_curso' => 'Running', 'correcta' => 'Successful', 'fallida' => 'Failed', 'omitida' => 'Skipped'],
    'OrigenEjecucion' => ['programada' => 'Scheduled', 'manual' => 'Manual'],
    'TipoReserva' => ['reserva' => 'Booking', 'bloqueo' => 'Block'],
    'EstadoReserva' => ['sin_confirmar' => 'Unconfirmed', 'confirmada' => 'Confirmed', 'cancelada' => 'Cancelled'],
    'TipoEventoReserva' => ['creada' => 'New', 'modificada' => 'Modified', 'cancelada' => 'Cancelled', 'eliminada' => 'Deleted in the main installation', 'reactivada' => 'Reactivated'],
    'NivelAviso' => ['informacion' => 'Information', 'advertencia' => 'Warning', 'error' => 'Error'],
    'TipoContacto' => ['telefono' => 'Phone', 'correo' => 'Email', 'fax' => 'Fax'],
    'OrigenFila' => ['hoteldruid' => 'HotelDruid', 'alojaflow' => 'AlojaFlow'],
];
