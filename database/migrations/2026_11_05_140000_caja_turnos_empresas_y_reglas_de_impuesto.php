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
 * La caja de la principal, uno por uno (D-078), el turno de caja y la bitácora (D-080), empresas y agencias (D-081) y las reglas finas
 * de impuesto con lo que se cobró en cada reserva (D-082).
 */
return new class () extends Migration {
    private const CON_ORIGEN = ['cajas', 'pagos', 'movimientos'];

    public function up(): void
    {
        foreach (self::CON_ORIGEN as $tabla) {
            Schema::table($tabla, function (Blueprint $table): void {
                $table->boolean('sincronizada')->default(false);
                $table->foreignId('principal_id')->nullable()->constrained('principales')->nullOnDelete();
                $table->string('clave_origen', 60)->nullable();
                $table->unique(['principal_id', 'clave_origen']);
            });
        }

        Schema::create('turnos_caja', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('propiedad_id')->constrained('propiedades')->cascadeOnDelete();
            $table->foreignId('caja_id')->constrained('cajas')->cascadeOnDelete();
            // Igual a caja_id mientras esté abierto: la base de datos impide dos turnos abiertos en una caja.
            $table->unsignedBigInteger('caja_abierta')->nullable()->unique();
            $table->foreignId('abierto_por')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('abierto_en');
            $table->bigInteger('fondo_inicial')->default(0);
            $table->char('moneda', 3);
            $table->foreignId('cerrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('cerrado_en')->nullable();
            $table->bigInteger('esperado')->nullable();
            $table->bigInteger('contado')->nullable();
            $table->bigInteger('diferencia')->nullable();
            $table->json('resumen')->nullable();
            $table->text('notas')->nullable();
            $table->dateTime('creado_en')->nullable();
            $table->dateTime('actualizado_en')->nullable();
            $table->index(['propiedad_id', 'abierto_en']);
        });

        Schema::create('notas_bitacora', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('propiedad_id')->constrained('propiedades')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('texto');
            $table->boolean('importante')->default(false);
            $table->dateTime('resuelta_en')->nullable();
            $table->foreignId('resuelta_por')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('creado_en')->nullable();
            $table->dateTime('actualizado_en')->nullable();
            $table->index(['propiedad_id', 'creado_en']);
        });

        Schema::create('empresas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('propiedad_id')->constrained('propiedades')->cascadeOnDelete();
            $table->string('tipo', 20)->default('empresa');
            $table->string('nombre', 150);
            $table->string('identificador_fiscal', 30)->nullable();
            $table->string('contacto', 150)->nullable();
            $table->string('correo', 150)->nullable();
            $table->string('telefono', 40)->nullable();
            $table->decimal('comision_porcentaje', 7, 4)->nullable();
            $table->unsignedSmallInteger('dias_credito')->nullable();
            $table->text('condiciones')->nullable();
            $table->boolean('activa')->default(true);
            $table->dateTime('creado_en')->nullable();
            $table->dateTime('actualizado_en')->nullable();
            $table->unique(['propiedad_id', 'nombre']);
        });

        Schema::table('reservas', function (Blueprint $table): void {
            $table->foreignId('empresa_id')->nullable()->after('titular_id')->constrained('empresas')->nullOnDelete();
        });

        Schema::table('impuestos', function (Blueprint $table): void {
            $table->date('vigente_desde')->nullable()->after('incluido_en_precio');
            $table->date('vigente_hasta')->nullable()->after('vigente_desde');
            $table->unsignedSmallInteger('tope_noches')->nullable()->after('vigente_hasta');
            $table->unsignedTinyInteger('edad_minima')->nullable()->after('tope_noches');
        });

        Schema::table('canales', function (Blueprint $table): void {
            $table->boolean('cobra_impuestos')->default(false)->after('comision_porcentaje');
        });

        Schema::create('reservas_impuestos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('reserva_id')->constrained('reservas')->cascadeOnDelete();
            $table->foreignId('impuesto_id')->nullable()->constrained('impuestos')->nullOnDelete();
            $table->string('nombre', 100);
            $table->bigInteger('importe');
            $table->boolean('incluido')->default(false);
            $table->boolean('cobrado_por_canal')->default(false);
            $table->index('reserva_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservas_impuestos');
        Schema::table('canales', function (Blueprint $table): void {
            $table->dropColumn('cobra_impuestos');
        });
        Schema::table('impuestos', function (Blueprint $table): void {
            $table->dropColumn(['vigente_desde', 'vigente_hasta', 'tope_noches', 'edad_minima']);
        });
        Schema::table('reservas', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('empresa_id');
        });
        Schema::dropIfExists('empresas');
        Schema::dropIfExists('notas_bitacora');
        Schema::dropIfExists('turnos_caja');
        foreach (self::CON_ORIGEN as $tabla) {
            Schema::table($tabla, function (Blueprint $table): void {
                $table->dropUnique(['principal_id', 'clave_origen']);
                $table->dropConstrainedForeignId('principal_id');
                $table->dropColumn(['sincronizada', 'clave_origen']);
            });
        }
    }
};
