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
 * Plano de habitaciones y colores por tipo de cliente (modelo-datos.md §4.2 y §4.5).
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('planos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('propiedad_id')->constrained('propiedades')->cascadeOnDelete();
            $table->string('nombre', 100);
            $table->foreignId('archivo_id')->nullable()->constrained('archivos')->nullOnDelete();
            $table->integer('orden')->default(0);
            $table->dateTime('creado_en')->nullable();
            $table->dateTime('actualizado_en')->nullable();
        });

        Schema::create('planos_unidades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plano_id')->constrained('planos')->cascadeOnDelete();
            $table->foreignId('unidad_id')->constrained('unidades')->cascadeOnDelete();
            // En porcentaje del plano: así se ve igual en un teléfono y en una pantalla grande.
            $table->decimal('x', 5, 2)->default(0);
            $table->decimal('y', 5, 2)->default(0);
            $table->unique(['plano_id', 'unidad_id']);
        });

        Schema::create('colores_tipo_cliente', function (Blueprint $table) {
            $table->id();
            $table->foreignId('propiedad_id')->constrained('propiedades')->cascadeOnDelete();
            $table->string('campo', 100);
            $table->string('valor', 255);
            $table->char('color', 7);
            $table->dateTime('creado_en')->nullable();
            $table->dateTime('actualizado_en')->nullable();
            $table->unique(['propiedad_id', 'campo', 'valor']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('colores_tipo_cliente');
        Schema::dropIfExists('planos_unidades');
        Schema::dropIfExists('planos');
    }
};
