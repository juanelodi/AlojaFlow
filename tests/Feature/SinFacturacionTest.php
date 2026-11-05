<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

use App\Enums\EstadoReserva;
use App\Enums\MetodoPago;
use App\Enums\ModoPropiedad;
use App\Filament\Resources\Reservas\Pages\ViewReserva;
use App\Models\AccesoUsuario;
use App\Models\Huesped;
use App\Models\Propiedad;
use App\Models\Unidad;
use App\Models\User;
use App\Paises\Mexico;
use App\Reservas\GestorReservas;
use App\Seguridad\Acceso;
use App\Support\Ajustes;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesYPermisosSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

afterEach(fn () => Ajustes::olvidar());

beforeEach(function (): void {
    $this->seed(RolesYPermisosSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('app'));
    $this->propiedad = Propiedad::query()->create(['nombre' => 'Posada', 'codigo' => 'posada', 'modo' => ModoPropiedad::Nativa, 'zona_horaria' => 'America/Mexico_City', 'moneda' => 'MXN', 'pais' => 'MX', 'idioma' => 'es', 'paquete_pais' => 'mexico']);
    $this->unidad = Unidad::query()->create(['propiedad_id' => $this->propiedad->id, 'codigo' => '101', 'nombre' => 'Cuarto 101', 'capacidad' => 2]);
    $this->huesped = Huesped::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Ana', 'apellido1' => 'Ejemplo']);
    $hoy = CarbonImmutable::parse($this->propiedad->hoy()->format('Y-m-d'));
    $this->reserva = app(GestorReservas::class)->crear($this->propiedad, [
        'unidad_id' => $this->unidad->id, 'titular_id' => $this->huesped->id, 'personas' => 2, 'total' => 200000,
        'llegada' => $hoy->addDays(5)->format('Y-m-d'), 'salida' => $hoy->addDays(7)->format('Y-m-d'), 'estado' => EstadoReserva::Confirmada->value,
    ]);
    $this->admin = User::factory()->create(['es_administrador' => true, 'activo' => true, 'idioma' => 'es']);
});

it('de un pago sale un recibo con su número, el importe y lo que falta', function (): void {
    $pago = app(GestorReservas::class)->registrarPago($this->reserva, 50000, MetodoPago::Efectivo, CarbonImmutable::now(), 'R-1');
    $reserva = $this->reserva->refresh();

    $html = view('documentos.recibo', ['reserva' => $reserva, 'propiedad' => $reserva->propiedad, 'pago' => $pago])->render();

    expect($html)->toContain(__('documentos.recibo_numero'))
        ->toContain('Ana Ejemplo')
        ->toContain(App\Support\Formato::dinero(50000, 'MXN'))
        ->toContain(App\Support\Formato::dinero(150000, 'MXN'))
        ->toContain(__('documentos.no_fiscal'));
});

it('ya no hay pantallas ni botón para facturar', function (string $ruta): void {
    $this->actingAs($this->admin)->get($ruta)->assertNotFound();
})->with(['/facturas', '/datos-fiscales', '/entidades-fiscales']);

it('la reserva ya no ofrece facturar', function (): void {
    Livewire::actingAs($this->admin)->test(ViewReserva::class, ['record' => $this->reserva->getKey()])
        ->assertActionDoesNotExist('facturar');
});

it('el contador conserva los estados de cuenta y el calendario de cobros', function (): void {
    $contador = User::factory()->create(['activo' => true, 'idioma' => 'es']);
    Acceso::asignarRoles($contador, [['propiedad_id' => $this->propiedad->id, 'rol' => 'contador']]);

    expect(Acceso::puede($contador, Acceso::CUENTAS, $this->propiedad->id))->toBeTrue()
        ->and(Permission::query()->where('name', 'facturacion.gestionar')->exists())->toBeFalse();
});

it('si el permiso nuevo ya existe por el seeder, la migración junta los dos', function (): void {
    $migracion = require database_path('migrations/2026_11_05_280000_quitar_facturacion.php');
    $migracion->down();
    $nuevo = Permission::query()->create(['name' => 'cuentas.gestionar', 'guard_name' => 'web']);

    $migracion->up();

    expect(Permission::query()->where('name', 'facturacion.gestionar')->exists())->toBeFalse()
        ->and(Permission::query()->where('name', 'cuentas.gestionar')->sole()->id)->toBe($nuevo->id)
        ->and(DB::table('role_has_permissions')->where('permission_id', $nuevo->id)->count())->toBeGreaterThan(0);
});

it('el registro de llegada de México ya no pide el RFC', function (): void {
    expect((new Mexico())->camposLlegada())->not->toHaveKey('rfc');
});

it('la migración se lleva las tablas, lo emitido y el módulo, y respeta quién tiene el permiso', function (): void {
    $contador = User::factory()->create(['activo' => true, 'idioma' => 'es']);
    Acceso::asignarRoles($contador, [['propiedad_id' => $this->propiedad->id, 'rol' => 'contador']]);
    $conAgregado = User::factory()->create(['activo' => true, 'idioma' => 'es']);
    $migracion = require database_path('migrations/2026_11_05_280000_quitar_facturacion.php');
    $migracion->down();
    AccesoUsuario::query()->create(['user_id' => $contador->id, 'propiedad_id' => $this->propiedad->id, 'permisos_quitados' => ['facturacion.gestionar']]);
    AccesoUsuario::query()->create(['user_id' => $conAgregado->id, 'propiedad_id' => $this->propiedad->id, 'permisos_agregados' => ['facturacion.gestionar', 'datos.ver']]);

    expect(Schema::hasTable('facturas'))->toBeTrue();
    Storage::disk('local')->put('archivos/factura-de-prueba.pdf', 'pdf');
    DB::table('archivos')->insert(['ruta' => 'archivos/factura-de-prueba.pdf', 'nombre_original' => 'A-1.pdf', 'tipo_mime' => 'application/pdf', 'bytes' => 3, 'sha256' => str_repeat('a', 64), 'cifrado' => false, 'clase' => 'factura_pdf']);
    DB::table('ajustes')->updateOrInsert(['clave' => 'modulos.activos'], ['valor' => json_encode(['caja', 'facturacion'])]);

    $migracion->up();
    Ajustes::olvidar();

    expect(Schema::hasTable('facturas'))->toBeFalse()
        ->and(Schema::hasTable('entidades_fiscales'))->toBeFalse()
        ->and(Storage::disk('local')->exists('archivos/factura-de-prueba.pdf'))->toBeFalse()
        ->and(DB::table('archivos')->where('clase', 'factura_pdf')->exists())->toBeFalse()
        ->and(Ajustes::obtener('modulos.activos'))->toBe(['caja'])
        // A quien se le quitó el permiso no lo recupera, y a quien se le agregó no lo pierde.
        ->and(Acceso::puede($contador->refresh(), Acceso::CUENTAS, $this->propiedad->id))->toBeFalse()
        ->and(AccesoUsuario::query()->where('user_id', $contador->id)->sole()->permisos_quitados)->toBe([Acceso::CUENTAS])
        ->and(AccesoUsuario::query()->where('user_id', $conAgregado->id)->sole()->permisos_agregados)->toBe([Acceso::CUENTAS, 'datos.ver'])
        ->and(Acceso::puede($conAgregado->refresh(), Acceso::CUENTAS, $this->propiedad->id))->toBeTrue();

    // Las tablas se cambian fuera de la transacción de la prueba: lo escrito se queda.
    AccesoUsuario::query()->delete();
    Ajustes::borrar('modulos.activos');
});
