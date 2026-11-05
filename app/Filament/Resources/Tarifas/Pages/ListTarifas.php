<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\Tarifas\Pages;

use App\Filament\Pages\ImportarPrecios;
use App\Filament\Resources\Tarifas\TarifaResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListTarifas extends ListRecords
{
    protected static string $resource = TarifaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('importarPrecios')->label(__('operacion.precios.titulo'))->icon(Heroicon::OutlinedArrowUpTray)->color('gray')
                ->url(fn (): string => ImportarPrecios::getUrl()),
            CreateAction::make(),
        ];
    }
}
