<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

/**
 * Crea (o reactiva) un administrador de la instalación. La contraseña se pide sin mostrarla.
 */
class CrearAdministrador extends Command
{
    protected $signature = 'alojaflow:crear-administrador {correo} {--nombre=Administrador} {--idioma=es} {--super : También super administrador}';

    protected $description = 'Crea un usuario administrador de AlojaFlow';

    public function handle(): int
    {
        $correo = (string) $this->argument('correo');
        $contrasena = (string) $this->secret('Contraseña (mínimo 12 caracteres)');
        $confirmacion = (string) $this->secret('Repite la contraseña');

        $validacion = Validator::make(
            ['correo' => $correo, 'contrasena' => $contrasena, 'contrasena_confirmation' => $confirmacion],
            ['correo' => ['required', 'email'], 'contrasena' => ['required', 'min:12', 'confirmed']],
        );
        if ($validacion->fails()) {
            foreach ($validacion->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        // Sin ningún super administrador activo nadie podría actualizar: este lo es. Quien llega aquí ya manda en el servidor.
        $sinSuper = ! User::query()->where('es_superadministrador', true)->where('activo', true)->where('email', '!=', $correo)->exists();
        User::query()->updateOrCreate(['email' => $correo], [
            'name' => (string) $this->option('nombre'),
            'password' => $contrasena,
            'idioma' => (string) $this->option('idioma'),
            'activo' => true,
            'es_administrador' => true,
        ] + ($sinSuper || $this->option('super') ? ['es_superadministrador' => true] : []));
        $this->info(__('consola.administrador_listo', ['correo' => $correo]));

        return self::SUCCESS;
    }
}
