<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Campos;

use App\Documentos\CamposPropios;
use App\Enums\EntidadCampo;
use App\Enums\TipoCampoPropio;
use App\Models\CampoPropio;
use App\Models\Huesped;
use App\Models\Reserva;
use App\Seguridad\Acceso;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;

/**
 * Los campos propios en las pantallas (D-122): en el formulario de quien se puede editar, y en una ventana para lo que viene de la principal.
 */
final class CamposPropiosPanel
{
    /**
     * @return list<Field>
     */
    public static function componentes(int $propiedadId, EntidadCampo $entidad): array
    {
        return CamposPropios::de($propiedadId, $entidad)
            ->map(fn (CampoPropio $campo): Field => $campo->tipo === TipoCampoPropio::SiNo
                ? Toggle::make('campos_propios.'.$campo->id)->label($campo->nombre)
                : TextInput::make('campos_propios.'.$campo->id)->label($campo->nombre)->maxLength(1000))
            ->values()->all();
    }

    /**
     * @param  (Closure(Get): bool)|null  $ademas  otra condición para mostrarla
     */
    public static function seccion(EntidadCampo $entidad, ?Closure $ademas = null): Section
    {
        return Section::make(__('documentos_propios.campos.seccion'))->columnSpanFull()->columns(['default' => 1, 'md' => 2])
            ->visible(fn (Get $get): bool => ($ademas === null || $ademas($get)) && CamposPropios::hay((int) $get('propiedad_id'), $entidad))
            ->schema(fn (Get $get): array => self::componentes((int) $get('propiedad_id'), $entidad));
    }

    public static function accion(EntidadCampo $entidad, string $permiso): Action
    {
        return Action::make('camposPropios')->label(__('documentos_propios.campos.accion'))->icon(Heroicon::OutlinedTag)->color('gray')
            ->visible(fn (Reserva|Huesped $record): bool => Acceso::puede(Acceso::usuarioActual(), $permiso, $record->propiedad_id) && CamposPropios::hay($record->propiedad_id, $entidad))
            ->fillForm(fn (Reserva|Huesped $record): array => ['campos_propios' => CamposPropios::valoresParaFormulario($record)])
            ->schema(fn (Reserva|Huesped $record): array => self::componentes($record->propiedad_id, $entidad))
            ->action(function (Reserva|Huesped $record, array $data): void {
                CamposPropios::guardar($record, (array) ($data['campos_propios'] ?? []));
                Notification::make()->success()->title(__('documentos_propios.campos.guardados'))->send();
            });
    }
}
