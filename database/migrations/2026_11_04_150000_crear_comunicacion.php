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
 * Plantillas, automatizaciones, avisos a otros sistemas y cola de envíos (modelo-datos.md §4.8).
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('plantillas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('propiedad_id')->constrained('propiedades')->cascadeOnDelete();
            $table->string('codigo', 50);
            $table->string('nombre', 100);
            // Por idioma: {"es": "…", "en": "…"} (D-021).
            $table->json('asunto')->nullable();
            $table->json('cuerpo');
            $table->boolean('activa')->default(true);
            $table->dateTime('creado_en')->nullable();
            $table->dateTime('actualizado_en')->nullable();
            $table->unique(['propiedad_id', 'codigo']);
        });

        Schema::create('automatizaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('propiedad_id')->constrained('propiedades')->cascadeOnDelete();
            $table->string('nombre', 100);
            $table->string('disparador', 30);
            $table->smallInteger('dias')->default(0);
            $table->time('hora')->nullable();
            $table->json('condiciones')->nullable();
            $table->string('accion', 20);
            $table->foreignId('plantilla_id')->constrained('plantillas')->restrictOnDelete();
            $table->string('destino', 255)->nullable();
            $table->boolean('activa')->default(true);
            $table->dateTime('creado_en')->nullable();
            $table->dateTime('actualizado_en')->nullable();
        });

        Schema::create('webhooks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('propiedad_id')->constrained('propiedades')->cascadeOnDelete();
            $table->string('url', 500);
            $table->text('secreto');
            $table->json('eventos');
            $table->boolean('activo')->default(true);
            $table->dateTime('creado_en')->nullable();
            $table->dateTime('actualizado_en')->nullable();
        });

        Schema::create('envios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('propiedad_id')->constrained('propiedades')->cascadeOnDelete();
            $table->foreignId('reserva_id')->nullable()->constrained('reservas')->nullOnDelete();
            $table->foreignId('huesped_id')->nullable()->constrained('huespedes')->nullOnDelete();
            $table->foreignId('automatizacion_id')->nullable()->constrained('automatizaciones')->nullOnDelete();
            $table->foreignId('webhook_id')->nullable()->constrained('webhooks')->nullOnDelete();
            $table->string('canal', 20);
            $table->string('destino', 500)->nullable();
            $table->string('idioma', 12)->nullable();
            $table->string('asunto', 255)->nullable();
            $table->mediumText('cuerpo')->nullable();
            $table->string('estado', 20);
            $table->unsignedSmallInteger('intentos')->default(0);
            $table->text('ultimo_error')->nullable();
            $table->dateTime('programado_para')->nullable();
            $table->dateTime('enviado_en')->nullable();
            $table->string('clave_idempotencia', 190)->nullable()->unique();
            $table->dateTime('creado_en')->nullable();
            $table->dateTime('actualizado_en')->nullable();
            $table->index(['estado', 'programado_para']);
            $table->index(['propiedad_id', 'creado_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('envios');
        Schema::dropIfExists('webhooks');
        Schema::dropIfExists('automatizaciones');
        Schema::dropIfExists('plantillas');
    }
};
