<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Demo\ModoDemo;
use App\Support\ColoresEstado;
use App\Support\Formato;
use Filament\Auth\Pages\EditProfile;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Perfil del usuario: datos, idioma de la interfaz, contraseña y verificación en dos pasos.
 */
class EditarPerfil extends EditProfile
{
    public function form(Schema $schema): Schema
    {
        $preferencias = [
            Select::make('idioma')
                ->label(__('ui.perfil.idioma'))
                ->options(Formato::idiomas())
                ->required()
                ->native(false),
            Select::make('zona_horaria')
                ->label(__('ui.perfil.zona_horaria'))
                ->helperText(__('ui.perfil.zona_horaria_ayuda'))
                ->options(Formato::zonasHorarias())
                ->searchable(),
        ];
        // En la demostración pública el correo y la contraseña del usuario de prueba no se cambian.
        if (ModoDemo::activo()) {
            return $schema->components($preferencias);
        }

        return $schema->components([
            $this->getNameFormComponent(),
            $this->getEmailFormComponent(),
            ...$preferencias,
            // Desde el calendario se llega con ?colores y abierta (D-154).
            Section::make(__('herramientas.colores.titulo'))->description(__('herramientas.colores.ayuda'))->collapsed(! request()->has('colores'))->schema([
                Fieldset::make(__('ui.calendario.colorear_estado'))->columns(['default' => 1, 'md' => 2])->schema(self::selectoresColor(false)),
                Fieldset::make(__('ui.calendario.colorear_pago'))->columns(['default' => 1, 'md' => 2])->schema(self::selectoresColor(true)),
            ]),
            $this->getPasswordFormComponent(),
            $this->getPasswordConfirmationFormComponent(),
            $this->getCurrentPasswordFormComponent(),
        ]);
    }

    /**
     * @return list<ColorPicker>
     */
    private static function selectoresColor(bool $dePago): array
    {
        $claves = array_filter(array_keys(ColoresEstado::ESTADOS), fn (string $clave): bool => str_starts_with($clave, 'pago_') === $dePago);

        return array_values(array_map(fn (string $estado): ColorPicker => ColorPicker::make('colores_estado.'.$estado)->label(__('ui.calendario.'.$estado))->regex('/^#[0-9a-fA-F]{6}$/'), $claves));
    }
}
