<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\FuentesIcal\Pages;

use App\Filament\Resources\Base\CreaEnPropiedad;
use App\Filament\Resources\FuentesIcal\FuenteIcalResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFuenteIcal extends CreateRecord
{
    use CreaEnPropiedad;

    protected static string $resource = FuenteIcalResource::class;

    protected static bool $canCreateAnother = false;
}
