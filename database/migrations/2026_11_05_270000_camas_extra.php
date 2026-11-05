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

/*
 * Camas extra (D-125): cuántas personas más caben en una unidad, y un cargo que se cobra por cada una.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('unidades', function (Blueprint $table): void {
            $table->unsignedSmallInteger('camas_extra')->default(0)->after('capacidad');
        });
        Schema::table('conceptos_cargo', function (Blueprint $table): void {
            $table->boolean('por_cama_extra')->default(false)->after('solo_unidades');
        });
    }

    public function down(): void
    {
        Schema::table('conceptos_cargo', function (Blueprint $table): void {
            $table->dropColumn('por_cama_extra');
        });
        Schema::table('unidades', function (Blueprint $table): void {
            $table->dropColumn('camas_extra');
        });
    }
};
