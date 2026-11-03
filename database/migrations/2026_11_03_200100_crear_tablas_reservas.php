<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Unidades, huéspedes y reservas: la proyección de la fase 1, que en la fase 2 tendrá también
 * filas nativas. Ver docs/especificacion/modelo-datos.md §2, §4.4 y §4.5.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('unidades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('propiedad_id')->constrained('propiedades')->cascadeOnDelete();
            $table->string('codigo', 100);
            $table->string('nombre', 100);
            $table->string('piso', 50)->nullable();
            $table->string('edificio', 50)->nullable();
            $table->unsignedSmallInteger('capacidad')->default(0);
            $table->integer('orden')->default(0);
            $table->text('notas')->nullable();
            $table->boolean('activa')->default(true);
            $this->columnasOrigen($table);
            $table->dateTime('creado_en')->nullable();
            $table->dateTime('actualizado_en')->nullable();
            $table->unique(['propiedad_id', 'codigo']);
        });

        Schema::create('huespedes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('propiedad_id')->constrained('propiedades')->cascadeOnDelete();
            $table->string('nombre', 100)->nullable();
            $table->string('apellido1', 100)->nullable();
            $table->string('apellido2', 100)->nullable();
            $table->string('apodo', 100)->nullable();
            $table->string('tratamiento', 30)->nullable();
            $table->string('sexo', 10)->nullable();
            $table->string('idioma', 12)->nullable();
            $table->date('fecha_nacimiento')->nullable();
            $table->string('lugar_nacimiento', 255)->nullable();
            $table->string('pais_nacimiento_texto', 100)->nullable();
            $table->char('nacionalidad', 2)->nullable();
            $table->string('nacionalidad_texto', 100)->nullable();
            $table->string('documento_tipo_texto', 100)->nullable();
            $table->text('documento_numero')->nullable();
            $table->char('documento_numero_hmac', 64)->nullable()->index();
            $table->date('documento_vencimiento')->nullable();
            $table->string('documento_expedicion', 255)->nullable();
            $table->string('documento_pais_texto', 100)->nullable();
            $table->string('domicilio_calle', 150)->nullable();
            $table->string('domicilio_numero', 30)->nullable();
            $table->string('domicilio_ciudad', 100)->nullable();
            $table->string('domicilio_region', 100)->nullable();
            $table->string('domicilio_codigo_postal', 30)->nullable();
            $table->char('domicilio_pais', 2)->nullable();
            $table->string('domicilio_pais_texto', 100)->nullable();
            $table->string('id_fiscal_personal', 50)->nullable();
            $table->string('id_fiscal_empresa', 50)->nullable();
            $table->text('notas')->nullable();
            $table->dateTime('creado_en_origen')->nullable();
            $table->dateTime('eliminado_en_principal_en')->nullable();
            $this->columnasOrigen($table);
            $table->dateTime('creado_en')->nullable();
            $table->dateTime('actualizado_en')->nullable();
            $table->index(['propiedad_id', 'apellido1', 'nombre']);
        });

        Schema::create('contactos_huesped', function (Blueprint $table) {
            $table->id();
            $table->foreignId('huesped_id')->constrained('huespedes')->cascadeOnDelete();
            $table->string('tipo', 10);
            $table->string('valor', 255);
            $table->string('valor_normalizado', 255)->index();
            $table->unsignedTinyInteger('orden')->default(0);
        });

        Schema::create('campos_huesped', function (Blueprint $table) {
            $table->id();
            $table->foreignId('huesped_id')->constrained('huespedes')->cascadeOnDelete();
            $table->string('campo', 100);
            $table->text('valor')->nullable();
            $table->index(['huesped_id', 'campo']);
        });

        Schema::create('reservas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('propiedad_id')->constrained('propiedades')->cascadeOnDelete();
            $table->foreignId('unidad_id')->constrained('unidades')->cascadeOnDelete();
            $table->foreignId('titular_id')->nullable()->constrained('huespedes')->nullOnDelete();
            $table->string('tipo', 20)->default('reserva');
            $table->string('estado', 20);
            $table->string('numero', 20);
            $table->char('codigo_publico', 10)->unique();
            $table->date('llegada');
            $table->date('salida');
            $table->smallInteger('noches')->storedAs('DATEDIFF(`salida`, `llegada`)');
            $table->unsignedSmallInteger('personas')->default(0);
            $table->unsignedSmallInteger('adultos')->nullable();
            $table->unsignedSmallInteger('ninos')->nullable();
            $table->string('origen_texto', 100)->nullable();
            $table->string('grupo_codigo', 30)->nullable();
            $table->string('tarifa_nombre', 100)->nullable();
            $table->char('moneda', 3);
            $table->bigInteger('total')->default(0);
            $table->bigInteger('pagado')->default(0);
            $table->bigInteger('pendiente')->storedAs('`total` - `pagado`');
            $table->bigInteger('deposito')->default(0);
            $table->bigInteger('descuento')->default(0);
            $table->bigInteger('comision')->default(0);
            $table->decimal('impuestos_porcentaje', 7, 4)->nullable();
            $table->dateTime('entrada_registrada_en')->nullable();
            $table->dateTime('salida_registrada_en')->nullable();
            $table->text('comentario')->nullable();
            $table->dateTime('creada_en_origen')->nullable();
            $table->dateTime('modificada_en_origen')->nullable();
            $table->dateTime('eliminada_en_principal_en')->nullable();
            $this->columnasOrigen($table);
            $table->dateTime('creado_en')->nullable();
            $table->dateTime('actualizado_en')->nullable();
            $table->index(['propiedad_id', 'llegada']);
            $table->index(['propiedad_id', 'salida']);
        });
        DB::statement('ALTER TABLE reservas ADD CONSTRAINT reservas_salida_despues_de_llegada CHECK (salida > llegada)');

        // Una fila por noche ocupada. El índice único (unidad, fecha, exclusiva) impide la doble
        // reserva de las filas con exclusiva = 1 (fase 2) y admite varias con NULL (fase 1). D-019.
        Schema::create('reservas_noches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reserva_id')->constrained('reservas')->cascadeOnDelete();
            $table->foreignId('propiedad_id')->constrained('propiedades')->cascadeOnDelete();
            $table->foreignId('unidad_id')->constrained('unidades')->cascadeOnDelete();
            $table->date('fecha');
            $table->bigInteger('importe')->default(0);
            $table->unsignedTinyInteger('exclusiva')->nullable();
            $table->unique(['unidad_id', 'fecha', 'exclusiva']);
            $table->index(['propiedad_id', 'fecha']);
        });
        DB::statement('ALTER TABLE reservas_noches ADD CONSTRAINT reservas_noches_exclusiva_valida CHECK (exclusiva = 1 OR exclusiva IS NULL)');

        Schema::create('reservas_huespedes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reserva_id')->constrained('reservas')->cascadeOnDelete();
            $table->foreignId('huesped_id')->constrained('huespedes')->cascadeOnDelete();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->string('parentesco_texto', 70)->nullable();
            $table->unique(['reserva_id', 'huesped_id']);
        });

        Schema::create('eventos_reserva', function (Blueprint $table) {
            $table->id();
            $table->foreignId('propiedad_id')->constrained('propiedades')->cascadeOnDelete();
            $table->foreignId('reserva_id')->constrained('reservas')->cascadeOnDelete();
            $table->string('tipo', 20);
            $table->json('datos')->nullable();
            $table->dateTime('ocurrido_en');
            $table->dateTime('procesado_en')->nullable();
            $table->index(['procesado_en', 'id']);
            $table->index(['propiedad_id', 'ocurrido_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eventos_reserva');
        Schema::dropIfExists('reservas_huespedes');
        Schema::dropIfExists('reservas_noches');
        Schema::dropIfExists('reservas');
        Schema::dropIfExists('campos_huesped');
        Schema::dropIfExists('contactos_huesped');
        Schema::dropIfExists('huespedes');
        Schema::dropIfExists('unidades');
    }

    /**
     * Columnas que indican de dónde vino una fila y si hoy la controla la principal (§2).
     */
    private function columnasOrigen(Blueprint $table): void
    {
        $table->string('origen', 20)->default('alojaflow');
        $table->boolean('sincronizada')->default(false);
        $table->foreignId('principal_id')->nullable()->constrained('principales')->nullOnDelete();
        $table->string('clave_origen', 120)->nullable();
        $table->string('clave_origen_historica', 120)->nullable();
        $table->char('huella_identidad', 64)->nullable();
        $table->char('huella_datos', 64)->nullable();
        $table->foreignId('ejecucion_id')->nullable()->constrained('ejecuciones_sincronizacion')->nullOnDelete();
        $table->unique(['principal_id', 'clave_origen']);
    }
};
