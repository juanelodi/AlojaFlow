<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\ColoresTipoCliente\Pages;

use App\Filament\Resources\ColoresTipoCliente\ColorTipoClienteResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListColoresTipoCliente extends ListRecords
{
    protected static string $resource = ColorTipoClienteResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->visible(fn (): bool => ColorTipoClienteResource::canCreate())];
    }
}
