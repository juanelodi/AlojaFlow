<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Filament\Resources\CamposPropios\Pages;

use App\Filament\Resources\CamposPropios\CampoPropioResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCamposPropios extends ListRecords
{
    protected static string $resource = CampoPropioResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->visible(fn (): bool => CampoPropioResource::canCreate())];
    }
}
