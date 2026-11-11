<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Restricciones de llegada y salida (D-084), enlaces de pago por importe libre (D-085), automatizaciones que no mandan correo
 * (D-086) y opiniones de los huéspedes (D-087).
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('restricciones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('propiedad_id')->constrained('propiedades')->cascadeOnDelete();
            $table->foreignId('unidad_id')->nullable()->constrained('unidades')->cascadeOnDelete();
            $table->string('nombre', 100)->nullable();
            $table->date('desde');
            $table->date('hasta');
            $table->boolean('cerrado_llegada')->default(false);
            $table->boolean('cerrado_salida')->default(false);
            // Máscara de 7 bits, como en las reglas de limpieza: lunes = 1, martes = 2, … domingo = 64.
            $table->unsignedTinyInteger('dias_llegada')->nullable();
            $table->unsignedSmallInteger('estancia_maxima')->nullable();
            $table->unsignedSmallInteger('anticipacion_maxima')->nullable();
            $table->unsignedTinyInteger('noches_preparacion')->nullable();
            $table->boolean('activa')->default(true);
            $table->dateTime('creado_en')->nullable();
            $table->dateTime('actualizado_en')->nullable();
            $table->index(['propiedad_id', 'desde']);
        });
        DB::statement('ALTER TABLE restricciones ADD CONSTRAINT restricciones_fechas_validas CHECK (hasta >= desde)');

        Schema::create('enlaces_pago', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('propiedad_id')->constrained('propiedades')->cascadeOnDelete();
            $table->foreignId('reserva_id')->constrained('reservas')->cascadeOnDelete();
            $table->foreignId('enlace_publico_id')->nullable()->constrained('enlaces_publicos')->nullOnDelete();
            $table->string('concepto', 150);
            $table->bigInteger('importe');
            $table->char('moneda', 3);
            $table->foreignId('caja_id')->nullable()->constrained('cajas')->nullOnDelete();
            $table->dateTime('vence_en');
            $table->dateTime('pagado_en')->nullable();
            $table->foreignId('pago_id')->nullable()->constrained('pagos')->nullOnDelete();
            $table->dateTime('anulado_en')->nullable();
            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('creado_en')->nullable();
            $table->dateTime('actualizado_en')->nullable();
            $table->index('reserva_id');
        });
        DB::statement('ALTER TABLE enlaces_pago ADD CONSTRAINT enlaces_pago_importe_valido CHECK (importe > 0)');

        Schema::table('pagos_en_linea', function (Blueprint $table): void {
            $table->foreignId('enlace_pago_id')->nullable()->after('pago_id')->constrained('enlaces_pago')->nullOnDelete();
        });

        Schema::table('automatizaciones', function (Blueprint $table): void {
            $table->foreignId('plantilla_id')->nullable()->change();
        });

        Schema::table('notas_bitacora', function (Blueprint $table): void {
            $table->string('clave_generacion', 150)->nullable()->unique();
        });

        Schema::create('opiniones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('propiedad_id')->constrained('propiedades')->cascadeOnDelete();
            $table->foreignId('reserva_id')->unique()->constrained('reservas')->cascadeOnDelete();
            $table->unsignedTinyInteger('calificacion')->nullable();
            $table->text('comentario')->nullable();
            $table->dateTime('respondida_en')->nullable();
            $table->dateTime('creado_en')->nullable();
            $table->dateTime('actualizado_en')->nullable();
            $table->index(['propiedad_id', 'respondida_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opiniones');
        Schema::table('notas_bitacora', function (Blueprint $table): void {
            $table->dropColumn('clave_generacion');
        });
        DB::table('automatizaciones')->whereNull('plantilla_id')->delete();
        Schema::table('automatizaciones', function (Blueprint $table): void {
            $table->foreignId('plantilla_id')->nullable(false)->change();
        });
        Schema::table('pagos_en_linea', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('enlace_pago_id');
        });
        Schema::dropIfExists('enlaces_pago');
        Schema::dropIfExists('restricciones');
    }
};
