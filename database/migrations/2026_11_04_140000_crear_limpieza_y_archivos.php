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
 * Limpieza y mantenimiento (modelo-datos.md §4.7) y archivos subidos (§4.12). Son datos propios
 * de AlojaFlow: valen también para las propiedades subordinadas.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('archivos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('propiedad_id')->nullable()->constrained('propiedades')->cascadeOnDelete();
            $table->string('ruta', 255);
            $table->string('nombre_original', 255);
            $table->string('tipo_mime', 100);
            $table->unsignedBigInteger('bytes');
            $table->char('sha256', 64);
            $table->boolean('cifrado')->default(false);
            $table->string('clase', 30);
            $table->nullableMorphs('objeto');
            $table->foreignId('subido_por')->nullable()->constrained('users')->nullOnDelete();
            $table->date('conservar_hasta')->nullable()->index();
            $table->dateTime('creado_en')->nullable();
            $table->dateTime('actualizado_en')->nullable();
        });

        Schema::create('estados_unidad', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unidad_id')->unique()->constrained('unidades')->cascadeOnDelete();
            $table->string('limpieza', 20)->default('limpia');
            $table->boolean('fuera_de_servicio')->default(false);
            $table->text('nota')->nullable();
            $table->foreignId('actualizado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('creado_en')->nullable();
            $table->dateTime('actualizado_en')->nullable();
        });

        Schema::create('cambios_estado_unidad', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unidad_id')->constrained('unidades')->cascadeOnDelete();
            $table->string('campo', 30);
            $table->string('antes', 30)->nullable();
            $table->string('despues', 30)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('creado_en')->nullable();
            $table->dateTime('actualizado_en')->nullable();
            $table->index(['unidad_id', 'creado_en']);
        });

        Schema::create('tareas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('propiedad_id')->constrained('propiedades')->cascadeOnDelete();
            $table->foreignId('unidad_id')->nullable()->constrained('unidades')->cascadeOnDelete();
            $table->foreignId('reserva_id')->nullable()->constrained('reservas')->nullOnDelete();
            $table->string('tipo', 30);
            $table->string('titulo', 150);
            $table->text('descripcion')->nullable();
            $table->date('fecha');
            $table->smallInteger('prioridad')->default(0);
            $table->string('estado', 20)->default('pendiente');
            $table->foreignId('asignada_a')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('creada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('hecha_por')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('hecha_en')->nullable();
            $table->string('clave_generacion', 150)->nullable()->unique();
            $table->dateTime('creado_en')->nullable();
            $table->dateTime('actualizado_en')->nullable();
            $table->index(['propiedad_id', 'fecha', 'estado']);
        });

        Schema::create('reglas_limpieza', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reserva_id')->unique()->constrained('reservas')->cascadeOnDelete();
            $table->string('frecuencia', 20);
            // Máscara de 7 bits: lunes = 1, martes = 2, … domingo = 64.
            $table->unsignedTinyInteger('dias_semana')->nullable();
            $table->foreignId('creada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('creado_en')->nullable();
            $table->dateTime('actualizado_en')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reglas_limpieza');
        Schema::dropIfExists('tareas');
        Schema::dropIfExists('cambios_estado_unidad');
        Schema::dropIfExists('estados_unidad');
        Schema::dropIfExists('archivos');
    }
};
