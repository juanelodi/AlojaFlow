<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Filament\Resources\Canales\Pages;

use App\Filament\Resources\Canales\CanalResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCanales extends ListRecords
{
    protected static string $resource = CanalResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->visible(fn (): bool => CanalResource::canCreate())];
    }
}
