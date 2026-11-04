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
 * Calendarios iCal para los portales y bloqueos que traen (modelo-datos.md §4.9), y enlaces públicos
 * con token (§4.6, D-022).
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('enlaces_publicos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('propiedad_id')->constrained('propiedades')->cascadeOnDelete();
            $table->string('tipo', 20);
            $table->nullableMorphs('objeto');
            // Cifrado para poder enseñarlo otra vez; el HMAC, para buscarlo sin descifrar nada.
            $table->text('token_cifrado');
            $table->char('token_hmac', 64)->unique();
            $table->dateTime('expira_en')->nullable();
            $table->dateTime('revocado_en')->nullable();
            $table->unsignedInteger('usos')->default(0);
            $table->dateTime('ultimo_uso_en')->nullable();
            $table->dateTime('creado_en')->nullable();
            $table->dateTime('actualizado_en')->nullable();
        });

        Schema::create('calendarios_exportados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unidad_id')->unique()->constrained('unidades')->cascadeOnDelete();
            $table->foreignId('enlace_publico_id')->constrained('enlaces_publicos')->cascadeOnDelete();
            $table->boolean('incluir_bloqueos')->default(true);
            $table->boolean('activo')->default(true);
            $table->dateTime('creado_en')->nullable();
            $table->dateTime('actualizado_en')->nullable();
        });

        Schema::create('fuentes_ical', function (Blueprint $table) {
            $table->id();
            $table->foreignId('propiedad_id')->constrained('propiedades')->cascadeOnDelete();
            $table->foreignId('unidad_id')->constrained('unidades')->cascadeOnDelete();
            $table->foreignId('canal_id')->nullable()->constrained('canales')->nullOnDelete();
            $table->string('nombre', 100);
            // Cifrada: lleva el token secreto del portal.
            $table->text('url');
            $table->unsignedSmallInteger('frecuencia_minutos')->default(30);
            $table->string('estado', 20)->default('sin_leer');
            $table->dateTime('ultimo_intento_en')->nullable();
            $table->dateTime('ultimo_exito_en')->nullable();
            $table->text('ultimo_error')->nullable();
            $table->boolean('activa')->default(true);
            $table->dateTime('creado_en')->nullable();
            $table->dateTime('actualizado_en')->nullable();
        });

        Schema::create('bloqueos_externos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fuente_id')->constrained('fuentes_ical')->cascadeOnDelete();
            $table->foreignId('propiedad_id')->constrained('propiedades')->cascadeOnDelete();
            $table->foreignId('unidad_id')->constrained('unidades')->cascadeOnDelete();
            $table->string('uid', 255);
            $table->date('llegada');
            $table->date('salida');
            $table->string('resumen', 60)->nullable();
            $table->dateTime('primera_vez_en');
            $table->dateTime('visto_en');
            $table->dateTime('eliminado_en')->nullable();
            $table->unique(['fuente_id', 'uid']);
            $table->index(['unidad_id', 'llegada', 'salida']);
        });

        Schema::create('choques', function (Blueprint $table) {
            $table->id();
            $table->foreignId('propiedad_id')->constrained('propiedades')->cascadeOnDelete();
            $table->foreignId('unidad_id')->constrained('unidades')->cascadeOnDelete();
            $table->date('desde');
            $table->date('hasta');
            $table->string('tipo', 20);
            $table->foreignId('bloqueo_externo_id')->nullable()->constrained('bloqueos_externos')->cascadeOnDelete();
            $table->foreignId('reserva_id')->constrained('reservas')->cascadeOnDelete();
            $table->foreignId('reserva2_id')->nullable()->constrained('reservas')->cascadeOnDelete();
            // Sin valor al resolverse solo: el mismo choque puede volver a aparecer.
            $table->string('clave_unica', 100)->nullable()->unique();
            $table->dateTime('detectado_en');
            $table->dateTime('resuelto_en')->nullable();
            $table->foreignId('resuelto_por')->nullable()->constrained('users')->nullOnDelete();
            $table->text('nota')->nullable();
            $table->index(['propiedad_id', 'resuelto_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('choques');
        Schema::dropIfExists('bloqueos_externos');
        Schema::dropIfExists('fuentes_ical');
        Schema::dropIfExists('calendarios_exportados');
        Schema::dropIfExists('enlaces_publicos');
    }
};
