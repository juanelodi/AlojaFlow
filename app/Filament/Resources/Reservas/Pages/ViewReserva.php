<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\Reservas\Pages;

use App\Filament\Resources\Reservas\ReservaResource;
use App\Models\Reserva;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewReserva extends ViewRecord
{
    protected static string $resource = ReservaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('abrirEnPrincipal')
                ->label(__('ui.comun.abrir_principal'))
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->url(fn (Reserva $record): ?string => $record->urlEnPrincipal())
                ->openUrlInNewTab()
                ->visible(fn (Reserva $record): bool => $record->urlEnPrincipal() !== null),
        ];
    }
}
