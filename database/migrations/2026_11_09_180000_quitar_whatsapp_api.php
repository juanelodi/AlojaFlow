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

/**
 * WhatsApp por la API de Meta ya no forma parte de AlojaFlow (D-171, retira D-160). Se borra lo que guardó cada
 * propiedad (con el token cifrado) y las automatizaciones que mandaban plantillas; los envíos hechos se quedan en el
 * historial, y lo que faltaba por mandar queda omitido. Se puede repetir sin daño.
 */
return new class () extends Migration {
    public function up(): void
    {
        DB::table('ajustes')->where('clave', 'like', 'whatsapp.%')->delete();
        // Sus envíos se quedan: la llave foránea los deja sin automatización.
        DB::table('automatizaciones')->where('accion', 'whatsapp_huesped')->delete();
        DB::table('envios')->where('canal', 'whatsapp_api')->whereIn('estado', ['pendiente', 'fallido'])
            ->update(['estado' => 'omitido', 'ultimo_error' => __('mensajes.whatsapp_api_retirada')]);

        if (Schema::hasColumn('automatizaciones', 'whatsapp')) {
            Schema::table('automatizaciones', fn (Blueprint $table) => $table->dropColumn('whatsapp'));
        }
        if (Schema::hasColumn('envios', 'datos')) {
            Schema::table('envios', fn (Blueprint $table) => $table->dropColumn('datos'));
        }
    }

    public function down(): void
    {
        // Las columnas vuelven vacías: lo borrado no se recupera.
        if (! Schema::hasColumn('automatizaciones', 'whatsapp')) {
            Schema::table('automatizaciones', fn (Blueprint $table) => $table->json('whatsapp')->nullable()->after('destino'));
        }
        if (! Schema::hasColumn('envios', 'datos')) {
            Schema::table('envios', fn (Blueprint $table) => $table->json('datos')->nullable()->after('cuerpo'));
        }
    }
};
