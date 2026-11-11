<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Filament\Resources\Guia\Pages;

use App\Filament\Resources\Base\CreaEnPropiedad;
use App\Filament\Resources\Guia\GuiaSeccionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateGuiaSeccion extends CreateRecord
{
    use CreaEnPropiedad;

    protected static string $resource = GuiaSeccionResource::class;

    protected static bool $canCreateAnother = false;
}
