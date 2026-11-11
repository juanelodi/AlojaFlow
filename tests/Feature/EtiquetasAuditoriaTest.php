<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

use App\Enums\ModoPropiedad;
use App\Filament\Resources\Auditoria\Pages\ListRegistrosAuditoria;
use App\Models\Propiedad;
use App\Models\RegistroAuditoria;
use App\Models\User;
use App\Seguridad\Acceso;
use App\Seguridad\AuditoriaModelos;
use App\Seguridad\EtiquetasAuditoria;
use App\Support\Csv;
use Database\Seeders\RolesYPermisosSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

/**
 * @return list<string> los códigos que el programa escribe tal cual, más los que arma
 */
function codigosDeAuditoria(): array
{
    $codigos = ['grupo.clave_nueva', 'propietario.clave_nueva', 'importar.huespedes', 'importar.reservas'];
    foreach (File::allFiles(app_path()) as $archivo) {
        preg_match_all("/anotar\(\s*(?:[^,'()]+(?:\([^)]*\))?,\s*)?(?:[A-Za-z]+::[A-Za-z]+,\s*)?'([a-z_]+\.[a-z_]+)'/", $archivo->getContents(), $coincidencias);
        array_push($codigos, ...$coincidencias[1]);
    }

    return array_values(array_unique($codigos));
}

beforeEach(function (): void {
    $this->seed(RolesYPermisosSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('app'));
    $this->admin = User::query()->create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => Hash::make('contrasena-de-prueba'), 'activo' => true, 'es_administrador' => true, 'idioma' => 'en']);
});

it('cada acción que el programa anota se lee en cada idioma', function (string $idioma): void {
    app()->setLocale($idioma);
    $codigos = codigosDeAuditoria();

    expect(count($codigos))->toBeGreaterThan(80);
    foreach ($codigos as $codigo) {
        expect(EtiquetasAuditoria::accion($codigo))->not->toBe($codigo, "$idioma: $codigo");
    }
})->with(['es', 'en', 'fr', 'it', 'pt', 'de']);

it('cada modelo que se audita tiene su nombre', function (): void {
    $modelos = (new ReflectionClassConstant(AuditoriaModelos::class, 'MODELOS'))->getValue();

    foreach ([...$modelos, 'App\\Models\\Role'] as $modelo) {
        $tipo = class_basename($modelo);
        expect(__('auditoria.objetos.'.$tipo))->not->toBe('auditoria.objetos.'.$tipo, $tipo);
    }
});

it('lo crea, cambia y borra sin nombre propio se lee por su verbo, y lo desconocido sale como está', function (): void {
    app()->setLocale('en');

    expect(EtiquetasAuditoria::accion('reserva.crear'))->toBe('Booking created')
        ->and(EtiquetasAuditoria::accion('cambio.crear'))->toBe('Created')
        ->and(EtiquetasAuditoria::accion('cambio.editar'))->toBe('Changed')
        ->and(EtiquetasAuditoria::accion('impuesto.eliminar'))->toBe('Deleted')
        ->and(EtiquetasAuditoria::accion('reserva'))->toBe('reserva')
        ->and(EtiquetasAuditoria::accion('algo.desconocido'))->toBe('algo.desconocido')
        ->and(EtiquetasAuditoria::objeto('Reserva', 286))->toBe('Booking #286')
        ->and(EtiquetasAuditoria::objeto('ModeloNuevo', 3))->toBe('ModeloNuevo #3')
        ->and(EtiquetasAuditoria::objeto(null, null))->toBeNull();
});

it('el detalle se lee en el idioma de quien lo mira', function (): void {
    app()->setLocale('en');

    expect(EtiquetasAuditoria::detalle(null))->toBeNull()
        ->and(EtiquetasAuditoria::detalle(['clave_hash' => EtiquetasAuditoria::CAMBIO, 'acceso_activo' => EtiquetasAuditoria::CAMBIO]))->toBe('Password: changed · Active access: changed')
        ->and(EtiquetasAuditoria::detalle(['precio_noche' => ['antes' => 100, 'despues' => 200]]))->toBe('Price per night: 100 → 200')
        ->and(EtiquetasAuditoria::detalle(['despues' => ['total' => 5000, 'estado' => 'confirmada', 'llegada' => '2027-01-03']]))->toBe('After: (Total: 5000, Status: Confirmed, Arrival: 2027-01-03)')
        ->and(EtiquetasAuditoria::detalle(['copia' => 'copia-1.zip', 'automaticas' => true, 'seguridad' => null]))->toBe('Backup: copia-1.zip · Automatic: Yes · Prior backup: —')
        ->and(EtiquetasAuditoria::detalle(['campos' => ['nombre', 'columna_rara']]))->toBe('Fields: Name, columna rara')
        // Lo que escribió una persona no se traduce, aunque coincida con un valor del programa.
        ->and(EtiquetasAuditoria::detalle(['nombre' => ['antes' => 'salida', 'despues' => 'manual'], 'accion' => 'salida']))->toBe('Name: salida → manual · Action: Check-out');
});

describe('en la pantalla', function (): void {
    beforeEach(function (): void {
        app()->setLocale('en');
        $propiedad = Propiedad::query()->create(['nombre' => 'Posada', 'codigo' => 'posada', 'modo' => ModoPropiedad::Nativa, 'zona_horaria' => 'UTC', 'moneda' => 'MXN', 'pais' => 'MX', 'idioma' => 'es']);
        RegistroAuditoria::anotar('propiedad.independizar', $propiedad, ['reservas' => 2], $propiedad->id);
        RegistroAuditoria::anotar('copia.crear', null, ['copia' => 'copia-1.zip', 'origen' => 'programada']);
        $this->propiedad = $propiedad;
    });

    it('la tabla no enseña códigos en español', function (): void {
        Livewire::actingAs($this->admin)->test(ListRegistrosAuditoria::class)
            ->assertSee('Property made independent')
            ->assertSee('Property #'.$this->propiedad->id)
            ->assertSee('Bookings: 2')
            ->assertSee('Backup created')
            ->assertSee('Source: Scheduled')
            ->assertDontSee('propiedad.independizar')
            ->assertDontSee('copia.crear');
    });

    it('el buscador encuentra lo que se lee, y también el código', function (): void {
        foreach (['Backup created', 'backup', 'copia.crear'] as $texto) {
            Livewire::actingAs($this->admin)->test(ListRegistrosAuditoria::class)
                ->searchTable($texto)
                ->assertSee('Backup created')
                ->assertDontSee('Property made independent');
        }
        Livewire::actingAs($this->admin)->test(ListRegistrosAuditoria::class)
            ->searchTable('Property')
            ->assertSee('Property made independent')
            ->assertDontSee('Backup created');
    });

    it('lo exportado dice lo mismo que la pantalla', function (): void {
        $respuesta = Livewire::actingAs($this->admin)->test(ListRegistrosAuditoria::class)
            ->callAction('exportar', ['desde' => now()->subDay()->format('Y-m-d'), 'hasta' => now()->addDay()->format('Y-m-d')]);
        $texto = Csv::enUtf8((string) base64_decode((string) ($respuesta->effects['download']['content'] ?? ''), true));

        expect($texto)->toContain('Property made independent')->toContain('Property #'.$this->propiedad->id)->toContain('Bookings: 2')->not->toContain('propiedad.independizar');
    });

    it('sigue siendo solo para administradores', function (): void {
        $gerente = User::query()->create(['name' => 'Gerente', 'email' => 'gerente@example.com', 'password' => Hash::make('contrasena-de-prueba'), 'activo' => true, 'idioma' => 'en']);
        Acceso::asignarRoles($gerente, [['propiedad_id' => $this->propiedad->id, 'rol' => 'gerente']]);

        $this->actingAs($gerente)->get('/auditoria')->assertForbidden();
    });
});
