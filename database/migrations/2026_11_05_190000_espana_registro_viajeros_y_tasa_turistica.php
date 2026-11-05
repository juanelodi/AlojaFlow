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
 * Lo que se comunica al registro de viajeros (D-100) y las reglas de una tasa turística por persona y noche (D-101).
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('envios_autoridad', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('propiedad_id')->constrained('propiedades')->cascadeOnDelete();
            $table->foreignId('reserva_id')->nullable()->constrained('reservas')->nullOnDelete();
            $table->string('servicio', 20);
            $table->string('tipo', 2);
            $table->string('estado', 20)->default('pendiente');
            $table->char('huella', 64)->nullable();
            $table->string('lote', 36)->nullable();
            $table->unsignedSmallInteger('orden')->nullable();
            $table->string('codigo_comunicacion', 36)->nullable();
            $table->string('anulacion_lote', 36)->nullable();
            $table->json('faltan')->nullable();
            $table->text('correcciones')->nullable();
            $table->text('ultimo_error')->nullable();
            $table->unsignedSmallInteger('intentos')->default(0);
            $table->dateTime('proximo_intento_en')->nullable();
            $table->dateTime('vence_en')->nullable();
            $table->dateTime('enviado_en')->nullable();
            $table->dateTime('respondido_en')->nullable();
            $table->dateTime('creado_en')->nullable();
            $table->dateTime('actualizado_en')->nullable();
            $table->index(['propiedad_id', 'estado']);
            $table->index(['reserva_id', 'tipo']);
            $table->index('lote');
            $table->index('anulacion_lote');
        });

        Schema::table('impuestos', function (Blueprint $table): void {
            $table->boolean('fijar_al_reservar')->default(false);
            $table->string('temporada_baja_desde', 5)->nullable();
            $table->string('temporada_baja_hasta', 5)->nullable();
            $table->decimal('temporada_baja_descuento', 5, 2)->nullable();
            $table->unsignedSmallInteger('dias_largos_desde')->nullable();
            $table->decimal('dias_largos_descuento', 5, 2)->nullable();
        });

        Schema::table('reservas_impuestos', function (Blueprint $table): void {
            $table->unsignedInteger('unidades')->default(0);
            $table->unsignedInteger('unidades_exentas')->default(0);
            $table->bigInteger('importe_unitario')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('reservas_impuestos', function (Blueprint $table): void {
            $table->dropColumn(['unidades', 'unidades_exentas', 'importe_unitario']);
        });
        Schema::table('impuestos', function (Blueprint $table): void {
            $table->dropColumn(['fijar_al_reservar', 'temporada_baja_desde', 'temporada_baja_hasta', 'temporada_baja_descuento', 'dias_largos_desde', 'dias_largos_descuento']);
        });
        Schema::dropIfExists('envios_autoridad');
    }
};
