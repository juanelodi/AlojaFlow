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
 * El soporte de AlojaFlow dentro de una instancia (D-166): quién es del soporte y hasta cuándo puede entrar (UTC).
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('es_soporte')->default(false)->after('es_superadministrador');
            $table->timestamp('soporte_hasta')->nullable()->after('es_soporte');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['es_soporte', 'soporte_hasta']));
    }
};
