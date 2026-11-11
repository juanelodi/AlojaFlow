<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Datos iniciales de una instalación: solo roles y permisos. Los usuarios se crean con
 * `php artisan alojaflow:crear-administrador` y desde la pantalla de usuarios.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolesYPermisosSeeder::class);
    }
}
