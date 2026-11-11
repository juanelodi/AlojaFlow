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
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;

/**
 * Por ahora no se factura en ningún país: de un pago sale un recibo (D-131). Se van las tablas, los XML y PDF emitidos, el módulo y el
 * nombre del permiso. La actualización hace antes su copia de la base.
 */
return new class () extends Migration {
    private const TABLAS = ['eventos_fiscales', 'facturas_reservas', 'facturas_impuestos', 'facturas_lineas', 'facturas', 'receptores_fiscales', 'series_facturacion', 'entidades_fiscales'];

    /**
     * Los roles lo apuntan por su id, pero lo que se le da o se le quita a una persona guarda el nombre: si no cambia ahí, quien lo tenía
     * quitado lo recuperaría con su rol (D-075). Si el nombre nuevo ya existe, por haber corrido antes el seeder, se juntan.
     */
    private function renombrarPermiso(string $viejo, string $nuevo): void
    {
        $idViejo = DB::table('permissions')->where('name', $viejo)->value('id');
        $idNuevo = DB::table('permissions')->where('name', $nuevo)->value('id');
        if ($idViejo !== null && $idNuevo !== null) {
            foreach (DB::table('role_has_permissions')->where('permission_id', $idViejo)->pluck('role_id') as $rol) {
                DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $idNuevo, 'role_id' => $rol]);
            }
            DB::table('permissions')->where('id', $idViejo)->delete();
        } else {
            DB::table('permissions')->where('name', $viejo)->update(['name' => $nuevo]);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (DB::table('accesos_usuario')->get(['id', 'permisos_agregados', 'permisos_quitados']) as $fila) {
            $cambios = [];
            foreach (['permisos_agregados', 'permisos_quitados'] as $columna) {
                $lista = is_string($fila->{$columna}) ? json_decode($fila->{$columna}, true) : null;
                if (is_array($lista) && in_array($viejo, $lista, true)) {
                    $cambios[$columna] = json_encode(array_values(array_unique(array_map(fn (string $p): string => $p === $viejo ? $nuevo : $p, $lista))));
                }
            }
            if ($cambios !== []) {
                DB::table('accesos_usuario')->where('id', $fila->id)->update($cambios);
            }
        }
    }

    public function up(): void
    {
        foreach (DB::table('archivos')->whereIn('clase', ['factura_xml', 'factura_pdf'])->pluck('ruta') as $ruta) {
            Storage::disk('local')->delete((string) $ruta);
        }
        DB::table('archivos')->whereIn('clase', ['factura_xml', 'factura_pdf'])->delete();

        Schema::disableForeignKeyConstraints();
        foreach (self::TABLAS as $tabla) {
            Schema::dropIfExists($tabla);
        }
        Schema::enableForeignKeyConstraints();

        $this->renombrarPermiso('facturacion.gestionar', 'cuentas.gestionar');

        foreach (['modulos.activos', 'modulos.conocidos'] as $clave) {
            $valor = DB::table('ajustes')->where('clave', $clave)->value('valor');
            $lista = is_string($valor) ? json_decode($valor, true) : null;
            if (is_array($lista)) {
                DB::table('ajustes')->where('clave', $clave)->update(['valor' => json_encode(array_values(array_diff($lista, ['facturacion'])))]);
            }
        }
    }

    /**
     * Las tablas vuelven vacías, como quedaron con las migraciones anteriores: lo emitido solo está en la copia previa.
     */
    public function down(): void
    {
        $this->renombrarPermiso('cuentas.gestionar', 'facturacion.gestionar');

        Schema::create('entidades_fiscales', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('propiedad_id')->constrained('propiedades')->cascadeOnDelete();
            $table->string('nombre', 200);
            $table->string('pais', 2);
            $table->string('identificador_fiscal', 30);
            $table->string('regimen_fiscal', 10)->nullable();
            $table->string('codigo_postal', 10)->nullable();
            $table->string('domicilio', 255)->nullable();
            $table->text('configuracion')->nullable();
            $table->string('modo', 20)->default('pruebas');
            $table->dateTime('creado_en')->nullable();
            $table->dateTime('actualizado_en')->nullable();
        });
        Schema::create('series_facturacion', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('entidad_fiscal_id')->constrained('entidades_fiscales')->cascadeOnDelete();
            $table->string('serie', 25);
            $table->string('tipo_documento', 20)->default('factura');
            $table->unsignedInteger('siguiente_numero')->default(1);
            $table->boolean('activa')->default(true);
            $table->dateTime('creado_en')->nullable();
            $table->dateTime('actualizado_en')->nullable();
            $table->unique(['entidad_fiscal_id', 'serie']);
        });
        Schema::create('receptores_fiscales', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('propiedad_id')->constrained('propiedades')->cascadeOnDelete();
            $table->foreignId('huesped_id')->nullable()->constrained('huespedes')->nullOnDelete();
            $table->string('pais', 2)->nullable();
            $table->string('identificador_fiscal', 30);
            $table->string('nombre', 200);
            $table->string('regimen_fiscal', 10)->nullable();
            $table->string('codigo_postal', 10)->nullable();
            $table->string('uso', 10)->nullable();
            $table->string('correo', 150)->nullable();
            $table->dateTime('creado_en')->nullable();
            $table->dateTime('actualizado_en')->nullable();
            $table->unique(['propiedad_id', 'identificador_fiscal']);
        });
        Schema::create('facturas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('propiedad_id')->constrained('propiedades')->restrictOnDelete();
            $table->foreignId('entidad_fiscal_id')->constrained('entidades_fiscales')->restrictOnDelete();
            $table->foreignId('serie_id')->constrained('series_facturacion')->restrictOnDelete();
            $table->foreignId('cuota_reserva_id')->nullable()->constrained('cuotas_reserva')->nullOnDelete();
            $table->string('serie', 25);
            $table->unsignedInteger('numero');
            $table->string('tipo', 20);
            $table->string('estado', 20);
            $table->json('emisor');
            $table->json('receptor');
            $table->char('moneda', 3);
            $table->bigInteger('subtotal');
            $table->bigInteger('descuento')->default(0);
            $table->bigInteger('impuestos');
            $table->bigInteger('total');
            $table->dateTime('emitida_en');
            $table->string('lugar_expedicion', 10)->nullable();
            $table->string('metodo_pago', 10)->nullable();
            $table->string('forma_pago', 10)->nullable();
            $table->string('uuid_fiscal', 40)->nullable()->unique();
            $table->foreignId('xml_archivo_id')->nullable()->constrained('archivos')->nullOnDelete();
            $table->foreignId('pdf_archivo_id')->nullable()->constrained('archivos')->nullOnDelete();
            $table->dateTime('cancelada_en')->nullable();
            $table->string('motivo_cancelacion', 255)->nullable();
            $table->dateTime('creado_en')->nullable();
            $table->dateTime('actualizado_en')->nullable();
            $table->mediumText('qr_timbrado')->nullable();
            $table->string('motivo_cancelacion_clave', 2)->nullable();
            $table->string('folio_sustitucion', 40)->nullable();
            $table->unique(['serie_id', 'numero']);
            $table->index(['propiedad_id', 'emitida_en']);
        });
        Schema::create('facturas_lineas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('factura_id')->constrained('facturas')->cascadeOnDelete();
            $table->unsignedSmallInteger('orden');
            $table->string('concepto', 255);
            $table->string('clave_producto', 20)->nullable();
            $table->string('clave_unidad', 10)->nullable();
            $table->decimal('cantidad', 20, 6)->default(1);
            $table->decimal('valor_unitario', 20, 6);
            $table->bigInteger('importe');
        });
        Schema::create('facturas_impuestos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('factura_linea_id')->constrained('facturas_lineas')->cascadeOnDelete();
            $table->string('nombre', 100);
            $table->string('codigo', 30)->nullable();
            $table->string('tipo', 20)->default('traslado');
            $table->bigInteger('base');
            $table->decimal('tasa', 7, 4);
            $table->bigInteger('importe');
        });
        Schema::create('facturas_reservas', function (Blueprint $table): void {
            $table->foreignId('factura_id')->constrained('facturas')->cascadeOnDelete();
            $table->foreignId('reserva_id')->constrained('reservas')->restrictOnDelete();
            $table->primary(['factura_id', 'reserva_id']);
        });
        Schema::create('eventos_fiscales', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('factura_id')->constrained('facturas')->cascadeOnDelete();
            $table->string('tipo', 30);
            $table->string('estado', 20);
            $table->json('respuesta')->nullable();
            $table->unsignedSmallInteger('intentos')->default(1);
            $table->dateTime('creado_en')->nullable();
            $table->dateTime('actualizado_en')->nullable();
        });
    }
};
