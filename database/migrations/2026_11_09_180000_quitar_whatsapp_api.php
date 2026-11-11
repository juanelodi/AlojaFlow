<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
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
        // La razón en el idioma de cada envío, como las demás de la lista.
        $porMandar = fn () => DB::table('envios')->where('canal', 'whatsapp_api')->whereIn('estado', ['pendiente', 'fallido']);
        foreach ($porMandar()->distinct()->pluck('idioma') as $idioma) {
            $texto = __('mensajes.whatsapp_api_retirada', [], in_array($idioma, (array) config('alojaflow.idiomas'), true) ? $idioma : null);
            $porMandar()->where(fn ($query) => $idioma === null ? $query->whereNull('idioma') : $query->where('idioma', $idioma))
                ->update(['estado' => 'omitido', 'ultimo_error' => $texto]);
        }

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
