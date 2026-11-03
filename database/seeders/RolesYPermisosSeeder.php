<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace Database\Seeders;

use App\Seguridad\Acceso;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Roles globales (sin propiedad) que luego se asignan por propiedad.
 */
class RolesYPermisosSeeder extends Seeder
{
    public function run(): void
    {
        $registro = app(PermissionRegistrar::class);
        $registro->forgetCachedPermissions();
        $registro->setPermissionsTeamId(null);

        foreach ([Acceso::OPERACION, Acceso::DATOS, Acceso::INDICADORES, Acceso::SINCRONIZACION] as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }

        foreach (Acceso::ROLES as $nombre => $permisos) {
            $rol = Role::query()->firstOrCreate(['name' => $nombre, 'guard_name' => 'web', 'propiedad_id' => null]);
            $rol->syncPermissions($permisos);
        }

        $registro->forgetCachedPermissions();
    }
}
