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
 * Propiedades, principales de HotelDruid, ejecuciones de sincronización y espejo.
 * Ver docs/especificacion/modelo-datos.md §4.2 y §4.3.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('propiedades', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 150);
            $table->string('codigo', 40)->unique();
            $table->char('color', 7)->default('#0f766e');
            $table->string('modo', 20)->default('subordinada');
            $table->string('paquete_pais', 20)->default('generico');
            $table->char('pais', 2)->nullable();
            $table->string('subdivision', 6)->nullable();
            $table->string('zona_horaria', 64);
            $table->char('moneda', 3);
            $table->string('idioma', 12)->default('es');
            $table->string('domicilio', 255)->nullable();
            $table->string('telefono', 50)->nullable();
            $table->string('correo', 150)->nullable();
            $table->json('ajustes')->nullable();
            $table->boolean('activa')->default(true);
            $table->dateTime('creado_en')->nullable();
            $table->dateTime('actualizado_en')->nullable();
        });

        Schema::create('principales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('propiedad_id')->unique()->constrained('propiedades')->cascadeOnDelete();
            $table->string('url_base', 255);
            $table->text('usuario')->nullable();
            $table->text('contrasena')->nullable();
            $table->string('metodo', 4)->default('post');
            $table->boolean('compresion')->default(true);
            $table->unsignedSmallInteger('frecuencia_minutos')->default(30);
            $table->boolean('permitir_red_local')->default(false);
            $table->unsignedSmallInteger('anio_consulta')->nullable();
            $table->string('estado', 20)->default('activa');
            $table->string('codigo_pausa', 8)->nullable();
            $table->dateTime('pausada_hasta')->nullable();
            $table->dateTime('proxima_en')->nullable()->index();
            $table->unsignedSmallInteger('fallos_consecutivos')->default(0);
            $table->unsignedBigInteger('ejecucion_vigente_id')->nullable();
            $table->string('version_esquema', 10)->nullable();
            $table->string('idioma_principal', 12)->nullable();
            $table->boolean('inicio_sesion_activado')->nullable();
            $table->dateTime('fecha_copia')->nullable();
            $table->dateTime('ultima_correcta_en')->nullable();
            $table->dateTime('creado_en')->nullable();
            $table->dateTime('actualizado_en')->nullable();
        });

        Schema::create('ejecuciones_sincronizacion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('principal_id')->constrained('principales')->cascadeOnDelete();
            $table->string('origen', 20);
            $table->string('estado', 20);
            $table->dateTime('iniciada_en');
            $table->dateTime('terminada_en')->nullable();
            $table->string('paso_fallido', 30)->nullable();
            $table->string('codigo_error', 8)->nullable();
            $table->text('detalle')->nullable();
            $table->unsignedBigInteger('bytes_descargados')->nullable();
            $table->unsignedBigInteger('bytes_descomprimidos')->nullable();
            $table->string('version_esquema', 10)->nullable();
            $table->dateTime('fecha_copia')->nullable();
            $table->json('filas')->nullable();
            $table->unsignedInteger('eventos')->default(0);
            $table->json('incidencias')->nullable();
            $table->index(['principal_id', 'iniciada_en']);
        });

        Schema::table('principales', function (Blueprint $table) {
            $table->foreign('ejecucion_vigente_id')->references('id')->on('ejecuciones_sincronizacion')->nullOnDelete();
        });

        // Espejo de la copia de HotelDruid: una fila por fila de la copia, con sus columnas en JSON.
        // Solo la escribe y la lee el módulo de sincronización (D-013).
        Schema::create('hd_filas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('principal_id')->constrained('principales')->cascadeOnDelete();
            $table->foreignId('ejecucion_id')->constrained('ejecuciones_sincronizacion')->cascadeOnDelete();
            $table->string('tabla', 30);
            $table->unsignedSmallInteger('anio')->nullable();
            $table->json('datos');
            $table->index(['ejecucion_id', 'tabla', 'anio']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hd_filas');
        Schema::table('principales', function (Blueprint $table) {
            $table->dropForeign(['ejecucion_vigente_id']);
        });
        Schema::dropIfExists('ejecuciones_sincronizacion');
        Schema::dropIfExists('principales');
        Schema::dropIfExists('propiedades');
    }
};
