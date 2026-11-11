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
 * Unidades vinculadas (D-155): una unidad que se compone de otras, como la casa completa y sus cuartos. Ocupar una cierra las
 * otras. Un solo nivel: lo que tiene partes no es parte de nada.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('unidades_partes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unidad_id')->constrained('unidades')->cascadeOnDelete();
            $table->foreignId('parte_id')->constrained('unidades')->cascadeOnDelete();
            $table->unique(['unidad_id', 'parte_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unidades_partes');
    }
};
