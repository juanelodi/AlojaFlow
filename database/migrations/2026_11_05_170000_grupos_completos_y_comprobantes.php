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
 * Grupos completos: estado, precio, cuotas, lista por habitación, acceso del coordinador y mensajes (D-094 a D-096), y comprobantes de pago, de grupos y
 * de reservas del motor (D-097).
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('grupos', function (Blueprint $table): void {
            $table->string('estado', 20)->default('confirmado')->after('nombre');
            $table->string('color', 7)->nullable()->after('estado');
            $table->string('modo_precio', 20)->default('tarifa');
            $table->unsignedBigInteger('precio_total')->nullable();
            $table->json('precios_capacidad')->nullable();
            $table->unsignedSmallInteger('gratis_cada')->nullable();
            $table->decimal('descuento_porcentaje', 5, 2)->default(0);
            $table->unsignedBigInteger('extras')->default(0);
            $table->date('fecha_limite_lista')->nullable();
            $table->boolean('liberar_sin_responsable')->default(false);
            $table->boolean('acceso_activo')->default(false);
            $table->string('clave_hash')->nullable();
            $table->text('clave_cifrada')->nullable();
            $table->boolean('debe_cambiar_clave')->default(true);
            $table->unsignedTinyInteger('intentos_fallidos')->default(0);
            $table->dateTime('bloqueado_hasta')->nullable();
            $table->unsignedSmallInteger('dias_acceso_tras_salida')->default(7);
            $table->dateTime('ultimo_acceso_en')->nullable();
        });

        Schema::create('grupo_habitaciones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('grupo_id')->constrained('grupos')->cascadeOnDelete();
            $table->foreignId('reserva_id')->unique()->constrained('reservas')->cascadeOnDelete();
            $table->unsignedBigInteger('precio_noche')->nullable();
            $table->string('responsable', 150)->nullable();
            $table->string('telefono', 50)->nullable();
            $table->string('correo', 150)->nullable();
            $table->text('acompanantes')->nullable();
            $table->dateTime('creado_en')->nullable();
            $table->dateTime('actualizado_en')->nullable();
        });

        // Los grupos que ya existían, con una fila por reserva como los nuevos.
        $ahora = now('UTC');
        DB::table('reservas')->whereNotNull('grupo_id')->orderBy('id')->select(['id', 'grupo_id'])->chunk(500, function ($reservas) use ($ahora): void {
            DB::table('grupo_habitaciones')->insert($reservas->map(fn (object $r): array => [
                'grupo_id' => $r->grupo_id, 'reserva_id' => $r->id, 'creado_en' => $ahora, 'actualizado_en' => $ahora,
            ])->all());
        });

        Schema::create('cuotas_grupo', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('grupo_id')->constrained('grupos')->cascadeOnDelete();
            $table->string('concepto', 150);
            $table->unsignedBigInteger('importe');
            $table->date('vence_en');
            $table->dateTime('creado_en')->nullable();
            $table->dateTime('actualizado_en')->nullable();
            $table->index(['grupo_id', 'vence_en']);
        });

        Schema::create('comprobantes_pago', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('propiedad_id')->constrained('propiedades')->cascadeOnDelete();
            $table->foreignId('grupo_id')->nullable()->constrained('grupos')->cascadeOnDelete();
            $table->foreignId('reserva_id')->nullable()->constrained('reservas')->cascadeOnDelete();
            $table->unsignedBigInteger('importe');
            $table->string('moneda', 3);
            $table->date('fecha');
            $table->string('metodo', 20)->default('transferencia');
            $table->string('referencia', 100)->nullable();
            $table->foreignId('archivo_id')->nullable()->constrained('archivos')->nullOnDelete();
            $table->string('estado', 20)->default('por_confirmar');
            $table->string('motivo_rechazo', 255)->nullable();
            $table->foreignId('revisado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('revisado_en')->nullable();
            $table->dateTime('creado_en')->nullable();
            $table->dateTime('actualizado_en')->nullable();
            $table->index(['propiedad_id', 'estado']);
        });

        Schema::create('mensajes_grupo', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('grupo_id')->constrained('grupos')->cascadeOnDelete();
            $table->boolean('del_coordinador');
            $table->text('texto');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('leido_en')->nullable();
            $table->dateTime('creado_en')->nullable();
            $table->dateTime('actualizado_en')->nullable();
            $table->index(['grupo_id', 'leido_en']);
        });

        Schema::table('aceptaciones', function (Blueprint $table): void {
            $table->foreignId('grupo_id')->nullable()->after('reserva_id')->constrained('grupos')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('aceptaciones', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('grupo_id');
        });
        Schema::dropIfExists('mensajes_grupo');
        Schema::dropIfExists('comprobantes_pago');
        Schema::dropIfExists('cuotas_grupo');
        Schema::dropIfExists('grupo_habitaciones');
        Schema::table('grupos', function (Blueprint $table): void {
            $table->dropColumn([
                'estado', 'color', 'modo_precio', 'precio_total', 'precios_capacidad', 'gratis_cada', 'descuento_porcentaje', 'extras', 'fecha_limite_lista',
                'liberar_sin_responsable', 'acceso_activo', 'clave_hash', 'clave_cifrada', 'debe_cambiar_clave', 'intentos_fallidos', 'bloqueado_hasta',
                'dias_acceso_tras_salida', 'ultimo_acceso_en',
            ]);
        });
    }
};
