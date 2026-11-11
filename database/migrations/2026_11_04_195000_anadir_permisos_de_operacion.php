<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Los permisos nuevos, para las instalaciones que se actualizan: ahí los roles ya existen y el
 * seeder no vuelve a correr. En una instalación nueva los crea el seeder.
 */
return new class () extends Migration {
    private const PERMISOS = [
        'limpieza.gestionar' => ['gerente', 'recepcion', 'limpieza', 'mantenimiento'],
        'mensajes.gestionar' => ['gerente', 'recepcion'],
        'ajustes.gestionar' => ['gerente'],
        'huespedes.gestionar' => ['gerente', 'recepcion'],
        'facturacion.gestionar' => ['gerente', 'contador'],
    ];

    public function up(): void
    {
        foreach (self::PERMISOS as $permiso => $roles) {
            $roleIds = DB::table('roles')->whereIn('name', $roles)->pluck('id');
            if ($roleIds->isEmpty()) {
                continue;
            }
            DB::table('permissions')->insertOrIgnore(['name' => $permiso, 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]);
            $permisoId = DB::table('permissions')->where('name', $permiso)->where('guard_name', 'web')->value('id');
            foreach ($roleIds as $roleId) {
                DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $permisoId, 'role_id' => $roleId]);
            }
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $permisos = DB::table('permissions')->whereIn('name', array_keys(self::PERMISOS))->pluck('id');
        DB::table('role_has_permissions')->whereIn('permission_id', $permisos)->delete();
        DB::table('permissions')->whereIn('id', $permisos)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
