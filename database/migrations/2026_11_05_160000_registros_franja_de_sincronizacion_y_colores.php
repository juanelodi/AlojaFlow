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
 * Número de registro turístico (D-091), días y franja de sincronización (D-093) y colores de cada usuario (D-090).
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('registros_turisticos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('propiedad_id')->constrained('propiedades')->cascadeOnDelete();
            $table->foreignId('unidad_id')->nullable()->constrained('unidades')->cascadeOnDelete();
            $table->string('tipo', 100);
            $table->string('numero', 100);
            $table->date('vigente_hasta')->nullable();
            $table->boolean('mostrar')->default(true);
            $table->dateTime('creado_en')->nullable();
            $table->dateTime('actualizado_en')->nullable();
            $table->index(['propiedad_id', 'vigente_hasta']);
        });

        Schema::table('propiedades', function (Blueprint $table): void {
            // Un código ISO cabía en 6, pero un país sin catálogo la escribe a mano (D-092).
            $table->string('subdivision', 100)->nullable()->change();
            $table->string('responsable', 150)->nullable()->after('subdivision');
            $table->string('autoridad_quejas', 255)->nullable()->after('responsable');
        });

        Schema::table('principales', function (Blueprint $table): void {
            $table->unsignedTinyInteger('dias_semana')->nullable()->after('frecuencia_minutos');
            $table->time('franja_desde')->nullable()->after('dias_semana');
            $table->time('franja_hasta')->nullable()->after('franja_desde');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->json('colores_estado')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('colores_estado');
        });
        Schema::table('principales', function (Blueprint $table): void {
            $table->dropColumn(['dias_semana', 'franja_desde', 'franja_hasta']);
        });
        Schema::table('propiedades', function (Blueprint $table): void {
            $table->dropColumn(['responsable', 'autoridad_quejas']);
        });
        Schema::dropIfExists('registros_turisticos');
    }
};
