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
 * Las fotos y los textos de la página pública (D-104), y la guía digital (D-106).
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('fotos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('propiedad_id')->constrained('propiedades')->cascadeOnDelete();
            $table->foreignId('tarifa_id')->nullable()->constrained('tarifas')->cascadeOnDelete();
            $table->foreignId('archivo_id')->nullable()->constrained('archivos')->nullOnDelete();
            $table->foreignId('miniatura_id')->nullable()->constrained('archivos')->nullOnDelete();
            $table->string('texto_alternativo', 200)->nullable();
            $table->unsignedInteger('orden')->default(0);
            $table->dateTime('creado_en')->nullable();
            $table->dateTime('actualizado_en')->nullable();
            $table->index(['propiedad_id', 'tarifa_id', 'orden']);
        });

        Schema::create('guia_secciones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('propiedad_id')->constrained('propiedades')->cascadeOnDelete();
            $table->foreignId('unidad_id')->nullable()->constrained('unidades')->cascadeOnDelete();
            $table->string('tipo', 20);
            $table->string('momento', 10)->default('siempre');
            $table->boolean('solo_con_reserva')->default(false);
            $table->boolean('requiere_pago')->default(false);
            $table->json('titulo');
            $table->json('cuerpo');
            $table->unsignedInteger('orden')->default(0);
            $table->boolean('activa')->default(true);
            $table->dateTime('creado_en')->nullable();
            $table->dateTime('actualizado_en')->nullable();
            $table->index(['propiedad_id', 'activa', 'orden']);
        });

        Schema::table('propiedades', function (Blueprint $table): void {
            $table->json('pagina_publica')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('propiedades', function (Blueprint $table): void {
            $table->dropColumn('pagina_publica');
        });
        Schema::dropIfExists('guia_secciones');
        Schema::dropIfExists('fotos');
    }
};
