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
 * Varias unidades en una sola reserva del motor (D-158): una reserva por unidad, como siempre, y las demás apuntan a la primera,
 * que es la que se paga por todas.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('reservas', function (Blueprint $table): void {
            $table->foreignId('reserva_principal_id')->nullable()->after('grupo_id')->constrained('reservas')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('reservas', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('reserva_principal_id');
        });
    }
};
