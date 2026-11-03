<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

return [
    'ModoPropiedad' => ['subordinada' => 'Subordinada a HotelDruid', 'nativa' => 'Nativa'],
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
    'OrigenFila' => ['hoteldruid' => 'HotelDruid', 'alojaflow' => 'AlojaFlow'],
];
