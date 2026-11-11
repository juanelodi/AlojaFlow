<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Filament\Resources\RegistrosTuristicos\Pages;

use App\Filament\Resources\Base\EditaEnPropiedad;
use App\Filament\Resources\RegistrosTuristicos\RegistroTuristicoResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRegistroTuristico extends EditRecord
{
    use EditaEnPropiedad;

    protected static string $resource = RegistroTuristicoResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
