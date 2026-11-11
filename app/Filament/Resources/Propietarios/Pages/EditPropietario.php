<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Filament\Resources\Propietarios\Pages;

use App\Comunicacion\VariablesMensaje;
use App\Filament\Resources\Base\EditaEnPropiedad;
use App\Filament\Resources\Propietarios\PropietarioResource;
use App\Models\Propietario;
use App\Models\RegistroAuditoria;
use App\Propietarios\AccesoPropietario;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditPropietario extends EditRecord
{
    use EditaEnPropiedad;

    protected static string $resource = PropietarioResource::class;

    /**
     * @return array<\Filament\Actions\Action|ActionGroup>
     */
    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                Action::make('darAcceso')
                    ->label(fn (Propietario $record): string => __($record->clave_hash === null ? 'propietarios.acceso.dar' : 'propietarios.acceso.clave_nueva'))
                    ->icon(Heroicon::OutlinedKey)
                    ->requiresConfirmation()
                    ->modalDescription(fn (Propietario $record): string => __($record->clave_hash === null ? 'propietarios.acceso.dar_ayuda' : 'propietarios.acceso.clave_nueva_ayuda'))
                    ->action(function (Propietario $record): void {
                        app(AccesoPropietario::class)->generarClave($record);
                        $this->replaceMountedAction('verAcceso');
                    }),
                Action::make('verAcceso')
                    ->label(__('propietarios.acceso.ver'))
                    ->icon(Heroicon::OutlinedEye)
                    ->visible(fn (Propietario $record): bool => $record->clave_hash !== null)
                    ->modalHeading(__('propietarios.acceso.titulo'))
                    ->modalContent(fn (Propietario $record) => view('filament.modales.acceso-propietario', self::datosAcceso($record)))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('ui.comun.cerrar')),
                Action::make('apagarAcceso')
                    ->label(fn (Propietario $record): string => __($record->acceso_activo ? 'propietarios.acceso.desactivar' : 'propietarios.acceso.activar'))
                    ->icon(Heroicon::OutlinedNoSymbol)
                    ->color('gray')
                    ->requiresConfirmation()
                    ->visible(fn (Propietario $record): bool => $record->clave_hash !== null)
                    ->action(function (Propietario $record): void {
                        $acceso = app(AccesoPropietario::class);
                        $record->acceso_activo ? $acceso->desactivar($record) : $acceso->activar($record);
                    }),
            ])->label(__('propietarios.acceso.menu'))->icon(Heroicon::OutlinedKey)->color('gray')->button(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function datosAcceso(Propietario $propietario): array
    {
        $idioma = $propietario->idioma ?? $propietario->propiedad->idioma;
        $url = (string) app(AccesoPropietario::class)->url($propietario, $idioma);
        $clave = $propietario->clave_cifrada;
        if ($clave !== null) {
            RegistroAuditoria::anotar('propietario.ver_clave', $propietario, [], $propietario->propiedad_id);
        }

        return [
            'propietario' => $propietario,
            'url' => $url,
            'clave' => $clave,
            'whatsapp' => $clave === null || blank($propietario->telefono) ? null : VariablesMensaje::enlaceWhatsapp($propietario->telefono, __('propietarios.acceso.whatsapp', ['propiedad' => $propietario->propiedad->nombre, 'enlace' => $url, 'clave' => $clave], $idioma), $propietario->propiedad->pais),
        ];
    }
}
