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
 * El texto que ve el huésped en el motor de reservas (D-042).
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('tarifas', function (Blueprint $table) {
            $table->text('descripcion_publica')->nullable()->after('estancia_minima');
            $table->boolean('visible_motor')->default(true)->after('descripcion_publica');
        });
    }

    public function down(): void
    {
        Schema::table('tarifas', function (Blueprint $table) {
            $table->dropColumn(['descripcion_publica', 'visible_motor']);
        });
    }
};
