<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Avisos, registro de auditoría, cierres del día y llaves de propiedad en las tablas de permisos.
 * Ver docs/especificacion/modelo-datos.md §4.1 y §4.10.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('avisos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('propiedad_id')->nullable()->constrained('propiedades')->cascadeOnDelete();
            $table->string('tipo', 40);
            $table->string('nivel', 15);
            $table->json('datos')->nullable();
            // Única mientras el aviso está abierto; al resolverlo se vacía para poder abrir otro.
            $table->string('clave_unica', 150)->nullable()->unique();
            $table->dateTime('abierto_en');
            $table->dateTime('resuelto_en')->nullable();
            $table->dateTime('notificado_en')->nullable();
            $table->index(['propiedad_id', 'resuelto_en']);
        });

        Schema::create('registro_auditoria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('propiedad_id')->nullable()->constrained('propiedades')->nullOnDelete();
            $table->string('accion', 50);
            $table->string('modelo_tipo', 100)->nullable();
            $table->unsignedBigInteger('modelo_id')->nullable();
            $table->json('cambios')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('agente', 255)->nullable();
            $table->dateTime('creado_en');
            $table->index(['propiedad_id', 'creado_en']);
            $table->index(['modelo_tipo', 'modelo_id']);
        });

        Schema::create('cierres_dia', function (Blueprint $table) {
            $table->id();
            $table->foreignId('propiedad_id')->constrained('propiedades')->cascadeOnDelete();
            $table->date('fecha');
            $table->foreignId('cerrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('cerrado_en');
            $table->text('notas')->nullable();
            $table->json('resumen')->nullable();
            $table->unique(['propiedad_id', 'fecha']);
        });

        // Los roles se asignan por propiedad (equipos de spatie/laravel-permission).
        Schema::table('roles', function (Blueprint $table) {
            $table->foreign('propiedad_id')->references('id')->on('propiedades')->cascadeOnDelete();
        });
        Schema::table('model_has_roles', function (Blueprint $table) {
            $table->foreign('propiedad_id')->references('id')->on('propiedades')->cascadeOnDelete();
        });
        Schema::table('model_has_permissions', function (Blueprint $table) {
            $table->foreign('propiedad_id')->references('id')->on('propiedades')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('model_has_permissions', function (Blueprint $table) {
            $table->dropForeign(['propiedad_id']);
        });
        Schema::table('model_has_roles', function (Blueprint $table) {
            $table->dropForeign(['propiedad_id']);
        });
        Schema::table('roles', function (Blueprint $table) {
            $table->dropForeign(['propiedad_id']);
        });
        Schema::dropIfExists('cierres_dia');
        Schema::dropIfExists('registro_auditoria');
        Schema::dropIfExists('avisos');
    }
};
