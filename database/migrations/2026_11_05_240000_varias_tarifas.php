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
 * Varias tarifas por unidad (D-118), tarifas que salen de otra (D-119) y políticas no reembolsables (D-120).
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('politicas', function (Blueprint $table): void {
            $table->boolean('no_reembolsable')->default(false)->after('dias_cancelacion_gratuita');
        });

        Schema::table('tarifas', function (Blueprint $table): void {
            // Sin restricción al borrar: el panel no deja, y borrar una propiedad se lleva todas sus tarifas de una vez.
            $table->foreignId('tarifa_base_id')->nullable()->after('propiedad_id')->constrained('tarifas')->nullOnDelete();
            $table->decimal('ajuste_porcentaje', 6, 2)->nullable()->after('tarifa_base_id');
            $table->bigInteger('ajuste_noche')->nullable()->after('ajuste_porcentaje');
            $table->unsignedSmallInteger('personas_minimas')->nullable()->after('estancia_minima');
            $table->unsignedSmallInteger('personas_maximas')->nullable()->after('personas_minimas');
            $table->foreignId('politica_id')->nullable()->after('personas_maximas')->constrained('politicas')->nullOnDelete();
        });

        // Las demás tarifas de una unidad; la principal sigue en unidades.tarifa_id.
        Schema::create('tarifa_unidad', function (Blueprint $table): void {
            $table->foreignId('tarifa_id')->constrained('tarifas')->cascadeOnDelete();
            $table->foreignId('unidad_id')->constrained('unidades')->cascadeOnDelete();
            $table->primary(['tarifa_id', 'unidad_id']);
            $table->index('unidad_id');
        });

        // Con cuál se vendió: de ella sale su política, aunque después se retire.
        Schema::table('reservas', function (Blueprint $table): void {
            $table->foreignId('tarifa_id')->nullable()->after('tarifa_nombre')->constrained('tarifas')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('reservas', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('tarifa_id');
        });
        Schema::dropIfExists('tarifa_unidad');
        Schema::table('tarifas', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('politica_id');
            $table->dropConstrainedForeignId('tarifa_base_id');
            $table->dropColumn(['ajuste_porcentaje', 'ajuste_noche', 'personas_minimas', 'personas_maximas']);
        });
        Schema::table('politicas', function (Blueprint $table): void {
            $table->dropColumn('no_reembolsable');
        });
    }
};
