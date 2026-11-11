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

/*
 * Lo de un camping: qué tiene cada lugar, el equipo del huésped, lecturas de medidor, precio de temporada completa y extras con cupo (D-110 a D-113).
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('unidades', function (Blueprint $table): void {
            $table->json('atributos')->nullable()->after('capacidad');
        });

        // Cifrado: las placas de un vehículo dicen de quién es.
        Schema::table('reservas', function (Blueprint $table): void {
            $table->text('equipo')->nullable()->after('comentario');
        });

        Schema::table('tarifas_temporadas', function (Blueprint $table): void {
            $table->bigInteger('precio_completa')->nullable()->after('precio_mes');
        });

        Schema::table('conceptos_cargo', function (Blueprint $table): void {
            $table->unsignedInteger('cupo_diario')->nullable()->after('porcentaje');
        });

        Schema::table('cuotas_reserva', function (Blueprint $table): void {
            $table->foreignId('cargo_id')->nullable()->after('reserva_id')->constrained('reservas_cargos')->nullOnDelete();
        });

        Schema::create('lecturas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('propiedad_id')->constrained('propiedades')->cascadeOnDelete();
            $table->foreignId('unidad_id')->constrained('unidades')->cascadeOnDelete();
            $table->foreignId('reserva_id')->nullable()->constrained('reservas')->nullOnDelete();
            $table->foreignId('cargo_id')->nullable()->constrained('reservas_cargos')->nullOnDelete();
            $table->string('servicio', 10);
            $table->date('leida_en');
            $table->decimal('actual', 12, 2);
            $table->decimal('consumo', 12, 2)->default(0);
            $table->decimal('tarifa', 12, 4)->nullable();
            $table->bigInteger('importe')->default(0);
            $table->boolean('inicial')->default(false);
            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('creado_en')->nullable();
            $table->dateTime('actualizado_en')->nullable();
            $table->index(['unidad_id', 'servicio', 'leida_en', 'id']);
            $table->index(['propiedad_id', 'servicio', 'leida_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lecturas');
        Schema::table('cuotas_reserva', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('cargo_id');
        });
        Schema::table('conceptos_cargo', function (Blueprint $table): void {
            $table->dropColumn('cupo_diario');
        });
        Schema::table('tarifas_temporadas', function (Blueprint $table): void {
            $table->dropColumn('precio_completa');
        });
        Schema::table('reservas', function (Blueprint $table): void {
            $table->dropColumn('equipo');
        });
        Schema::table('unidades', function (Blueprint $table): void {
            $table->dropColumn('atributos');
        });
    }
};
