<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\Reservas\Pages;

use App\Filament\Resources\Reservas\AccionesReserva;
use App\Filament\Resources\Reservas\ReservaResource;
use App\Models\Reserva;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewReserva extends ViewRecord
{
    protected static string $resource = ReservaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            AccionesReserva::confirmar(),
            AccionesReserva::entrada(),
            AccionesReserva::salida(),
            EditAction::make()->visible(fn (Reserva $record): bool => ReservaResource::canEdit($record)),
            AccionesReserva::reactivar(),
            ActionGroup::make([
                AccionesReserva::confirmacionPdf(),
                AccionesReserva::deshacerRegistro(),
                AccionesReserva::cancelar(),
            ])->label(__('ui.reservas.mas_acciones'))->button()->color('gray'),
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
