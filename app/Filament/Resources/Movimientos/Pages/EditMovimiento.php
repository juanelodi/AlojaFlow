<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\Movimientos\Pages;

use App\Filament\Resources\Base\EditaEnPropiedad;
use App\Filament\Resources\Movimientos\MovimientoResource;
use Filament\Resources\Pages\EditRecord;

class EditMovimiento extends EditRecord
{
    use EditaEnPropiedad;

    protected static string $resource = MovimientoResource::class;
}
