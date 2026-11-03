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
 * Historial de actualizaciones desde el panel (modelo-datos.md §4.1;
 * especificacion/instalacion-actualizaciones.md).
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('actualizaciones', function (Blueprint $table) {
            $table->id();
            $table->string('version_anterior', 20);
            $table->string('version_nueva', 20);
            $table->string('estado', 20);
            $table->boolean('reinstalar')->default(false);
            $table->string('paquete', 255);
            $table->char('paquete_sha256', 64);
            $table->json('resumen')->nullable();
            $table->text('detalle')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('iniciada_en')->nullable();
            $table->dateTime('terminada_en')->nullable();
            $table->dateTime('creado_en')->nullable();
            $table->dateTime('actualizado_en')->nullable();
            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('actualizaciones');
    }
};
