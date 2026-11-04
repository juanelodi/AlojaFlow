<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

return [
    'titulo' => 'Reports',
    'informe' => 'Report',
    'vacio' => 'No data for this period.',
    'nombres' => [
        'unidades' => 'By room',
        'canales' => 'By channel, net of commission',
        'anio_anterior' => 'Against last year',
        'futuro' => 'Bookings on the books',
        'cobros' => 'Payments by method',
        'estancia' => 'Stay and lead time',
        'dia_semana' => 'By day of the week',
        'cancelaciones' => 'Cancellations and no-shows',
        'limpieza' => 'Housekeeping and maintenance',
        'huespedes' => 'Guests',
        'anticipos' => 'Pending deposits',
        'gastos' => 'Expenses and income',
    ],
    'columnas' => [
        'unidad' => 'Room', 'noches' => 'Nights', 'ocupacion' => 'Occupancy', 'ingresos' => 'Revenue', 'adr' => 'Average rate',
        'canal' => 'Channel', 'reservas' => 'Bookings', 'comision' => 'Commission', 'neto' => 'Net',
        'propiedad' => 'Property', 'noches_anterior' => 'Nights last year', 'ingresos_anterior' => 'Revenue last year', 'variacion' => 'Change',
        'mes' => 'Month', 'metodo' => 'Method', 'pagos' => 'Payments', 'importe' => 'Amount',
        'estancia' => 'Nights per stay', 'personas' => 'People per booking', 'anticipacion' => 'Lead time (days)',
        'dia' => 'Day', 'canceladas' => 'Cancelled', 'tasa' => 'Rate', 'valor_perdido' => 'Cancelled value', 'no_show' => 'No-shows',
        'tipo' => 'Type', 'total' => 'Total', 'hechas' => 'Done', 'cumplimiento' => 'Completion',
        'pais' => 'Country', 'huespedes' => 'Guests', 'repiten' => 'Returning',
        'reserva' => 'Booking', 'llegada' => 'Arrival', 'anticipo' => 'Deposit', 'pagado' => 'Paid', 'falta' => 'Missing',
        'concepto' => 'Item', 'movimientos' => 'Entries',
    ],
];
