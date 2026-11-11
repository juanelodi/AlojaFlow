<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Filament\Resources\Reservas\Pages;

use App\Enums\EntidadCampo;
use App\Filament\Campos\CamposPropiosPanel;
use App\Filament\Resources\Reservas\AccionesReserva;
use App\Filament\Resources\Reservas\ReservaResource;
use App\Models\Reserva;
use App\Seguridad\Acceso;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Str;

class ViewReserva extends ViewRecord
{
    protected static string $resource = ReservaResource::class;

    public function getTitle(): string
    {
        return Str::ucfirst(__('ui.reservas.modelo')).' '.$this->getRecord()->getAttribute('numero');
    }

    /**
     * El huésped, como en su ficha: es lo primero que se busca. Un bloqueo no tiene huésped.
     */
    public function getHeading(): string|Htmlable
    {
        $reserva = $this->getRecord();

        return $reserva instanceof Reserva && $reserva->titular !== null ? $reserva->titular->nombreCompleto() : $this->getTitle();
    }

    public function getSubheading(): string|Htmlable|null
    {
        $reserva = $this->getRecord();

        return $reserva instanceof Reserva && $reserva->titular !== null ? $this->getTitle() : null;
    }

    public function getBreadcrumb(): string
    {
        return $this->getTitle();
    }

    protected function getHeaderActions(): array
    {
        return [
            // Primero lo que toca hacer ahora: en el teléfono, estos botones van antes que todo lo demás.
            AccionesReserva::confirmar(),
            AccionesReserva::entrada(),
            AccionesReserva::salida(),
            AccionesReserva::mandarMensaje(),
            EditAction::make()->visible(fn (Reserva $record): bool => ReservaResource::canEdit($record)),
            AccionesReserva::reactivar(),
            ActionGroup::make([
                AccionesReserva::limpiezaEstancia(),
                AccionesReserva::enlaceLlegada(),
                AccionesReserva::codigoAcceso(),
                AccionesReserva::enlacePago(),
                AccionesReserva::noShow(),
                AccionesReserva::confirmacionPdf(),
                AccionesReserva::documentoPropio(),
                CamposPropiosPanel::accion(EntidadCampo::Reserva, Acceso::RESERVAS),
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
