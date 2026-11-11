<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
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

        foreach (Acceso::PERMISOS as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }

        foreach (Acceso::ROLES as $nombre => $permisos) {
            $rol = Role::query()->firstOrCreate(['name' => $nombre, 'guard_name' => 'web', 'propiedad_id' => null]);
            $rol->syncPermissions($permisos);
        }

        $registro->forgetCachedPermissions();
    }
}
