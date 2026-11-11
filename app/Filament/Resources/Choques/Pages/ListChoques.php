<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Filament\Resources\Choques\Pages;

use App\Filament\Resources\Choques\ChoqueResource;
use Filament\Resources\Pages\ListRecords;

class ListChoques extends ListRecords
{
    protected static string $resource = ChoqueResource::class;
}
