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
 * Ajustes de la instalación que se cambian desde el panel (modelo-datos.md §4.1): correo, copias de
 * seguridad, programador… Los secretos van cifrados dentro de «valor».
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('ajustes', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 100)->unique();
            $table->json('valor')->nullable();
            $table->dateTime('creado_en')->nullable();
            $table->dateTime('actualizado_en')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ajustes');
    }
};
