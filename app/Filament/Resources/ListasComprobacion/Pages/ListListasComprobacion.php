<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\ListasComprobacion\Pages;

use App\Filament\Resources\ListasComprobacion\ListaComprobacionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListListasComprobacion extends ListRecords
{
    protected static string $resource = ListaComprobacionResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->visible(fn (): bool => ListaComprobacionResource::canCreate())];
    }
}
