<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\Guia\Pages;

use App\Filament\Pages\CodigosGuia;
use App\Filament\Resources\Guia\GuiaSeccionResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListGuiaSecciones extends ListRecords
{
    protected static string $resource = GuiaSeccionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('codigos')->label(__('portal.guia_panel.codigos'))->icon(Heroicon::OutlinedQrCode)->color('gray')->url(fn (): string => CodigosGuia::getUrl()),
            CreateAction::make(),
        ];
    }
}
