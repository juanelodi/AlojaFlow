<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\Politicas\Pages;

use App\Filament\Resources\Base\CreaEnPropiedad;
use App\Filament\Resources\Politicas\PoliticaResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePolitica extends CreateRecord
{
    use CreaEnPropiedad;

    protected static string $resource = PoliticaResource::class;

    protected static bool $canCreateAnother = false;
}
