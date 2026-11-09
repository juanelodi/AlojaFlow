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
 * Un pago recibido en la segunda moneda (D-157): cuánto se recibió, en qué moneda y a qué tipo de cambio. El importe de siempre
 * sigue en la moneda de la reserva, que es lo que suma.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('pagos', function (Blueprint $table): void {
            $table->char('moneda_recibida', 3)->nullable()->after('moneda');
            $table->bigInteger('importe_recibido')->nullable()->after('moneda_recibida');
            $table->decimal('tipo_cambio', 16, 6)->nullable()->after('importe_recibido');
        });
    }

    public function down(): void
    {
        Schema::table('pagos', function (Blueprint $table): void {
            $table->dropColumn(['moneda_recibida', 'importe_recibido', 'tipo_cambio']);
        });
    }
};
