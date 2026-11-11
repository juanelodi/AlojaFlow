<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Filament\Resources\Envios\Pages;

use App\Filament\Resources\Envios\EnvioResource;
use Filament\Resources\Pages\ListRecords;

class ListEnvios extends ListRecords
{
    protected static string $resource = EnvioResource::class;
}
