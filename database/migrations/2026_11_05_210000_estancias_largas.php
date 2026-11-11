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
 * Precio por semana y por mes, impuesto que no se cobra en una estancia larga, calendario de cobros y factura por cuota (D-107 a D-109).
 */
return new class () extends Migration {
    public function up(): void
    {
        foreach (['tarifas', 'tarifas_temporadas'] as $tabla) {
            Schema::table($tabla, function (Blueprint $table): void {
                $table->bigInteger('precio_semana')->nullable()->after('precio_fin_semana');
                $table->bigInteger('precio_mes')->nullable()->after('precio_semana');
            });
        }

        Schema::table('impuestos', function (Blueprint $table): void {
            $table->unsignedSmallInteger('exento_desde_noches')->nullable()->after('tope_noches');
        });

        Schema::create('cuotas_reserva', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('reserva_id')->constrained('reservas')->cascadeOnDelete();
            $table->string('concepto', 150)->nullable();
            $table->date('desde')->nullable();
            $table->date('hasta')->nullable();
            $table->unsignedBigInteger('importe');
            $table->date('vence_en');
            $table->dateTime('creado_en')->nullable();
            $table->dateTime('actualizado_en')->nullable();
            $table->index(['reserva_id', 'vence_en']);
            $table->index('vence_en');
        });

        Schema::table('facturas', function (Blueprint $table): void {
            $table->foreignId('cuota_reserva_id')->nullable()->after('serie_id')->constrained('cuotas_reserva')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('facturas', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('cuota_reserva_id');
        });
        Schema::dropIfExists('cuotas_reserva');
        Schema::table('impuestos', function (Blueprint $table): void {
            $table->dropColumn('exento_desde_noches');
        });
        foreach (['tarifas', 'tarifas_temporadas'] as $tabla) {
            Schema::table($tabla, function (Blueprint $table): void {
                $table->dropColumn(['precio_semana', 'precio_mes']);
            });
        }
    }
};
