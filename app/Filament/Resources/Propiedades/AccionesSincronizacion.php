<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Filament\Resources\Propiedades;

use App\Enums\EstadoEjecucion;
use App\Enums\EstadoPrincipal;
use App\Enums\OrigenEjecucion;
use App\Models\Propiedad;
use App\Models\RegistroAuditoria;
use App\Modulos\Modulo;
use App\Modulos\Modulos;
use App\Sincronizacion\HotelDruid\ErrorSincronizacion;
use App\Sincronizacion\Subordinadores;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Probar la conexión, sincronizar ahora y reanudar una principal pausada.
 */
final class AccionesSincronizacion
{
    public static function probar(): Action
    {
        return Action::make('probarConexion')
            ->label(__('ui.propiedades.probar'))
            ->icon(Heroicon::OutlinedSignal)
            ->color('gray')
            ->visible(fn (Propiedad $record): bool => $record->principal !== null && Modulos::activo(Modulo::Subordinacion))
            ->action(function (Propiedad $record): void {
                $principal = $record->principal;
                if ($principal === null) {
                    return;
                }
                try {
                    Subordinadores::de($principal)->probar($principal);
                    Notification::make()->success()->title(__('ui.propiedades.probar_ok'))->send();
                } catch (ErrorSincronizacion $e) {
                    Notification::make()->danger()->title($e->codigo->value.' · '.$e->codigo->etiqueta())->body(e($e->getMessage()))->persistent()->send();
                }
            });
    }

    public static function sincronizar(): Action
    {
        return Action::make('sincronizarAhora')
            ->label(__('ui.propiedades.sincronizar'))
            ->icon(Heroicon::OutlinedArrowPath)
            ->visible(fn (Propiedad $record): bool => $record->principal !== null && Modulos::activo(Modulo::Subordinacion))
            ->action(function (Propiedad $record): void {
                $principal = $record->principal;
                if ($principal === null) {
                    return;
                }
                if (function_exists('set_time_limit')) {
                    @set_time_limit(600);
                }
                $ejecucion = Subordinadores::de($principal)->sincronizar($principal, OrigenEjecucion::Manual);
                RegistroAuditoria::anotar('sincronizacion.manual', $principal, ['ejecucion' => $ejecucion->id], $record->id);

                if ($ejecucion->estado === EstadoEjecucion::Correcta) {
                    Notification::make()->success()->title(__('ui.propiedades.sincronizado', [
                        'reservas' => $record->reservas()->count(), 'eventos' => $ejecucion->eventos,
                    ]))->send();
                } else {
                    Notification::make()->danger()->title(($ejecucion->codigo_error ?? '').' · '.$ejecucion->estado->etiqueta())
                        ->body(e((string) $ejecucion->detalle))->persistent()->send();
                }
            });
    }

    public static function reanudar(): Action
    {
        return Action::make('reanudar')
            ->label(__('ui.propiedades.reanudar'))
            ->icon(Heroicon::OutlinedPlay)
            ->color('warning')
            ->visible(fn (Propiedad $record): bool => $record->principal?->estado === EstadoPrincipal::Pausada)
            ->requiresConfirmation()
            ->action(function (Propiedad $record): void {
                $record->principal?->reanudar();
                RegistroAuditoria::anotar('sincronizacion.reanudar', $record->principal, [], $record->id);
                Notification::make()->success()->title(__('ui.propiedades.reanudada'))->send();
            });
    }
}
