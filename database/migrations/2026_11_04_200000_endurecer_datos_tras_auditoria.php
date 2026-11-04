<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lo que dejó la auditoría de noviembre de 2026: datos personales y tokens que estaban en claro.
 */
return new class () extends Migration {
    public function up(): void
    {
        // El espejo se vuelve a llenar en la siguiente sincronización; se vacía para que no quede nada
        // en claro y porque cambiar la columna de una tabla grande en un hosting compartido tarda.
        DB::table('hd_filas')->truncate();
        Schema::table('hd_filas', function (Blueprint $table) {
            $table->longText('datos')->change();
        });

        Schema::table('webhooks', function (Blueprint $table) {
            $table->text('url')->change();
        });
        foreach (DB::table('webhooks')->get(['id', 'url']) as $webhook) {
            DB::table('webhooks')->where('id', $webhook->id)->update(['url' => Crypt::encryptString((string) $webhook->url)]);
        }

        // De lo ya enviado queda solo el sitio: la dirección completa suele llevar un token.
        DB::table('envios')->where('canal', 'webhook')->orderBy('id')->select(['id', 'destino'])->chunkById(500, function ($envios): void {
            foreach ($envios as $envio) {
                DB::table('envios')->where('id', $envio->id)->update(['destino' => (string) parse_url((string) $envio->destino, PHP_URL_HOST)]);
            }
        });

        // Sin llave primaria, una tabla no se puede replicar en muchos servidores de MySQL.
        Schema::table('facturas_reservas', function (Blueprint $table) {
            $table->primary(['factura_id', 'reserva_id']);
        });
    }

    public function down(): void
    {
        Schema::table('facturas_reservas', function (Blueprint $table) {
            $table->dropPrimary();
        });
        foreach (DB::table('webhooks')->get(['id', 'url']) as $webhook) {
            DB::table('webhooks')->where('id', $webhook->id)->update(['url' => Crypt::decryptString((string) $webhook->url)]);
        }
        Schema::table('webhooks', function (Blueprint $table) {
            $table->string('url', 500)->change();
        });
        DB::table('hd_filas')->truncate();
        Schema::table('hd_filas', function (Blueprint $table) {
            $table->json('datos')->change();
        });
    }
};
