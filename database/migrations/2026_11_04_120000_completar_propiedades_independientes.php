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
 * Lo que le faltaba a una propiedad independiente para trabajar sola (D-041): precios por persona y
 * de fin de semana, impuestos, cargos, canales, caja, grupos, cupones, notas internas y pagos en
 * línea. Ver docs/especificacion/modelo-datos.md §4.4, §4.9, §4.10 y §5.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('tarifas', function (Blueprint $table) {
            $table->string('modo', 20)->default('por_noche')->after('nombre');
            $table->unsignedSmallInteger('personas_incluidas')->default(2)->after('precio_noche');
            $table->bigInteger('precio_persona_extra')->default(0)->after('personas_incluidas');
            $table->bigInteger('precio_fin_semana')->nullable()->after('precio_persona_extra');
            $table->unsignedSmallInteger('estancia_minima')->default(1)->after('precio_fin_semana');
        });

        Schema::table('tarifas_temporadas', function (Blueprint $table) {
            $table->bigInteger('precio_persona_extra')->nullable()->after('precio_noche');
            $table->bigInteger('precio_fin_semana')->nullable()->after('precio_persona_extra');
            $table->unsignedSmallInteger('estancia_minima')->nullable()->after('precio_fin_semana');
        });

        Schema::create('impuestos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('propiedad_id')->constrained('propiedades')->cascadeOnDelete();
            $table->string('codigo', 30)->nullable();
            $table->string('nombre', 100);
            $table->string('base', 30)->default('porcentaje_alojamiento');
            $table->decimal('tasa', 7, 4)->nullable();
            $table->bigInteger('importe')->nullable();
            $table->boolean('incluido_en_precio')->default(true);
            $table->integer('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->dateTime('creado_en')->nullable();
            $table->dateTime('actualizado_en')->nullable();
        });

        Schema::create('conceptos_cargo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('propiedad_id')->constrained('propiedades')->cascadeOnDelete();
            $table->string('nombre', 100);
            $table->string('base', 30)->default('por_estancia');
            $table->bigInteger('importe')->default(0);
            $table->decimal('porcentaje', 7, 4)->nullable();
            $table->boolean('obligatorio')->default(false);
            $table->boolean('visible_motor')->default(true);
            $table->boolean('activo')->default(true);
            $table->integer('orden')->default(0);
            $table->dateTime('creado_en')->nullable();
            $table->dateTime('actualizado_en')->nullable();
        });

        Schema::create('reservas_cargos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reserva_id')->constrained('reservas')->cascadeOnDelete();
            $table->foreignId('concepto_cargo_id')->nullable()->constrained('conceptos_cargo')->nullOnDelete();
            $table->string('nombre', 100);
            $table->decimal('cantidad', 10, 2)->default(1);
            $table->bigInteger('importe');
            $table->dateTime('creado_en')->nullable();
            $table->dateTime('actualizado_en')->nullable();
        });

        Schema::create('canales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('propiedad_id')->constrained('propiedades')->cascadeOnDelete();
            $table->string('nombre', 100);
            $table->decimal('comision_porcentaje', 7, 4)->nullable();
            $table->boolean('activo')->default(true);
            $table->dateTime('creado_en')->nullable();
            $table->dateTime('actualizado_en')->nullable();
            $table->unique(['propiedad_id', 'nombre']);
        });

        Schema::create('cajas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('propiedad_id')->constrained('propiedades')->cascadeOnDelete();
            $table->string('nombre', 100);
            $table->boolean('activa')->default(true);
            $table->dateTime('creado_en')->nullable();
            $table->dateTime('actualizado_en')->nullable();
            $table->unique(['propiedad_id', 'nombre']);
        });

        // Gastos e ingresos que no son de una reserva; los cobros van en «pagos».
        Schema::create('movimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('propiedad_id')->constrained('propiedades')->cascadeOnDelete();
            $table->foreignId('caja_id')->nullable()->constrained('cajas')->nullOnDelete();
            $table->date('fecha');
            $table->string('tipo', 20);
            $table->string('categoria', 100)->nullable();
            $table->string('concepto', 255);
            $table->string('metodo', 20);
            $table->bigInteger('importe');
            $table->char('moneda', 3);
            $table->string('referencia', 100)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('creado_en')->nullable();
            $table->dateTime('actualizado_en')->nullable();
            $table->index(['propiedad_id', 'fecha']);
        });

        Schema::table('pagos', function (Blueprint $table) {
            $table->foreignId('caja_id')->nullable()->after('reserva_id')->constrained('cajas')->nullOnDelete();
        });

        Schema::create('notas_reserva', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reserva_id')->constrained('reservas')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('texto');
            $table->dateTime('creado_en')->nullable();
            $table->dateTime('actualizado_en')->nullable();
        });

        Schema::create('grupos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('propiedad_id')->constrained('propiedades')->cascadeOnDelete();
            $table->string('codigo', 30);
            $table->string('nombre', 150);
            $table->foreignId('coordinador_id')->nullable()->constrained('huespedes')->nullOnDelete();
            $table->text('notas')->nullable();
            $table->dateTime('creado_en')->nullable();
            $table->dateTime('actualizado_en')->nullable();
            $table->unique(['propiedad_id', 'codigo']);
        });

        Schema::create('cupones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('propiedad_id')->constrained('propiedades')->cascadeOnDelete();
            $table->string('codigo', 40);
            $table->string('descripcion', 150)->nullable();
            $table->string('tipo', 20)->default('porcentaje');
            $table->decimal('porcentaje', 7, 4)->nullable();
            $table->bigInteger('importe')->nullable();
            $table->date('valido_desde')->nullable();
            $table->date('valido_hasta')->nullable();
            $table->unsignedInteger('usos_maximos')->nullable();
            $table->unsignedSmallInteger('estancia_minima')->default(1);
            $table->boolean('activo')->default(true);
            $table->dateTime('creado_en')->nullable();
            $table->dateTime('actualizado_en')->nullable();
            $table->unique(['propiedad_id', 'codigo']);
        });

        Schema::table('reservas', function (Blueprint $table) {
            $table->foreignId('grupo_id')->nullable()->after('grupo_codigo')->constrained('grupos')->nullOnDelete();
            $table->foreignId('cupon_id')->nullable()->after('grupo_id')->constrained('cupones')->nullOnDelete();
            // Reserva retenida mientras el huésped paga en el motor de reservas.
            $table->dateTime('expira_en')->nullable()->after('version')->index();
            $table->string('idioma', 12)->nullable()->after('expira_en');
        });

        Schema::create('pagos_en_linea', function (Blueprint $table) {
            $table->id();
            $table->foreignId('propiedad_id')->constrained('propiedades')->cascadeOnDelete();
            $table->foreignId('reserva_id')->constrained('reservas')->cascadeOnDelete();
            $table->foreignId('pago_id')->nullable()->constrained('pagos')->nullOnDelete();
            $table->string('pasarela', 20);
            $table->string('referencia', 190)->nullable();
            $table->string('estado', 20)->default('pendiente');
            $table->bigInteger('importe');
            $table->char('moneda', 3);
            $table->json('datos')->nullable();
            $table->dateTime('creado_en')->nullable();
            $table->dateTime('actualizado_en')->nullable();
            $table->unique(['pasarela', 'referencia']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos_en_linea');
        Schema::table('reservas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cupon_id');
            $table->dropConstrainedForeignId('grupo_id');
            $table->dropColumn(['expira_en', 'idioma']);
        });
        Schema::dropIfExists('cupones');
        Schema::dropIfExists('grupos');
        Schema::dropIfExists('notas_reserva');
        Schema::table('pagos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('caja_id');
        });
        Schema::dropIfExists('movimientos');
        Schema::dropIfExists('cajas');
        Schema::dropIfExists('canales');
        Schema::dropIfExists('reservas_cargos');
        Schema::dropIfExists('conceptos_cargo');
        Schema::dropIfExists('impuestos');
        Schema::table('tarifas_temporadas', function (Blueprint $table) {
            $table->dropColumn(['precio_persona_extra', 'precio_fin_semana', 'estancia_minima']);
        });
        Schema::table('tarifas', function (Blueprint $table) {
            $table->dropColumn(['modo', 'personas_incluidas', 'precio_persona_extra', 'precio_fin_semana', 'estancia_minima']);
        });
    }
};
