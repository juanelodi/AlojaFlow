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

/*
 * Cuándo se cobra un cargo del catálogo (D-121). Todo vacío: siempre, como hasta ahora.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('conceptos_cargo', function (Blueprint $table): void {
            $table->json('dias_semana')->nullable()->after('cupo_diario');
            $table->unsignedSmallInteger('cada_noches')->nullable()->after('dias_semana');
            $table->date('desde')->nullable()->after('cada_noches');
            $table->date('hasta')->nullable()->after('desde');
            $table->unsignedSmallInteger('noches_minimas')->nullable()->after('hasta');
            $table->json('solo_tarifas')->nullable()->after('noches_minimas');
            $table->json('solo_unidades')->nullable()->after('solo_tarifas');
        });
    }

    public function down(): void
    {
        Schema::table('conceptos_cargo', function (Blueprint $table): void {
            $table->dropColumn(['dias_semana', 'cada_noches', 'desde', 'hasta', 'noches_minimas', 'solo_tarifas', 'solo_unidades']);
        });
    }
};
