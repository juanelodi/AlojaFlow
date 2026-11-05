<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
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
