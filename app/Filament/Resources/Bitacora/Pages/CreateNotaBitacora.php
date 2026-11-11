<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Filament\Resources\Bitacora\Pages;

use App\Filament\Resources\Base\CreaEnPropiedad;
use App\Filament\Resources\Bitacora\NotaBitacoraResource;
use Filament\Resources\Pages\CreateRecord;

class CreateNotaBitacora extends CreateRecord
{
    use CreaEnPropiedad;

    protected static string $resource = NotaBitacoraResource::class;

    protected static bool $canCreateAnother = false;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function completarDatos(array $data): array
    {
        $data['user_id'] = auth()->id();

        return $data;
    }
}
