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
 * Políticas, no-show y facturación (modelo-datos.md §4.4 y §4.10).
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('politicas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('propiedad_id')->constrained('propiedades')->cascadeOnDelete();
            $table->string('nombre', 100);
            $table->unsignedSmallInteger('dias_cancelacion_gratuita')->default(0);
            $table->decimal('penalizacion_porcentaje', 5, 2)->default(0);
            $table->decimal('no_show_porcentaje', 5, 2)->default(0);
            $table->decimal('deposito_porcentaje', 5, 2)->default(0);
            $table->unsignedSmallInteger('deposito_plazo_dias')->default(0);
            $table->text('texto')->nullable();
            $table->boolean('predeterminada')->default(false);
            $table->dateTime('creado_en')->nullable();
            $table->dateTime('actualizado_en')->nullable();
        });
        Schema::table('canales', function (Blueprint $table) {
            $table->foreignId('politica_id')->nullable()->after('comision_porcentaje')->constrained('politicas')->nullOnDelete();
        });

        Schema::create('marcas_no_show', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reserva_id')->unique()->constrained('reservas')->cascadeOnDelete();
            $table->foreignId('marcada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->bigInteger('penalizacion')->default(0);
            $table->char('moneda', 3);
            $table->text('nota')->nullable();
            $table->dateTime('creado_en')->nullable();
            $table->dateTime('actualizado_en')->nullable();
        });

        Schema::create('entidades_fiscales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('propiedad_id')->constrained('propiedades')->cascadeOnDelete();
            $table->string('nombre', 200);
            $table->string('pais', 2);
            $table->string('identificador_fiscal', 30);
            $table->string('regimen_fiscal', 10)->nullable();
            $table->string('codigo_postal', 10)->nullable();
            $table->string('domicilio', 255)->nullable();
            // Cifrada: credenciales del PAC y certificados.
            $table->text('configuracion')->nullable();
            $table->string('modo', 20)->default('pruebas');
            $table->dateTime('creado_en')->nullable();
            $table->dateTime('actualizado_en')->nullable();
        });

        Schema::create('series_facturacion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entidad_fiscal_id')->constrained('entidades_fiscales')->cascadeOnDelete();
            $table->string('serie', 25);
            $table->string('tipo_documento', 20)->default('factura');
            $table->unsignedInteger('siguiente_numero')->default(1);
            $table->boolean('activa')->default(true);
            $table->dateTime('creado_en')->nullable();
            $table->dateTime('actualizado_en')->nullable();
            $table->unique(['entidad_fiscal_id', 'serie']);
        });

        Schema::create('receptores_fiscales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('propiedad_id')->constrained('propiedades')->cascadeOnDelete();
            $table->foreignId('huesped_id')->nullable()->constrained('huespedes')->nullOnDelete();
            $table->string('pais', 2)->nullable();
            $table->string('identificador_fiscal', 30);
            $table->string('nombre', 200);
            $table->string('regimen_fiscal', 10)->nullable();
            $table->string('codigo_postal', 10)->nullable();
            $table->string('uso', 10)->nullable();
            $table->string('correo', 150)->nullable();
            $table->dateTime('creado_en')->nullable();
            $table->dateTime('actualizado_en')->nullable();
            $table->unique(['propiedad_id', 'identificador_fiscal']);
        });

        Schema::create('facturas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('propiedad_id')->constrained('propiedades')->restrictOnDelete();
            $table->foreignId('entidad_fiscal_id')->constrained('entidades_fiscales')->restrictOnDelete();
            $table->foreignId('serie_id')->constrained('series_facturacion')->restrictOnDelete();
            $table->string('serie', 25);
            $table->unsignedInteger('numero');
            $table->string('tipo', 20);
            $table->string('estado', 20);
            $table->json('emisor');
            $table->json('receptor');
            $table->char('moneda', 3);
            $table->bigInteger('subtotal');
            $table->bigInteger('descuento')->default(0);
            $table->bigInteger('impuestos');
            $table->bigInteger('total');
            $table->dateTime('emitida_en');
            $table->string('lugar_expedicion', 10)->nullable();
            $table->string('metodo_pago', 10)->nullable();
            $table->string('forma_pago', 10)->nullable();
            $table->string('uuid_fiscal', 40)->nullable()->unique();
            $table->foreignId('xml_archivo_id')->nullable()->constrained('archivos')->nullOnDelete();
            $table->foreignId('pdf_archivo_id')->nullable()->constrained('archivos')->nullOnDelete();
            $table->dateTime('cancelada_en')->nullable();
            $table->string('motivo_cancelacion', 255)->nullable();
            $table->dateTime('creado_en')->nullable();
            $table->dateTime('actualizado_en')->nullable();
            $table->unique(['serie_id', 'numero']);
            $table->index(['propiedad_id', 'emitida_en']);
        });

        Schema::create('facturas_lineas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('factura_id')->constrained('facturas')->cascadeOnDelete();
            $table->unsignedSmallInteger('orden');
            $table->string('concepto', 255);
            $table->string('clave_producto', 20)->nullable();
            $table->string('clave_unidad', 10)->nullable();
            $table->decimal('cantidad', 20, 6)->default(1);
            $table->decimal('valor_unitario', 20, 6);
            $table->bigInteger('importe');
        });

        Schema::create('facturas_impuestos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('factura_linea_id')->constrained('facturas_lineas')->cascadeOnDelete();
            $table->string('nombre', 100);
            $table->string('codigo', 30)->nullable();
            $table->string('tipo', 20)->default('traslado');
            $table->bigInteger('base');
            $table->decimal('tasa', 7, 4);
            $table->bigInteger('importe');
        });

        Schema::create('facturas_reservas', function (Blueprint $table) {
            $table->foreignId('factura_id')->constrained('facturas')->cascadeOnDelete();
            $table->foreignId('reserva_id')->constrained('reservas')->restrictOnDelete();
            $table->unique(['factura_id', 'reserva_id']);
        });

        Schema::create('eventos_fiscales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('factura_id')->constrained('facturas')->cascadeOnDelete();
            $table->string('tipo', 30);
            $table->string('estado', 20);
            $table->json('respuesta')->nullable();
            $table->unsignedSmallInteger('intentos')->default(1);
            $table->dateTime('creado_en')->nullable();
            $table->dateTime('actualizado_en')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eventos_fiscales');
        Schema::dropIfExists('facturas_reservas');
        Schema::dropIfExists('facturas_impuestos');
        Schema::dropIfExists('facturas_lineas');
        Schema::dropIfExists('facturas');
        Schema::dropIfExists('receptores_fiscales');
        Schema::dropIfExists('series_facturacion');
        Schema::dropIfExists('entidades_fiscales');
        Schema::dropIfExists('marcas_no_show');
        Schema::table('canales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('politica_id');
        });
        Schema::dropIfExists('politicas');
    }
};
