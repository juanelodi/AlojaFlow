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
 * Subordinar otra instalación de AlojaFlow (D-073): lo que comparte la principal y de qué copia la subordinada.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('conexiones_compartidas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('propiedad_id')->constrained('propiedades')->cascadeOnDelete();
            $table->string('nombre', 100);
            // Solo la huella: el código lo tiene la subordinada, y aquí no se puede volver a ver.
            $table->char('token_hash', 64)->unique();
            $table->boolean('incluir_documentos')->default(false);
            $table->dateTime('ultimo_uso_en')->nullable();
            $table->dateTime('revocada_en')->nullable();
            $table->dateTime('creado_en')->nullable();
            $table->dateTime('actualizado_en')->nullable();
        });

        Schema::table('principales', function (Blueprint $table): void {
            $table->string('tipo', 20)->default('hoteldruid')->after('propiedad_id');
            $table->char('revision_origen', 64)->nullable()->after('ultima_correcta_en');
            $table->dateTime('ultima_bajada_en')->nullable()->after('revision_origen');
        });
    }

    public function down(): void
    {
        Schema::table('principales', function (Blueprint $table): void {
            $table->dropColumn(['tipo', 'revision_origen', 'ultima_bajada_en']);
        });
        Schema::dropIfExists('conexiones_compartidas');
    }
};
