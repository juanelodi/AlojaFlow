<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Filament\Resources\Automatizaciones\Pages;

use App\Filament\Resources\Automatizaciones\AutomatizacionResource;
use App\Filament\Resources\Base\CreaEnPropiedad;
use Filament\Resources\Pages\CreateRecord;

class CreateAutomatizacion extends CreateRecord
{
    use CreaEnPropiedad;

    protected static string $resource = AutomatizacionResource::class;

    protected static bool $canCreateAnother = false;
}
