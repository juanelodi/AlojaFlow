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

/*
 * Campos propios de huéspedes y reservas (D-122) y documentos propios (D-123). Los valores van en su tabla: la sincronización no la toca.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('campos_propios', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('propiedad_id')->constrained('propiedades')->cascadeOnDelete();
            $table->string('entidad', 10);
            $table->string('nombre', 100);
            $table->string('clave', 60);
            $table->string('tipo', 10)->default('texto');
            $table->unsignedSmallInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->dateTime('creado_en')->nullable();
            $table->dateTime('actualizado_en')->nullable();
            $table->unique(['propiedad_id', 'clave']);
        });

        Schema::create('valores_campo_propio', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campo_propio_id')->constrained('campos_propios')->cascadeOnDelete();
            $table->foreignId('huesped_id')->nullable()->constrained('huespedes')->cascadeOnDelete();
            $table->foreignId('reserva_id')->nullable()->constrained('reservas')->cascadeOnDelete();
            $table->text('valor');
            $table->unique(['campo_propio_id', 'huesped_id']);
            $table->unique(['campo_propio_id', 'reserva_id']);
        });

        Schema::create('documentos_propios', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('propiedad_id')->constrained('propiedades')->cascadeOnDelete();
            $table->string('nombre', 100);
            $table->string('alcance', 10)->default('reserva');
            $table->json('cuerpo');
            $table->unsignedSmallInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->dateTime('creado_en')->nullable();
            $table->dateTime('actualizado_en')->nullable();
            $table->unique(['propiedad_id', 'nombre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documentos_propios');
        Schema::dropIfExists('valores_campo_propio');
        Schema::dropIfExists('campos_propios');
    }
};
