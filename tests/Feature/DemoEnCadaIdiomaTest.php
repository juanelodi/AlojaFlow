<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

use App\Demo\GeneradorDemo;
use App\Documentos\DocumentosDePartida;
use App\Models\Canal;
use App\Models\ConceptoCargo;
use App\Models\ContactoHuesped;
use App\Models\Cupon;
use App\Models\DocumentoLegal;
use App\Models\DocumentoPropio;
use App\Models\Huesped;
use App\Models\Impuesto;
use App\Models\Movimiento;
use App\Models\Propiedad;
use App\Models\Reserva;
use App\Models\Tarifa;
use App\Models\Unidad;
use Database\Seeders\RolesYPermisosSeeder;

beforeEach(function (): void {
    $this->seed(RolesYPermisosSeeder::class);
});

afterEach(fn () => app()->setLocale((string) config('app.locale')));

/**
 * @return list<string> lo que escribe la demostración y se lee en pantalla
 */
function textosDeLaDemo(Propiedad $propiedad): array
{
    $de = fn (string $modelo, string $columna): array => $modelo::query()->where('propiedad_id', $propiedad->id)->pluck($columna)->filter()->unique()->values()->all();

    return array_merge(
        $de(Tarifa::class, 'nombre'),
        $de(Unidad::class, 'nombre'),
        $de(Unidad::class, 'edificio'),
        $de(Impuesto::class, 'nombre'),
        $de(ConceptoCargo::class, 'nombre'),
        $de(Canal::class, 'nombre'),
        $de(Cupon::class, 'codigo'),
        $de(Movimiento::class, 'concepto'),
        $de(Movimiento::class, 'categoria'),
        $de(Huesped::class, 'apellido1'),
        $de(Huesped::class, 'nacionalidad_texto'),
        $de(Reserva::class, 'origen_texto'),
        $de(Reserva::class, 'comentario'),
    );
}

it('en inglés nace en inglés, sin nada en español', function (): void {
    app()->setLocale('en');

    $demo = app(GeneradorDemo::class)->crear();
    $textos = textosDeLaDemo($demo);

    expect($demo->idioma)->toBe('en')
        ->and($textos)->toContain('Double', 'Family', 'Cabin', 'Cabin A', 'Garden', 'Lodging tax', 'Breakfast', 'Direct', 'Phone', 'WELCOME10', 'Electricity', 'Utilities', 'Mexico')
        ->and($textos)->not->toContain('Doble', 'Familiar', 'Cabaña', 'Cabaña A', 'Jardín', 'Impuesto sobre hospedaje', 'Desayuno', 'Directo', 'Teléfono', 'BIENVENIDA10', 'Luz', 'Servicios', 'México');
    foreach ($textos as $texto) {
        expect($texto)->not->toMatch('/[áéíóúñ¿¡]/iu', $texto.' sigue en español');
    }
});

it('los documentos salen en su idioma', function (): void {
    app()->setLocale('en');

    $demo = app(GeneradorDemo::class)->crear();

    expect(DocumentoLegal::query()->where('propiedad_id', $demo->id)->where('idioma', 'en')->exists())->toBeTrue()
        ->and(DocumentoPropio::query()->where('propiedad_id', $demo->id)->pluck('nombre')->all())->toContain(__('documentos_propios.base.'.array_key_first((new ReflectionClassConstant(DocumentosDePartida::class, 'DOCUMENTOS'))->getValue()).'.nombre', [], 'en'));
});

it('las cabañas siguen siendo del dueño, y la tarifa con desayuno sale de la doble, en cualquier idioma', function (string $idioma): void {
    app()->setLocale($idioma);

    $demo = app(GeneradorDemo::class)->crear();
    $doble = Tarifa::query()->where('propiedad_id', $demo->id)->where('nombre', __('ui.demo.tarifa_doble'))->sole();

    expect(Unidad::query()->where('propiedad_id', $demo->id)->whereNotNull('propietario_id')->count())->toBe(2)
        ->and(Unidad::query()->where('propiedad_id', $demo->id)->where('codigo', __('ui.demo.cabana').' A')->exists())->toBeTrue()
        ->and(Tarifa::query()->where('tarifa_base_id', $doble->id)->count())->toBe(2)
        ->and($demo->idioma)->toBe($idioma);
})->with(['es', 'fr', 'de']);

it('un correo de la demo nunca lleva acentos, en ningún idioma', function (string $idioma): void {
    app()->setLocale($idioma);

    app(GeneradorDemo::class)->crear();

    expect(ContactoHuesped::query()->where('tipo', 'correo')->pluck('valor')->all())
        ->each->toMatch('/^[a-z0-9.\-]+@example\.com$/');
})->with(['es', 'fr', 'de', 'pt']);

it('en español la demo no cambió', function (): void {
    app()->setLocale('es');

    $demo = app(GeneradorDemo::class)->crear();
    $textos = textosDeLaDemo($demo);

    expect($demo->idioma)->toBe('es')
        ->and($textos)->toContain('Doble', 'Cabaña A', 'Jardín', 'Impuesto sobre hospedaje', 'Desayuno', 'Directo', 'BIENVENIDA10', 'México');
});

it('con un idioma que no conoce, parte del español', function (): void {
    app()->setLocale('nl');

    expect(app(GeneradorDemo::class)->crear()->idioma)->toBe('es');
});
