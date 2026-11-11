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
 * Mejora continua (D-150): lo que se junta aquí antes de mandarlo, y los reportes que escribe el equipo.
 * Nada de huéspedes: errores ya saneados, visitas por nombre de pantalla y reportes con su texto.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('mejora_errores', function (Blueprint $table): void {
            $table->id();
            // Misma clase, lugar y mensaje sin datos, el mismo día y con la misma versión: una fila con su conteo.
            $table->char('huella', 64);
            $table->date('fecha');
            $table->string('version', 20);
            $table->string('clase', 255);
            $table->text('mensaje');
            $table->string('codigo', 40)->nullable();
            $table->string('archivo', 255)->nullable();
            $table->unsignedInteger('linea')->nullable();
            $table->string('funcion', 255)->nullable();
            $table->json('traza')->nullable();
            $table->json('contexto')->nullable();
            $table->unsignedInteger('veces')->default(0);
            $table->timestamp('primera_vez')->nullable();
            $table->timestamp('ultima_vez')->nullable();
            $table->timestamp('creado_en')->nullable();
            $table->timestamp('actualizado_en')->nullable();
            $table->unique(['huella', 'fecha', 'version']);
        });

        Schema::create('mejora_pantallas', function (Blueprint $table): void {
            $table->date('fecha');
            $table->string('pantalla', 150);
            $table->unsignedInteger('visitas')->default(0);
            $table->primary(['fecha', 'pantalla']);
        });

        Schema::create('reportes_mejora', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            // La lleva el receptor: si se reintenta, no se duplica.
            $table->uuid('clave')->unique();
            $table->string('tipo', 20);
            $table->string('titulo', 150);
            $table->text('descripcion');
            $table->text('pasos')->nullable();
            $table->string('pantalla', 255)->nullable();
            $table->string('idioma', 10)->nullable();
            $table->string('navegador', 255)->nullable();
            $table->string('resolucion', 20)->nullable();
            $table->string('rol', 60)->nullable();
            $table->string('contacto_nombre', 150)->nullable();
            $table->string('contacto_correo', 190)->nullable();
            $table->boolean('permite_contacto')->default(false);
            $table->boolean('adjuntar_diagnostico')->default(true);
            // Rutas en el disco privado, hasta que se mandan; después se borran.
            $table->json('imagenes')->nullable();
            $table->unsignedTinyInteger('imagenes_enviadas')->default(0);
            // pendiente | enviado | error
            $table->string('envio', 20)->default('pendiente')->index();
            $table->unsignedSmallInteger('intentos')->default(0);
            $table->string('ultimo_error', 255)->nullable();
            $table->timestamp('enviado_en')->nullable();
            // Lo que contesta el equipo de AlojaFlow.
            $table->string('folio', 20)->nullable();
            $table->string('estado', 20)->nullable();
            $table->text('respuesta')->nullable();
            $table->timestamp('respondido_en')->nullable();
            $table->string('resuelto_en_version', 20)->nullable();
            $table->timestamp('creado_en')->nullable();
            $table->timestamp('actualizado_en')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reportes_mejora');
        Schema::dropIfExists('mejora_pantallas');
        Schema::dropIfExists('mejora_errores');
    }
};
