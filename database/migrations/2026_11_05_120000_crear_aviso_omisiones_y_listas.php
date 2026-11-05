<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Clientes omitidos, días sueltos de limpieza, listas de comprobación y mantenimiento recurrente (D-074). Son datos propios: valen
 * igual en independientes y subordinadas, y la sincronización no los toca.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('omisiones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('propiedad_id')->constrained('propiedades')->cascadeOnDelete();
            $table->foreignId('huesped_id')->nullable()->constrained('huespedes')->cascadeOnDelete();
            // O todos los de un tipo: un campo de la ficha con un valor, como los colores del calendario.
            $table->string('campo', 100)->nullable();
            $table->string('valor', 255)->nullable();
            $table->boolean('en_aviso')->default(true);
            $table->boolean('en_informes')->default(false);
            $table->boolean('en_mensajes')->default(false);
            $table->string('motivo', 255)->nullable();
            $table->foreignId('creada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('creado_en')->nullable();
            $table->dateTime('actualizado_en')->nullable();
            $table->unique(['propiedad_id', 'huesped_id']);
        });

        Schema::table('reglas_limpieza', function (Blueprint $table): void {
            $table->json('fechas')->nullable()->after('dias_semana');
        });

        Schema::create('listas_comprobacion', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('propiedad_id')->constrained('propiedades')->cascadeOnDelete();
            $table->string('tipo_tarea', 30);
            $table->string('nombre', 100);
            $table->json('puntos');
            $table->boolean('activa')->default(true);
            $table->dateTime('creado_en')->nullable();
            $table->dateTime('actualizado_en')->nullable();
            $table->unique(['propiedad_id', 'tipo_tarea']);
        });

        Schema::table('tareas', function (Blueprint $table): void {
            $table->json('puntos')->nullable()->after('descripcion');
        });

        Schema::create('mantenimientos_recurrentes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('propiedad_id')->constrained('propiedades')->cascadeOnDelete();
            $table->foreignId('unidad_id')->nullable()->constrained('unidades')->cascadeOnDelete();
            $table->string('titulo', 150);
            $table->text('descripcion')->nullable();
            $table->unsignedSmallInteger('cada_dias');
            $table->date('proxima_fecha');
            $table->boolean('activo')->default(true);
            $table->dateTime('creado_en')->nullable();
            $table->dateTime('actualizado_en')->nullable();
            $table->index(['activo', 'proxima_fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mantenimientos_recurrentes');
        Schema::table('tareas', function (Blueprint $table): void {
            $table->dropColumn('puntos');
        });
        Schema::dropIfExists('listas_comprobacion');
        Schema::table('reglas_limpieza', function (Blueprint $table): void {
            $table->dropColumn('fechas');
        });
        Schema::dropIfExists('omisiones');
    }
};
