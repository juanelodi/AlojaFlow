<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Demo\ModoDemo;
use App\Support\ColoresEstado;
use App\Support\Formato;
use Filament\Auth\Pages\EditProfile;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
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
            Section::make(__('herramientas.colores.titulo'))->description(__('herramientas.colores.ayuda'))->collapsed()->columns(['default' => 1, 'md' => 2])->schema(
                array_map(fn (string $estado): ColorPicker => ColorPicker::make('colores_estado.'.$estado)->label(__('ui.calendario.'.$estado))->regex('/^#[0-9a-fA-F]{6}$/'), array_keys(ColoresEstado::ESTADOS)),
            ),
            $this->getPasswordFormComponent(),
            $this->getPasswordConfirmationFormComponent(),
            $this->getCurrentPasswordFormComponent(),
        ]);
    }
}
