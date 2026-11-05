<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

use App\Enums\ModoPropiedad;
use App\Filament\Pages\Indicadores;
use App\Filament\Pages\Informes;
use App\Filament\Resources\Auditoria\Pages\ListRegistrosAuditoria;
use App\Models\Huesped;
use App\Models\Propiedad;
use App\Models\RegistroAuditoria;
use App\Models\Tarifa;
use App\Models\Unidad;
use App\Models\User;
use App\Modulos\Modulo;
use App\Modulos\Modulos;
use App\Reservas\GestorReservas;
use App\Seguridad\Acceso;
use App\Support\Ajustes;
use App\Support\Csv;
use App\Support\Dinero;
use Database\Seeders\RolesYPermisosSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

function textoDeLaDescarga(object $pagina): string
{
    expect($pagina->effects['download']['contentType'] ?? null)->toBe(Csv::TIPO_EXCEL);

    return Csv::enUtf8((string) base64_decode((string) ($pagina->effects['download']['content'] ?? ''), true));
}

beforeEach(function (): void {
    $this->seed(RolesYPermisosSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Ajustes::olvidar();
    Modulos::guardar(Modulo::cases());
    app()->setLocale('es');
    $this->propiedad = Propiedad::query()->create(['nombre' => '=Posada', 'codigo' => 'posada', 'modo' => ModoPropiedad::Nativa, 'zona_horaria' => 'America/Mexico_City', 'moneda' => 'MXN', 'idioma' => 'es']);
    $this->propiedad->refresh();
    $tarifa = Tarifa::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Doble', 'precio_noche' => 100000, 'personas_incluidas' => 2]);
    $unidad = Unidad::query()->create(['propiedad_id' => $this->propiedad->id, 'tarifa_id' => $tarifa->id, 'codigo' => '101', 'nombre' => 'Cuarto 101', 'capacidad' => 4]);
    $this->gerente = User::query()->create(['name' => 'Ger', 'email' => 'ger@example.com', 'password' => Hash::make('contrasena-de-prueba'), 'activo' => true, 'idioma' => 'es']);
    Acceso::asignarRoles($this->gerente, [['propiedad_id' => $this->propiedad->id, 'rol' => 'gerente']]);
    $this->actingAs($this->gerente);
    $huesped = Huesped::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Ana', 'apellido1' => 'Ejemplo']);
    $hoy = $this->propiedad->hoy();
    (new GestorReservas())->crear($this->propiedad, ['unidad_id' => $unidad->id, 'titular_id' => $huesped->id, 'llegada' => $hoy->format('Y-m-d'), 'salida' => $hoy->addDays(2)->format('Y-m-d'), 'personas' => 2, 'estado' => 'confirmada', 'total' => 212350]);
    $this->rango = ['desde' => $hoy->subDays(3)->format('Y-m-d'), 'hasta' => $hoy->addDays(3)->format('Y-m-d')];
});

afterEach(fn () => Ajustes::olvidar());

describe('el archivo', function (): void {
    it('lleva UTF-16 y tabuladores, y se vuelve a leer tal cual', function (): void {
        $archivo = Csv::paraExcel([['Nombre', 'Nota con "comillas"'], ['José Núñez', "Dos\nlíneas"]]);

        expect(substr($archivo, 0, 2))->toBe("\xFF\xFE")
            ->and(Csv::enUtf8($archivo))->toBe("Nombre\t\"Nota con \"\"comillas\"\"\"\r\n\"José Núñez\"\t\"Dos\nlíneas\"\r\n");
    });

    it('un generador da lo mismo que un arreglo, y la descarga lo mismo que el archivo', function (): void {
        $filas = [['Uno', '1'], ['Dos', '2']];
        $generador = function () use ($filas): Generator {
            yield from $filas;
        };

        $respuesta = Csv::descargaParaExcel($generador(), 'prueba.csv');
        ob_start();
        $respuesta->sendContent();

        expect(Csv::paraExcel($generador()))->toBe(Csv::paraExcel($filas))
            ->and((string) ob_get_clean())->toBe(Csv::paraExcel($filas))
            ->and($respuesta->headers->get('Content-Type'))->toBe(Csv::TIPO_EXCEL)
            ->and($respuesta->headers->get('Content-Disposition'))->toContain('prueba.csv');
    });

    it('sin filas solo queda la marca', function (): void {
        expect(Csv::paraExcel([]))->toBe("\xFF\xFE");
    });

    it('no deja pasar fórmulas, y un negativo con coma decimal sigue siendo número', function (): void {
        expect(Csv::fila(['-1000,50', '-1000.50', '+1,5', '=1,5', '-1,5,=2', '@a,1']))
            ->toBe(['-1000,50', '-1000.50', '+1,5', "'=1,5", "'-1,5,=2", "'@a,1"]);
    });

    it('el separador decimal sale del idioma y de la moneda', function (string $idioma, string $moneda, string $separador): void {
        app()->setLocale($idioma);

        expect(Dinero::separadorDecimal($moneda))->toBe($separador)
            ->and(Csv::numero('1000.50', $moneda))->toBe('1000'.$separador.'50')
            ->and(Csv::numero('', $moneda))->toBe('');
    })->with([
        ['es', 'MXN', '.'],
        ['es', 'EUR', ','],
        ['en', 'USD', '.'],
        ['de', 'EUR', ','],
        ['fr', 'EUR', ','],
    ]);
});

describe('las pantallas', function (): void {
    it('los indicadores salen en columnas, con el decimal de su moneda', function (): void {
        $pagina = fn () => Livewire::actingAs($this->gerente)->test(Indicadores::class)->set('desde', $this->rango['desde'])->set('hasta', $this->rango['hasta'])->call('descargarCsv');

        $texto = textoDeLaDescarga($pagina());
        expect($texto)->toContain(__('ui.indicadores.csv.propiedad')."\t".__('ui.indicadores.csv.moneda'))
            ->toContain("'=Posada\tMXN")
            ->toContain('2123.50')
            ->not->toContain('2123,50')
            ->not->toContain(';');

        $this->propiedad->update(['moneda' => 'EUR']);
        App\Models\Reserva::query()->update(['moneda' => 'EUR']);

        expect(textoDeLaDescarga($pagina()))->toContain('2123,50')->not->toContain('2123.50');
    });

    it('los informes salen en columnas, con un gerente', function (): void {
        $texto = textoDeLaDescarga(Livewire::actingAs($this->gerente)->test(Informes::class)->set('desde', $this->rango['desde'])->set('hasta', $this->rango['hasta'])->set('informe', 'unidades')->call('descargarCsv'));

        expect($texto)->toContain("\t")->toContain('Cuarto 101')->toContain('$2,123.50');
    });

    it('la auditoría pasa entera, sin repetir ni perder filas de un tramo al otro', function (): void {
        $admin = User::query()->create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => Hash::make('contrasena-de-prueba'), 'activo' => true, 'es_administrador' => true, 'idioma' => 'es']);
        RegistroAuditoria::query()->delete();
        for ($i = 1; $i <= 501; $i++) {
            RegistroAuditoria::anotar('=prueba.'.$i);
        }

        $texto = textoDeLaDescarga(Livewire::actingAs($admin)->test(ListRegistrosAuditoria::class)
            ->callAction('exportar', ['desde' => now()->subDay()->format('Y-m-d'), 'hasta' => now()->addDay()->format('Y-m-d')]));

        expect(substr_count($texto, "'=prueba."))->toBe(501)
            ->and($texto)->toContain("'=prueba.1\t")->toContain("'=prueba.501\t")->toContain(__('ui.auditoria.accion')."\t");
    });
});
