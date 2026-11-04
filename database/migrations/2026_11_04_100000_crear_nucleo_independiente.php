<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * Propiedades independientes (D-035): tarifas con temporadas, pagos, el consecutivo de reservas y
 * la marca de las propiedades de demostración. Las actualizaciones no ejecutan seeders, así que los
 * permisos nuevos se dan aquí a los roles que ya existen. Ver docs/especificacion/modelo-datos.md §5.
 */
return new class () extends Migration {
    private const PERMISOS = [
        'reservas.gestionar' => ['gerente', 'recepcion'],
        'catalogo.gestionar' => ['gerente'],
    ];

    public function up(): void
    {
        Schema::table('propiedades', function (Blueprint $table) {
            $table->boolean('es_demo')->default(false)->after('activa');
            $table->unsignedInteger('consecutivo_reserva')->default(0)->after('es_demo');
        });

        Schema::create('tarifas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('propiedad_id')->constrained('propiedades')->cascadeOnDelete();
            $table->string('nombre', 100);
            $table->bigInteger('precio_noche');
            $table->boolean('activa')->default(true);
            $table->dateTime('creado_en')->nullable();
            $table->dateTime('actualizado_en')->nullable();
            $table->unique(['propiedad_id', 'nombre']);
        });
        DB::statement('ALTER TABLE tarifas ADD CONSTRAINT tarifas_precio_valido CHECK (precio_noche >= 0)');

        // «hasta» es la última noche de la temporada, inclusive.
        Schema::create('tarifas_temporadas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tarifa_id')->constrained('tarifas')->cascadeOnDelete();
            $table->string('nombre', 100);
            $table->date('desde');
            $table->date('hasta');
            $table->bigInteger('precio_noche');
            $table->dateTime('creado_en')->nullable();
            $table->dateTime('actualizado_en')->nullable();
            $table->index(['tarifa_id', 'desde']);
        });
        DB::statement('ALTER TABLE tarifas_temporadas ADD CONSTRAINT tarifas_temporadas_fechas_validas CHECK (hasta >= desde)');
        DB::statement('ALTER TABLE tarifas_temporadas ADD CONSTRAINT tarifas_temporadas_precio_valido CHECK (precio_noche >= 0)');

        Schema::table('unidades', function (Blueprint $table) {
            $table->foreignId('tarifa_id')->nullable()->after('propiedad_id')->constrained('tarifas')->nullOnDelete();
        });

        Schema::table('reservas', function (Blueprint $table) {
            // Bloqueo optimista al editar (modelo-datos.md §7).
            $table->unsignedInteger('version')->default(1)->after('comentario');
        });

        // Importe negativo = devolución. reservas.pagado es la suma de los pagos.
        Schema::create('pagos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('propiedad_id')->constrained('propiedades')->cascadeOnDelete();
            $table->foreignId('reserva_id')->constrained('reservas')->cascadeOnDelete();
            $table->date('fecha');
            $table->string('metodo', 20);
            $table->bigInteger('importe');
            $table->char('moneda', 3);
            $table->string('referencia', 100)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('creado_en')->nullable();
            $table->dateTime('actualizado_en')->nullable();
            $table->index(['propiedad_id', 'fecha']);
        });

        $this->darPermisos();
    }

    public function down(): void
    {
        $permisos = DB::table('permissions')->whereIn('name', array_keys(self::PERMISOS))->pluck('id');
        DB::table('role_has_permissions')->whereIn('permission_id', $permisos)->delete();
        DB::table('permissions')->whereIn('id', $permisos)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Schema::dropIfExists('pagos');
        Schema::table('reservas', function (Blueprint $table) {
            $table->dropColumn('version');
        });
        Schema::table('unidades', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tarifa_id');
        });
        Schema::dropIfExists('tarifas_temporadas');
        Schema::dropIfExists('tarifas');
        Schema::table('propiedades', function (Blueprint $table) {
            $table->dropColumn(['es_demo', 'consecutivo_reserva']);
        });
    }

    /**
     * En una instalación nueva todavía no hay roles: los crea el seeder, con los mismos permisos.
     */
    private function darPermisos(): void
    {
        foreach (self::PERMISOS as $permiso => $roles) {
            $roleIds = DB::table('roles')->whereIn('name', $roles)->pluck('id');
            if ($roleIds->isEmpty()) {
                continue;
            }
            DB::table('permissions')->insertOrIgnore([
                'name' => $permiso, 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now(),
            ]);
            $permisoId = DB::table('permissions')->where('name', $permiso)->where('guard_name', 'web')->value('id');
            foreach ($roleIds as $roleId) {
                DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $permisoId, 'role_id' => $roleId]);
            }
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
