<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\Grupos\Pages;

use App\Enums\MetodoPago;
use App\Filament\Resources\Grupos\GrupoResource;
use App\Models\Grupo;
use App\Reservas\GestorGrupos;
use App\Reservas\ReservaRechazada;
use App\Support\Dinero;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewGrupo extends ViewRecord
{
    protected static string $resource = GrupoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('cobrarGrupo')
                ->label(__('ui.grupos.cobrar'))
                ->icon(Heroicon::OutlinedBanknotes)
                ->modalDescription(__('ui.grupos.cobrar_ayuda'))
                ->fillForm(function (Grupo $record): array {
                    $saldo = app(GestorGrupos::class)->saldo($record);

                    return [
                        'importe' => $saldo['pendiente'] > 0 ? Dinero::aDecimal($saldo['pendiente'], $saldo['moneda']) : null,
                        'fecha' => $record->propiedad->hoy()->format('Y-m-d'),
                        'metodo' => MetodoPago::Efectivo->value,
                    ];
                })
                ->schema([
                    TextInput::make('importe')->label(__('ui.pagos.importe'))->required()->numeric()->minValue(0.01)->step('any'),
                    DatePicker::make('fecha')->label(__('ui.pagos.fecha'))->required(),
                    Select::make('metodo')->label(__('ui.pagos.metodo'))->options(MetodoPago::opciones())->required()->native(false),
                    TextInput::make('referencia')->label(__('ui.pagos.referencia'))->maxLength(100),
                ])
                ->action(function (Grupo $record, array $data): void {
                    try {
                        app(GestorGrupos::class)->cobrar(
                            $record,
                            Dinero::aUnidadMenor((string) $data['importe'], $record->propiedad->moneda),
                            MetodoPago::from((string) $data['metodo']),
                            CarbonImmutable::parse((string) $data['fecha']),
                            $data['referencia'] ?? null,
                        );
                        Notification::make()->success()->title(__('ui.pagos.registrado'))->send();
                    } catch (ReservaRechazada $e) {
                        Notification::make()->danger()->title($e->getMessage())->send();
                    }
                }),
        ];
    }
}
