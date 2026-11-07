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

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('canales', function (Blueprint $table): void {
            // Como se venía calculando: sobre el total.
            $table->string('comision_base', 20)->default('total_final')->after('comision_porcentaje');
        });
        Schema::create('canales_cargos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('canal_id')->constrained('canales')->cascadeOnDelete();
            $table->string('nombre', 100);
            $table->string('clase', 30);
            $table->string('calculo', 20)->default('porcentaje');
            // Un porcentaje, o un importe en la moneda de la propiedad.
            $table->decimal('valor', 12, 4)->default(0);
            $table->string('base', 20)->default('total_final');
            $table->boolean('activo')->default(true);
            $table->dateTime('creado_en')->nullable();
            $table->dateTime('actualizado_en')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('canales_cargos');
        Schema::table('canales', function (Blueprint $table): void {
            $table->dropColumn('comision_base');
        });
    }
};
