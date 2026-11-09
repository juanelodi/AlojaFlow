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
 * Códigos de acceso (D-156): el de una reserva o el fijo de una unidad, como la clave de una cerradura o de una caja de llaves. Dato
 * propio, también en una subordinada, y cifrado: abre una puerta.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('codigos_acceso', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('reserva_id')->nullable()->unique()->constrained('reservas')->cascadeOnDelete();
            $table->foreignId('unidad_id')->nullable()->unique()->constrained('unidades')->cascadeOnDelete();
            $table->text('codigo');
            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('creado_en')->nullable();
            $table->timestamp('actualizado_en')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('codigos_acceso');
    }
};
