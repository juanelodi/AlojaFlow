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
 * Lo que devuelve el PAC para imprimir la factura y su cancelación (D-098), y el cliente de Stripe que hace falta para las transferencias SPEI (D-099).
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('facturas', function (Blueprint $table): void {
            $table->mediumText('qr_timbrado')->nullable();
            $table->string('motivo_cancelacion_clave', 2)->nullable();
            $table->string('folio_sustitucion', 40)->nullable();
        });

        Schema::table('huespedes', function (Blueprint $table): void {
            $table->string('stripe_cliente', 60)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('huespedes', function (Blueprint $table): void {
            $table->dropColumn('stripe_cliente');
        });
        Schema::table('facturas', function (Blueprint $table): void {
            $table->dropColumn(['qr_timbrado', 'motivo_cancelacion_clave', 'folio_sustitucion']);
        });
    }
};
