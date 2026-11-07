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

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('omisiones', function (Blueprint $table): void {
            $table->boolean('en_todo')->default(false)->after('valor');
            $table->boolean('en_hoy')->default(false)->after('en_todo');
            $table->boolean('en_calendario')->default(false)->after('en_hoy');
            $table->boolean('en_limpieza')->default(false)->after('en_calendario');
        });

        // Quien ya omitió a alguien del aviso esperaba no verlo en la jornada (D-144).
        DB::table('omisiones')->where('en_aviso', true)->update(['en_hoy' => true]);
    }

    public function down(): void
    {
        Schema::table('omisiones', function (Blueprint $table): void {
            $table->dropColumn(['en_todo', 'en_hoy', 'en_calendario', 'en_limpieza']);
        });
    }
};
