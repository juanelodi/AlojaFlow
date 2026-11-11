<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El super administrador (D-070) y los módulos de esta versión (D-069). En una instalación que ya
 * tiene usuarios, lo que había sigue prendido y no se hacen las preguntas de primera entrada.
 */
return new class () extends Migration {
    private const MODULOS = ['varias_propiedades', 'subordinacion', 'motor', 'pagos', 'ical', 'cupones', 'grupos', 'limpieza', 'plano', 'llegada', 'mensajes', 'caja', 'facturacion', 'informes', 'integraciones'];

    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('es_superadministrador')->default(false)->after('es_administrador');
        });

        $primero = DB::table('users')->where('es_administrador', true)->where('activo', true)->orderBy('id')->value('id');
        if ($primero !== null) {
            DB::table('users')->where('id', $primero)->update(['es_superadministrador' => true]);
        }

        if (DB::table('users')->exists()) {
            $ahora = now('UTC');
            foreach ([
                'modulos.activos' => self::MODULOS,
                'modulos.conocidos' => self::MODULOS,
                'modulos.preguntas' => ['como' => 'actualizacion', 'en' => $ahora->toIso8601String()],
            ] as $clave => $valor) {
                DB::table('ajustes')->updateOrInsert(['clave' => $clave], ['valor' => json_encode($valor), 'creado_en' => $ahora, 'actualizado_en' => $ahora]);
            }
        }
    }

    public function down(): void
    {
        DB::table('ajustes')->whereIn('clave', ['modulos.activos', 'modulos.conocidos', 'modulos.preguntas'])->delete();
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('es_superadministrador');
        });
    }
};
