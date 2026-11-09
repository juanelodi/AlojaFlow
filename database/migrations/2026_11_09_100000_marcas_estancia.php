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
 * Llegada y salida de una reserva de una propiedad subordinada (D-153): la reserva es de la principal y no
 * se escribe en ella; la marca es un dato propio, como el no-show (D-074).
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('marcas_estancia', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('reserva_id')->unique()->constrained('reservas')->cascadeOnDelete();
            $table->timestamp('entrada_en')->nullable();
            $table->foreignId('entrada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('salida_en')->nullable();
            $table->foreignId('salida_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('creado_en')->nullable();
            $table->timestamp('actualizado_en')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marcas_estancia');
    }
};
