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
 * Llegada en línea, documentos legales con aceptación y privacidad (modelo-datos.md §4.6 y §4.12).
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('documentos_legales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('propiedad_id')->constrained('propiedades')->cascadeOnDelete();
            $table->string('tipo', 30);
            $table->string('idioma', 12);
            $table->unsignedSmallInteger('version');
            $table->string('titulo', 150);
            $table->mediumText('cuerpo');
            $table->char('huella', 64)->nullable();
            $table->dateTime('publicado_en')->nullable();
            $table->foreignId('publicado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('retirado_en')->nullable();
            $table->dateTime('creado_en')->nullable();
            $table->dateTime('actualizado_en')->nullable();
            $table->unique(['propiedad_id', 'tipo', 'idioma', 'version']);
        });

        Schema::create('registros_llegada', function (Blueprint $table) {
            $table->id();
            $table->foreignId('propiedad_id')->constrained('propiedades')->cascadeOnDelete();
            $table->foreignId('reserva_id')->unique()->constrained('reservas')->cascadeOnDelete();
            $table->string('estado', 20)->default('pendiente');
            $table->string('idioma', 12)->nullable();
            $table->string('hora_llegada_estimada', 5)->nullable();
            $table->text('notas_huesped')->nullable();
            // Mientras se verifica el código: qué documentos marcó el huésped.
            $table->json('documentos_marcados')->nullable();
            $table->dateTime('recibido_en')->nullable();
            $table->foreignId('revisado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('revisado_en')->nullable();
            $table->date('conservar_hasta')->nullable()->index();
            $table->dateTime('creado_en')->nullable();
            $table->dateTime('actualizado_en')->nullable();
            $table->index(['propiedad_id', 'estado']);
        });

        Schema::create('registros_llegada_personas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registro_llegada_id')->constrained('registros_llegada')->cascadeOnDelete();
            $table->unsignedSmallInteger('orden');
            $table->boolean('es_titular')->default(false);
            $table->string('nombre', 100);
            $table->string('apellido1', 100)->nullable();
            $table->string('apellido2', 100)->nullable();
            $table->string('sexo', 10)->nullable();
            $table->date('fecha_nacimiento')->nullable();
            $table->string('nacionalidad_texto', 100)->nullable();
            $table->string('documento_tipo', 30)->nullable();
            $table->text('documento_numero')->nullable();
            $table->char('documento_numero_hmac', 64)->nullable();
            $table->date('documento_vencimiento')->nullable();
            $table->string('domicilio_calle', 255)->nullable();
            $table->string('domicilio_ciudad', 100)->nullable();
            $table->string('domicilio_region', 100)->nullable();
            $table->string('domicilio_codigo_postal', 20)->nullable();
            $table->string('domicilio_pais_texto', 100)->nullable();
            $table->string('correo', 150)->nullable();
            $table->string('telefono', 50)->nullable();
            $table->json('datos_pais')->nullable();
            $table->dateTime('creado_en')->nullable();
            $table->dateTime('actualizado_en')->nullable();
        });

        Schema::create('codigos_otp', function (Blueprint $table) {
            $table->id();
            $table->string('proposito', 30);
            $table->string('canal', 20);
            $table->char('destino_hmac', 64);
            $table->string('codigo_hash', 255);
            $table->unsignedTinyInteger('intentos')->default(0);
            $table->dateTime('expira_en')->index();
            $table->dateTime('verificado_en')->nullable();
            $table->nullableMorphs('objeto');
            $table->dateTime('creado_en')->nullable();
            $table->dateTime('actualizado_en')->nullable();
        });

        Schema::create('aceptaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('documento_legal_id')->constrained('documentos_legales')->restrictOnDelete();
            $table->foreignId('propiedad_id')->constrained('propiedades')->cascadeOnDelete();
            $table->foreignId('reserva_id')->nullable()->constrained('reservas')->nullOnDelete();
            $table->foreignId('registro_llegada_id')->nullable()->constrained('registros_llegada')->nullOnDelete();
            $table->string('firmante_nombre', 200);
            $table->char('huella_documento', 64);
            $table->dateTime('aceptada_en');
            $table->string('ip', 45)->nullable();
            $table->string('agente', 255)->nullable();
            $table->string('verificacion', 20);
            $table->foreignId('codigo_otp_id')->nullable()->constrained('codigos_otp')->nullOnDelete();
            $table->foreignId('copia_archivo_id')->nullable()->constrained('archivos')->nullOnDelete();
            $table->date('conservar_hasta')->nullable()->index();
            $table->dateTime('creado_en')->nullable();
            $table->dateTime('actualizado_en')->nullable();
        });

        Schema::create('solicitudes_privacidad', function (Blueprint $table) {
            $table->id();
            $table->foreignId('propiedad_id')->constrained('propiedades')->cascadeOnDelete();
            $table->foreignId('huesped_id')->nullable()->constrained('huespedes')->nullOnDelete();
            $table->string('tipo', 20);
            $table->foreignId('solicitada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('estado', 20);
            $table->dateTime('completada_en')->nullable();
            $table->foreignId('archivo_id')->nullable()->constrained('archivos')->nullOnDelete();
            $table->dateTime('creado_en')->nullable();
            $table->dateTime('actualizado_en')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitudes_privacidad');
        Schema::dropIfExists('aceptaciones');
        Schema::dropIfExists('codigos_otp');
        Schema::dropIfExists('registros_llegada_personas');
        Schema::dropIfExists('registros_llegada');
        Schema::dropIfExists('documentos_legales');
    }
};
