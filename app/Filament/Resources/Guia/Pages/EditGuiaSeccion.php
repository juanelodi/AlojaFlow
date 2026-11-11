<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Filament\Resources\Guia\Pages;

use App\Filament\Resources\Guia\GuiaSeccionResource;
use Filament\Resources\Pages\EditRecord;

class EditGuiaSeccion extends EditRecord
{
    protected static string $resource = GuiaSeccionResource::class;
}
