<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
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
