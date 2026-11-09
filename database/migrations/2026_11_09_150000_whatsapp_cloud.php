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
 * WhatsApp por la API de Meta (D-160): la plantilla aprobada que manda una automatización y, en el envío, lo que se le mandó.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('automatizaciones', function (Blueprint $table): void {
            $table->json('whatsapp')->nullable()->after('destino');
        });
        Schema::table('envios', function (Blueprint $table): void {
            $table->json('datos')->nullable()->after('cuerpo');
        });
    }

    public function down(): void
    {
        Schema::table('automatizaciones', fn (Blueprint $table) => $table->dropColumn('whatsapp'));
        Schema::table('envios', fn (Blueprint $table) => $table->dropColumn('datos'));
    }
};
