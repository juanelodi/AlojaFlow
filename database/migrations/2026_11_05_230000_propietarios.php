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

/*
 * Quien administra propiedades de terceros: dueños con su comisión, gastos por dueño, estados de cuenta, portal y estancias del dueño (D-114 a D-116).
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('propietarios', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('propiedad_id')->constrained('propiedades')->cascadeOnDelete();
            $table->string('nombre', 200);
            $table->string('correo', 150)->nullable();
            $table->string('telefono', 50)->nullable();
            $table->string('identificador_fiscal', 30)->nullable();
            $table->string('idioma', 5)->nullable();
            $table->decimal('comision_porcentaje', 5, 2)->default(0);
            $table->boolean('comision_sobre_neto')->default(true);
            $table->bigInteger('cargo_fijo')->default(0);
            $table->boolean('puede_bloquear')->default(true);
            $table->text('notas')->nullable();
            $table->boolean('activo')->default(true);
            // Su acceso al portal, igual que el del coordinador de un grupo.
            $table->boolean('acceso_activo')->default(false);
            $table->string('clave_hash')->nullable();
            $table->text('clave_cifrada')->nullable();
            $table->boolean('debe_cambiar_clave')->default(false);
            $table->unsignedSmallInteger('intentos_fallidos')->default(0);
            $table->dateTime('bloqueado_hasta')->nullable();
            $table->dateTime('ultimo_acceso_en')->nullable();
            $table->dateTime('creado_en')->nullable();
            $table->dateTime('actualizado_en')->nullable();
            $table->unique(['propiedad_id', 'nombre']);
        });

        Schema::table('unidades', function (Blueprint $table): void {
            $table->foreignId('propietario_id')->nullable()->after('tarifa_id')->constrained('propietarios')->nullOnDelete();
            $table->decimal('comision_propietario', 5, 2)->nullable()->after('propietario_id');
        });

        Schema::table('conceptos_cargo', function (Blueprint $table): void {
            $table->boolean('para_administrador')->default(false)->after('cupo_diario');
        });

        Schema::table('movimientos', function (Blueprint $table): void {
            $table->foreignId('propietario_id')->nullable()->after('caja_id')->constrained('propietarios')->nullOnDelete();
            $table->foreignId('unidad_id')->nullable()->after('propietario_id')->constrained('unidades')->nullOnDelete();
            $table->foreignId('comprobante_archivo_id')->nullable()->after('unidad_id')->constrained('archivos')->nullOnDelete();
            $table->index(['propietario_id', 'fecha']);
        });

        // Una estancia del dueño es un bloqueo que dice de quién es.
        Schema::table('reservas', function (Blueprint $table): void {
            $table->foreignId('propietario_id')->nullable()->after('empresa_id')->constrained('propietarios')->nullOnDelete();
        });

        Schema::create('estados_cuenta', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('propiedad_id')->constrained('propiedades')->cascadeOnDelete();
            $table->foreignId('propietario_id')->constrained('propietarios')->cascadeOnDelete();
            $table->date('desde');
            $table->date('hasta');
            $table->string('fase', 10)->default('borrador');
            $table->string('moneda', 3);
            $table->bigInteger('ingresos')->default(0);
            $table->bigInteger('comision_portales')->default(0);
            $table->bigInteger('comision')->default(0);
            $table->bigInteger('gastos')->default(0);
            $table->bigInteger('cargo_fijo')->default(0);
            $table->bigInteger('neto')->default(0);
            $table->json('detalle')->nullable();
            $table->dateTime('enviado_en')->nullable();
            $table->date('pagado_en')->nullable();
            $table->string('referencia_pago', 100)->nullable();
            $table->dateTime('creado_en')->nullable();
            $table->dateTime('actualizado_en')->nullable();
            $table->unique(['propietario_id', 'desde']);
            $table->index(['propiedad_id', 'desde']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estados_cuenta');
        Schema::table('reservas', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('propietario_id');
        });
        Schema::table('movimientos', function (Blueprint $table): void {
            $table->dropIndex(['propietario_id', 'fecha']);
            $table->dropConstrainedForeignId('comprobante_archivo_id');
            $table->dropConstrainedForeignId('unidad_id');
            $table->dropConstrainedForeignId('propietario_id');
        });
        Schema::table('conceptos_cargo', function (Blueprint $table): void {
            $table->dropColumn('para_administrador');
        });
        Schema::table('unidades', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('propietario_id');
            $table->dropColumn('comision_propietario');
        });
        Schema::dropIfExists('propietarios');
    }
};
