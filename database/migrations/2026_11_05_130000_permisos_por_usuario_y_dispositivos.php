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
 * Ajustes y límites por usuario, quién creó cada reserva y dispositivos recordados (D-075). Sin ningún ajuste, cada quien
 * sigue pudiendo lo mismo que hoy.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('accesos_usuario', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('propiedad_id')->constrained('propiedades')->cascadeOnDelete();
            $table->json('permisos_agregados')->nullable();
            $table->json('permisos_quitados')->nullable();
            $table->boolean('solo_propias')->default(false);
            $table->boolean('sin_iniciadas')->default(false);
            $table->unsignedSmallInteger('horas_modificar')->nullable();
            $table->unsignedSmallInteger('horas_cancelar')->nullable();
            $table->dateTime('creado_en')->nullable();
            $table->dateTime('actualizado_en')->nullable();
            $table->unique(['user_id', 'propiedad_id']);
        });

        Schema::table('reservas', function (Blueprint $table): void {
            $table->foreignId('creada_por')->nullable()->after('comentario')->constrained('users')->nullOnDelete();
        });
        Schema::table('huespedes', function (Blueprint $table): void {
            $table->foreignId('creado_por')->nullable()->after('notas')->constrained('users')->nullOnDelete();
        });

        Schema::create('dispositivos_recordados', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->char('selector', 18)->unique();
            $table->char('validador_hash', 64);
            $table->char('validador_anterior_hash', 64)->nullable();
            $table->dateTime('rotado_en')->nullable();
            $table->char('huella_contrasena', 64);
            $table->string('agente', 255)->nullable();
            $table->string('ip', 45)->nullable();
            $table->dateTime('ultimo_uso_en')->nullable();
            $table->dateTime('expira_en');
            $table->dateTime('creado_en')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispositivos_recordados');
        Schema::table('huespedes', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('creado_por');
        });
        Schema::table('reservas', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('creada_por');
        });
        Schema::dropIfExists('accesos_usuario');
    }
};
