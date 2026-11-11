<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Filament\Resources\RegistrosTuristicos\Pages;

use App\Filament\Resources\RegistrosTuristicos\RegistroTuristicoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRegistrosTuristicos extends ListRecords
{
    protected static string $resource = RegistroTuristicoResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
