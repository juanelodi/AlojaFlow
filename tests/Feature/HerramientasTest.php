<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

use App\Actualizacion\EsquemaAlDia;
use App\Enums\EstadoReserva;
use App\Enums\ModoPropiedad;
use App\Enums\TipoContacto;
use App\Filament\Pages\AnonimizarHuespedes;
use App\Filament\Pages\Calendario;
use App\Filament\Pages\EditarPerfil;
use App\Filament\Pages\HuespedesDuplicados;
use App\Filament\Resources\Huespedes\Pages\EditHuesped;
use App\Filament\Resources\Propiedades\Pages\EditPropiedad;
use App\Filament\Resources\RegistrosTuristicos\Pages\CreateRegistroTuristico;
use App\Filament\Resources\RegistrosTuristicos\Pages\ListRegistrosTuristicos;
use App\Http\Middleware\ProgramadorPorVisitas;
use App\Instalacion\Instalador;
use App\Models\Aviso;
use App\Models\ColorTipoCliente;
use App\Models\ContactoHuesped;
use App\Models\Huesped;
use App\Models\Principal;
use App\Models\Propiedad;
use App\Models\RegistroAuditoria;
use App\Models\RegistroTuristico;
use App\Models\Reserva;
use App\Models\Tarifa;
use App\Models\Unidad;
use App\Models\User;
use App\Modulos\Modulo;
use App\Modulos\Modulos;
use App\Paises\CatalogoIsh;
use App\Paises\CatalogoRegiones;
use App\Privacidad\GestorPrivacidad;
use App\Propiedades\RegistrosTuristicos;
use App\Reservas\DetectorDuplicados;
use App\Reservas\GestorReservas;
use App\Seguridad\Acceso;
use App\Support\Ajustes;
use App\Support\ColoresEstado;
use Database\Seeders\RolesYPermisosSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

afterEach(fn () => Ajustes::olvidar());

beforeEach(function (): void {
    $this->seed(RolesYPermisosSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Ajustes::olvidar();
    Modulos::guardar(Modulo::cases());
    $this->propiedad = Propiedad::query()->create([
        'nombre' => 'Posada', 'codigo' => 'posada', 'modo' => ModoPropiedad::Nativa, 'zona_horaria' => 'America/Mexico_City', 'moneda' => 'MXN', 'pais' => 'MX', 'idioma' => 'es',
        'ajustes' => ['motor' => ['activo' => true, 'minutos_retencion' => 30]],
    ]);
    $this->propiedad->refresh();
    $this->ajena = Propiedad::query()->create(['nombre' => 'Otra', 'codigo' => 'otra', 'modo' => ModoPropiedad::Nativa, 'zona_horaria' => 'America/Mexico_City', 'moneda' => 'MXN', 'pais' => 'MX', 'idioma' => 'es']);
    $this->tarifa = Tarifa::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Doble', 'precio_noche' => 100000, 'personas_incluidas' => 2, 'activa' => true]);
    $this->unidad = Unidad::query()->create(['propiedad_id' => $this->propiedad->id, 'tarifa_id' => $this->tarifa->id, 'codigo' => '101', 'nombre' => 'Cabaña', 'capacidad' => 4, 'activa' => true]);
    $this->otra = Unidad::query()->create(['propiedad_id' => $this->propiedad->id, 'tarifa_id' => $this->tarifa->id, 'codigo' => '102', 'nombre' => 'Suite', 'capacidad' => 4, 'activa' => true]);
    $this->admin = User::query()->create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => Hash::make('contrasena-de-prueba'), 'activo' => true, 'es_administrador' => true]);
    $this->recepcion = User::query()->create(['name' => 'Rec', 'email' => 'rec@example.com', 'password' => Hash::make('contrasena-de-prueba'), 'activo' => true, 'idioma' => 'es']);
    Acceso::asignarRoles($this->recepcion, [['propiedad_id' => $this->propiedad->id, 'rol' => 'recepcion']]);
    $this->gerente = User::query()->create(['name' => 'Ger', 'email' => 'ger@example.com', 'password' => Hash::make('contrasena-de-prueba'), 'activo' => true]);
    Acceso::asignarRoles($this->gerente, [['propiedad_id' => $this->propiedad->id, 'rol' => 'gerente']]);
    $this->limpieza = User::query()->create(['name' => 'Lim', 'email' => 'lim@example.com', 'password' => Hash::make('contrasena-de-prueba'), 'activo' => true]);
    Acceso::asignarRoles($this->limpieza, [['propiedad_id' => $this->propiedad->id, 'rol' => 'limpieza']]);
    $this->ficha = function (array $datos = [], ?Propiedad $propiedad = null): Huesped {
        $sincronizada = $datos['sincronizada'] ?? false;
        $creada = $datos['creado_en'] ?? null;
        unset($datos['sincronizada'], $datos['creado_en']);
        $huesped = Huesped::query()->create(['propiedad_id' => ($propiedad ?? $this->propiedad)->id, 'nombre' => 'Ana', 'apellido1' => 'Pérez', ...$datos]);
        // Ni el formulario ni la sincronización dejan poner esto: se escribe directo, como lo deja una copia.
        Huesped::query()->whereKey($huesped->id)->toBase()->update(array_filter(['sincronizada' => $sincronizada, 'creado_en' => $creada], fn ($v) => $v !== null));

        return $huesped->fresh();
    };
    $this->reservar = fn (Huesped $titular, string $llegada, string $salida, array $mas = []): Reserva => app(GestorReservas::class)->crear($this->propiedad, $mas + [
        'unidad_id' => $this->unidad->id, 'titular_id' => $titular->id, 'llegada' => $llegada, 'salida' => $salida,
        'personas' => 2, 'estado' => EstadoReserva::Confirmada->value, 'total' => 100000,
    ]);
    $this->principal = function (array $horario = [], string $zona = 'America/Mexico_City'): Principal {
        $propiedad = Propiedad::query()->create([
            'nombre' => 'Sub', 'codigo' => 'sub-'.uniqid(), 'modo' => ModoPropiedad::Subordinada, 'zona_horaria' => $zona, 'moneda' => 'MXN', 'pais' => 'MX', 'idioma' => 'es',
        ]);
        return Principal::query()->forceCreate(['propiedad_id' => $propiedad->id, 'url_base' => 'https://principal.example.com/', 'usuario' => 'u', 'contrasena' => 'x', ...$horario])->fresh();
    };
});

describe('duplicados', function (): void {
    it('agrupa por nombre sin acentos y por las últimas cifras del teléfono', function (): void {
        $a = ($this->ficha)(['nombre' => 'José', 'apellido1' => 'Núñez']);
        $b = ($this->ficha)(['nombre' => 'JOSE', 'apellido1' => 'nunez']);
        $c = ($this->ficha)(['nombre' => 'Mar', 'apellido1' => 'Uno']);
        $d = ($this->ficha)(['nombre' => 'Sol', 'apellido1' => 'Dos']);
        ContactoHuesped::query()->create(['huesped_id' => $c->id, 'tipo' => TipoContacto::Telefono, 'valor' => '+52 755 123 4567', 'valor_normalizado' => '527551234567']);
        ContactoHuesped::query()->create(['huesped_id' => $d->id, 'tipo' => TipoContacto::Telefono, 'valor' => '(755) 123-4567', 'valor_normalizado' => '7551234567']);

        $grupos = collect(app(DetectorDuplicados::class)->grupos($this->propiedad->id));

        expect($grupos->pluck('motivo')->all())->toEqualCanonicalizing(['nombre', 'telefono'])
            ->and(collect($grupos->firstWhere('motivo', 'nombre')['huespedes'])->pluck('id')->all())->toEqualCanonicalizing([$a->id, $b->id])
            ->and(collect($grupos->firstWhere('motivo', 'telefono')['huespedes'])->pluck('id')->all())->toEqualCanonicalizing([$c->id, $d->id]);
    });

    it('encuentra el mismo documento aunque se llamen distinto', function (): void {
        $a = ($this->ficha)(['nombre' => 'Mar', 'apellido1' => 'Uno', 'documento_numero' => 'X123']);
        $b = ($this->ficha)(['nombre' => 'Sol', 'apellido1' => 'Dos', 'documento_numero' => 'X123']);

        $grupos = app(DetectorDuplicados::class)->grupos($this->propiedad->id);

        expect($grupos)->toHaveCount(1)
            ->and($grupos[0]['motivo'])->toBe('documento')
            ->and(collect($grupos[0]['huespedes'])->pluck('id')->all())->toEqualCanonicalizing([$a->id, $b->id]);
    });

    it('no mezcla propiedades ni toma una sola palabra por nombre', function (): void {
        ($this->ficha)();
        ($this->ficha)([], $this->ajena);
        ($this->ficha)(['nombre' => 'Solo', 'apellido1' => null]);
        ($this->ficha)(['nombre' => 'Solo', 'apellido1' => null]);

        expect(app(DetectorDuplicados::class)->grupos($this->propiedad->id))->toBe([]);
    });

    it('no enseña fichas de la principal, que se fusionan allá', function (): void {
        ($this->ficha)();
        ($this->ficha)(['sincronizada' => true]);

        expect(app(DetectorDuplicados::class)->grupos($this->propiedad->id))->toBe([]);
    });

    it('dos personas iguales en nombre y correo salen una sola vez', function (): void {
        foreach ([1, 2] as $vez) {
            $ficha = ($this->ficha)();
            ContactoHuesped::query()->create(['huesped_id' => $ficha->id, 'tipo' => TipoContacto::Correo, 'valor' => 'ana@example.com', 'valor_normalizado' => 'ana@example.com']);
        }

        expect(app(DetectorDuplicados::class)->grupos($this->propiedad->id))->toHaveCount(1);
    });

    it('la pantalla los lista y fusiona, sin tocar lo de otra propiedad', function (): void {
        $conservar = ($this->ficha)();
        $otra = ($this->ficha)();
        $deFuera = ($this->ficha)([], $this->ajena);

        Livewire::actingAs($this->recepcion)->test(HuespedesDuplicados::class)
            ->assertSee(__('herramientas.duplicados.motivos.nombre'))
            ->call('fusionar', $conservar->id, [$otra->id, $deFuera->id])
            ->assertHasNoErrors();

        expect(Huesped::query()->whereKey($otra->id)->exists())->toBeFalse()
            ->and(Huesped::query()->whereKey($deFuera->id)->exists())->toBeTrue();
    });

    it('no deja conservar una ficha de otra propiedad', function (): void {
        $deFuera = ($this->ficha)([], $this->ajena);

        Livewire::actingAs($this->recepcion)->test(HuespedesDuplicados::class)->call('fusionar', $deFuera->id, [])->assertNotFound();
    });

    it('no entra quien no tiene el permiso ni quien solo ve lo suyo', function (): void {
        Livewire::actingAs($this->limpieza)->test(HuespedesDuplicados::class)->assertForbidden();

        Acceso::asignarRoles($this->recepcion, [['propiedad_id' => $this->propiedad->id, 'rol' => 'recepcion', 'solo_propias' => true]]);
        Acceso::olvidar();
        Livewire::actingAs($this->recepcion)->test(HuespedesDuplicados::class)->assertForbidden();
    });
});

describe('anonimizar por antigüedad', function (): void {
    it('solo quienes llevan N años sin venir', function (): void {
        $hace = fn (int $anios): string => now()->subYears($anios)->toDateTimeString();
        $hoy = $this->propiedad->hoy();
        $viejo = ($this->ficha)(['creado_en' => $hace(8)]);
        ($this->reservar)($viejo, $hoy->subYears(7)->format('Y-m-d'), $hoy->subYears(7)->addDays(2)->format('Y-m-d'));
        $reciente = ($this->ficha)(['creado_en' => $hace(8)]);
        ($this->reservar)($reciente, $hoy->subYear()->format('Y-m-d'), $hoy->subYear()->addDays(2)->format('Y-m-d'));
        $futura = ($this->ficha)(['creado_en' => $hace(8)]);
        ($this->reservar)($futura, $hoy->addMonth()->format('Y-m-d'), $hoy->addMonth()->addDays(2)->format('Y-m-d'));
        ($this->ficha)(['creado_en' => now()->subWeek()->toDateTimeString()]);
        ($this->ficha)(['creado_en' => $hace(8), 'sincronizada' => true]);
        ($this->ficha)(['creado_en' => $hace(8)], $this->ajena);

        $ids = app(GestorPrivacidad::class)->sinEstanciasDesde([$this->propiedad->id], 5)->pluck('id')->all();

        expect($ids)->toBe([$viejo->id]);
    });

    it('quien viajó de acompañante hace poco no sale', function (): void {
        $hoy = $this->propiedad->hoy();
        $titular = ($this->ficha)(['nombre' => 'Mar', 'creado_en' => now()->subYears(8)->toDateTimeString()]);
        $acompanante = ($this->ficha)(['nombre' => 'Luis', 'creado_en' => now()->subYears(8)->toDateTimeString()]);
        $reserva = ($this->reservar)($titular, $hoy->subMonth()->format('Y-m-d'), $hoy->subMonth()->addDays(2)->format('Y-m-d'));
        $reserva->huespedes()->attach($acompanante->id);

        expect(app(GestorPrivacidad::class)->sinEstanciasDesde([$this->propiedad->id], 5)->pluck('id')->all())->toBe([]);
    });

    it('anonimiza con la contraseña, queda en la auditoría y no vuelve a salir', function (): void {
        $viejo = ($this->ficha)(['creado_en' => now()->subYears(8)->toDateTimeString()]);

        Livewire::actingAs($this->gerente)->test(AnonimizarHuespedes::class)
            ->assertSee('Ana Pérez')
            ->callAction('anonimizarTodos', data: ['contrasena' => 'contrasena-de-prueba'])
            ->assertHasNoActionErrors()
            ->assertDontSee('Ana Pérez');

        expect($viejo->fresh()->apellido1)->toBeNull()
            ->and($viejo->fresh()->nombre)->toContain((string) $viejo->id)
            ->and(RegistroAuditoria::query()->where('accion', 'privacidad.anonimizar_lote')->exists())->toBeTrue()
            ->and(app(GestorPrivacidad::class)->sinEstanciasDesde([$this->propiedad->id], 5)->count())->toBe(0);
    });

    it('sin la contraseña no hace nada', function (): void {
        $viejo = ($this->ficha)(['creado_en' => now()->subYears(8)->toDateTimeString()]);

        Livewire::actingAs($this->gerente)->test(AnonimizarHuespedes::class)
            ->callAction('anonimizarTodos', data: ['contrasena' => 'no-es-esta'])
            ->assertHasActionErrors(['contrasena']);

        expect($viejo->fresh()->apellido1)->toBe('Pérez');
    });

    it('no alcanza a las propiedades de otros, ni entra quien no administra', function (): void {
        $deOtro = ($this->ficha)(['creado_en' => now()->subYears(8)->toDateTimeString()], $this->ajena);

        Livewire::actingAs($this->gerente)->test(AnonimizarHuespedes::class)
            ->assertDontSee('Ana Pérez');
        expect($deOtro->fresh()->apellido1)->toBe('Pérez');

        Livewire::actingAs($this->recepcion)->test(AnonimizarHuespedes::class)->assertForbidden();
        Livewire::actingAs($this->limpieza)->test(AnonimizarHuespedes::class)->assertForbidden();
    });
});

describe('tipo de cliente en una independiente', function (): void {
    it('se escribe en la ficha y colorea el calendario', function (): void {
        ColorTipoCliente::query()->create(['propiedad_id' => $this->propiedad->id, 'campo' => 'Tipo', 'valor' => 'VIP', 'color' => '#7c3aed']);
        $ficha = ($this->ficha)();

        Livewire::actingAs($this->recepcion)->test(EditHuesped::class, ['record' => $ficha->getRouteKey()])
            ->set('data.campos', [['campo' => 'Tipo', 'valor' => 'VIP']])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($ficha->fresh()->campos->pluck('valor', 'campo')->all())->toBe(['Tipo' => 'VIP']);

        $hoy = $this->propiedad->hoy();
        ($this->reservar)($ficha, $hoy->format('Y-m-d'), $hoy->addDays(2)->format('Y-m-d'));
        Livewire::actingAs($this->recepcion)->test(Calendario::class)->assertSeeHtml('#7c3aed');
    });
});

describe('horario de sincronización', function (): void {
    $mexico = fn (string $momento): Carbon => Carbon::parse($momento, 'America/Mexico_City');

    it('sin horario, siempre', function () use ($mexico): void {
        expect(($this->principal)()->dentroDelHorario($mexico('2026-11-04 03:00')))->toBeTrue();
    });

    it('la franja se mira en la hora de la propiedad', function () use ($mexico): void {
        $principal = ($this->principal)(['franja_desde' => '08:00', 'franja_hasta' => '10:00']);

        expect($principal->dentroDelHorario(Carbon::parse('2026-11-04 15:00', 'UTC')))->toBeTrue() // 09:00 en México
            ->and($principal->dentroDelHorario(Carbon::parse('2026-11-04 09:00', 'UTC')))->toBeFalse() // 03:00 en México
            ->and($principal->dentroDelHorario($mexico('2026-11-04 08:00')))->toBeTrue()
            ->and($principal->dentroDelHorario($mexico('2026-11-04 10:00')))->toBeFalse();
    });

    it('los días se miran en la hora de la propiedad', function (): void {
        // 2026-11-02 es lunes; a las 02:00 UTC del martes, en México todavía es lunes.
        $principal = ($this->principal)(['dias_semana' => 1]);

        expect($principal->dentroDelHorario(Carbon::parse('2026-11-03 02:00', 'UTC')))->toBeTrue()
            ->and($principal->dentroDelHorario(Carbon::parse('2026-11-03 12:00', 'UTC')))->toBeFalse();
    });

    it('una franja que cruza la medianoche es del día en que empieza', function () use ($mexico): void {
        $principal = ($this->principal)(['dias_semana' => 1, 'franja_desde' => '22:00', 'franja_hasta' => '06:00']);

        expect($principal->dentroDelHorario($mexico('2026-11-02 23:00')))->toBeTrue()
            ->and($principal->dentroDelHorario($mexico('2026-11-03 05:59')))->toBeTrue()
            ->and($principal->dentroDelHorario($mexico('2026-11-03 06:00')))->toBeFalse()
            ->and($principal->dentroDelHorario($mexico('2026-11-02 05:00')))->toBeFalse()
            ->and($principal->dentroDelHorario($mexico('2026-11-03 23:00')))->toBeFalse();
    });

    it('fuera de horario no toca aunque ya le tocara, y dentro sí', function () use ($mexico): void {
        $principal = ($this->principal)(['franja_desde' => '08:00', 'franja_hasta' => '10:00', 'proxima_en' => '2026-11-01 00:00:00']);

        expect($principal->toca($mexico('2026-11-04 03:00')))->toBeFalse()
            ->and($principal->toca($mexico('2026-11-04 09:00')))->toBeTrue();
    });

    it('el formulario guarda los días como máscara, y todos o ninguno es sin límite', function (): void {
        $principal = ($this->principal)();
        $propiedad = $principal->propiedad;

        Livewire::actingAs($this->admin)->test(EditPropiedad::class, ['record' => $propiedad->getRouteKey()])
            ->fillForm(['principal.dias_semana' => [1, 3], 'principal.franja_desde' => '22:00', 'principal.franja_hasta' => '06:00', 'principal.url_base' => 'https://principal.example.com/'])
            ->call('save')
            ->assertHasNoFormErrors();
        $principal->refresh();
        expect($principal->dias_semana)->toBe(0b101)
            ->and(substr((string) $principal->franja_desde, 0, 5))->toBe('22:00');

        Livewire::actingAs($this->admin)->test(EditPropiedad::class, ['record' => $propiedad->getRouteKey()])
            ->assertFormSet(['principal.dias_semana' => [1, 3]])
            ->fillForm(['principal.dias_semana' => [1, 2, 3, 4, 5, 6, 7], 'principal.url_base' => 'https://principal.example.com/'])
            ->call('save')
            ->assertHasNoFormErrors();
        expect($principal->fresh()->dias_semana)->toBeNull();
    });

    it('una franja a medias no se guarda', function (): void {
        $propiedad = ($this->principal)()->propiedad;

        Livewire::actingAs($this->admin)->test(EditPropiedad::class, ['record' => $propiedad->getRouteKey()])
            ->fillForm(['principal.franja_desde' => '22:00', 'principal.url_base' => 'https://principal.example.com/'])
            ->call('save')
            ->assertHasFormErrors(['principal.franja_hasta']);
    });
});

describe('regiones', function (): void {
    it('tiene los códigos ISO y caben en su columna', function (): void {
        expect(CatalogoRegiones::de('MX'))->toHaveCount(32)->toHaveKey('MX-ROO', 'Quintana Roo')
            ->and(CatalogoRegiones::de('us'))->toHaveCount(51)->toHaveKey('US-TX')
            ->and(CatalogoRegiones::de('CA'))->toHaveCount(13)
            ->and(CatalogoRegiones::de('ES'))->toHaveCount(19)
            ->and(CatalogoRegiones::de('ZZ'))->toBe([])
            ->and(CatalogoRegiones::de(null))->toBe([])
            ->and(CatalogoRegiones::nombre('MX-CMX'))->toBe('Ciudad de México')
            ->and(CatalogoRegiones::nombre('Texto libre'))->toBe('Texto libre')
            ->and(CatalogoRegiones::nombre(null))->toBeNull();
    });

    it('todos los estados de México tienen tasa de hospedaje', function (): void {
        $tasas = array_map(fn ($sugerencia) => $sugerencia->region, CatalogoIsh::todas());

        expect(array_values(array_diff(array_values(CatalogoRegiones::de('MX')), $tasas)))->toBe([]);
    });

    it('la propiedad la elige de la lista, o la escribe si su país no tiene', function (): void {
        $pagina = Livewire::actingAs($this->admin)->test(EditPropiedad::class, ['record' => $this->propiedad->getRouteKey()])
            ->fillForm(['subdivision' => 'MX-ROO'])
            ->call('save')
            ->assertHasNoFormErrors();
        expect($this->propiedad->fresh()->subdivision)->toBe('MX-ROO');

        $pagina->fillForm(['pais' => 'AR'])->assertFormSet(['subdivision' => null])
            ->fillForm(['subdivision' => 'Provincia de Buenos Aires'])
            ->call('save')
            ->assertHasNoFormErrors();
        expect($this->propiedad->fresh()->subdivision)->toBe('Provincia de Buenos Aires');
    });
});

describe('registro turístico', function (): void {
    it('avisa a 30 días y se quita al renovar o borrar', function (): void {
        $registro = RegistroTuristico::query()->create(['propiedad_id' => $this->propiedad->id, 'tipo' => 'RNT', 'numero' => '123', 'vigente_hasta' => now()->addDays(20)->toDateString()]);
        $abiertos = fn () => Aviso::query()->where('propiedad_id', $this->propiedad->id)->where('tipo', 'registro_por_vencer')->whereNull('resuelto_en');

        expect($abiertos()->count())->toBe(1);

        $registro->update(['vigente_hasta' => now()->addYear()->toDateString()]);
        expect($abiertos()->count())->toBe(0);

        $registro->update(['vigente_hasta' => now()->subDay()->toDateString()]);
        expect($abiertos()->count())->toBe(1)
            ->and($abiertos()->first()->mensaje())->toContain('30');

        $registro->delete();
        expect($abiertos()->count())->toBe(0);
    });

    it('la limpieza de cada noche lo revisa', function (): void {
        RegistroTuristico::query()->create(['propiedad_id' => $this->propiedad->id, 'tipo' => 'RNT', 'numero' => '123', 'vigente_hasta' => now()->addDays(40)->toDateString()]);
        expect(Aviso::query()->where('tipo', 'registro_por_vencer')->count())->toBe(0);

        $this->travel(15)->days();
        $this->artisan('alojaflow:limpiar')->assertSuccessful();

        expect(Aviso::query()->where('tipo', 'registro_por_vencer')->whereNull('resuelto_en')->count())->toBe(1);
    });

    it('en un documento, los de la propiedad y los de la unidad de esa reserva', function (): void {
        RegistroTuristico::query()->create(['propiedad_id' => $this->propiedad->id, 'tipo' => 'RNT', 'numero' => 'GENERAL']);
        RegistroTuristico::query()->create(['propiedad_id' => $this->propiedad->id, 'unidad_id' => $this->unidad->id, 'tipo' => 'VUT', 'numero' => 'DE-UNA']);
        RegistroTuristico::query()->create(['propiedad_id' => $this->propiedad->id, 'unidad_id' => $this->otra->id, 'tipo' => 'VUT', 'numero' => 'DE-OTRA']);
        RegistroTuristico::query()->create(['propiedad_id' => $this->propiedad->id, 'tipo' => 'Interno', 'numero' => 'OCULTO', 'mostrar' => false]);
        $hoy = $this->propiedad->hoy();
        $reserva = ($this->reservar)(($this->ficha)(), $hoy->format('Y-m-d'), $hoy->addDay()->format('Y-m-d'));

        expect(RegistrosTuristicos::visibles($this->propiedad)->pluck('numero')->all())->toBe(['GENERAL', 'DE-UNA', 'DE-OTRA'])
            ->and(RegistrosTuristicos::visibles($this->propiedad, $reserva)->pluck('numero')->all())->toBe(['GENERAL', 'DE-UNA']);
    });

    it('se enseña en la página pública con responsable y quejas', function (): void {
        $this->propiedad->update(['responsable' => 'Marta Ruiz', 'autoridad_quejas' => 'Profeco']);
        RegistroTuristico::query()->create(['propiedad_id' => $this->propiedad->id, 'tipo' => 'RNT', 'numero' => '04120001234']);

        $this->get(route('motor.buscar', ['codigo' => $this->propiedad->codigo]))
            ->assertOk()->assertSee('04120001234')->assertSee('Marta Ruiz')->assertSee('Profeco');
    });

    it('se da de alta desde el panel', function (): void {
        Livewire::actingAs($this->gerente)->test(CreateRegistroTuristico::class)
            ->fillForm(['propiedad_id' => $this->propiedad->id, 'unidad_id' => $this->unidad->id, 'tipo' => 'RNT', 'numero' => '04120001234', 'vigente_hasta' => now()->addYear()->format('Y-m-d')])
            ->call('create')
            ->assertHasNoFormErrors();

        $registro = RegistroTuristico::query()->sole();
        expect($registro->numero)->toBe('04120001234')->and($registro->unidad_id)->toBe($this->unidad->id)->and($registro->mostrar)->toBeTrue();
    });

    it('no admite la unidad de otra propiedad', function (): void {
        $tarifaAjena = Tarifa::query()->create(['propiedad_id' => $this->ajena->id, 'nombre' => 'Otra', 'precio_noche' => 100000, 'personas_incluidas' => 2, 'activa' => true]);
        $ajena = Unidad::query()->create(['propiedad_id' => $this->ajena->id, 'tarifa_id' => $tarifaAjena->id, 'codigo' => '9', 'nombre' => 'Ajena', 'capacidad' => 2, 'activa' => true]);

        Livewire::actingAs($this->admin)->test(CreateRegistroTuristico::class)
            ->fillForm(['propiedad_id' => $this->propiedad->id, 'unidad_id' => $ajena->id, 'tipo' => 'RNT', 'numero' => '1'])
            ->call('create');

        expect(RegistroTuristico::query()->count())->toBe(0);
    });

    it('no se administra sin el permiso de ajustes', function (): void {
        Livewire::actingAs($this->recepcion)->test(ListRegistrosTuristicos::class)->assertForbidden();
        Livewire::actingAs($this->admin)->test(ListRegistrosTuristicos::class)->assertOk();
    });
});

describe('base que se pone al día sola', function (): void {
    beforeEach(function (): void {
        File::ensureDirectoryExists(dirname(storage_path(Instalador::MARCA)));
    });

    it('sin instalar no se mira nada', function (): void {
        File::delete(storage_path(Instalador::MARCA));

        expect(app(EsquemaAlDia::class)->hayQueRevisar())->toBeFalse();
    });

    it('instalada y con otra versión hay que revisar; después no', function (): void {
        File::put(storage_path(Instalador::MARCA), '{}');
        $esquema = app(EsquemaAlDia::class);

        expect($esquema->hayQueRevisar())->toBeTrue()
            ->and($esquema->pendientes())->toBe([]);

        $esquema->ponerAlDia();

        expect($esquema->hayQueRevisar())->toBeFalse();

        File::put(storage_path('app/private/esquema-version.txt'), '0.0.1');
        expect($esquema->hayQueRevisar())->toBeTrue();
    });

    // En «staging» no aplica la excepción de las pruebas y tampoco se rehacen las cachés de arranque, que caerían en bootstrap/cache del equipo.
    it('una visita sigue donde iba, y queda al día', function (): void {
        File::put(storage_path(Instalador::MARCA), '{}');
        File::put(storage_path('app/private/esquema-version.txt'), '0.0.1');
        $this->app['env'] = 'staging';

        $this->withoutMiddleware(ProgramadorPorVisitas::class)->get('/up')->assertOk();

        expect(app(EsquemaAlDia::class)->hayQueRevisar())->toBeFalse();
    });

    it('un formulario va al inicio, porque ese envío no se procesó', function (): void {
        File::put(storage_path(Instalador::MARCA), '{}');
        File::put(storage_path('app/private/esquema-version.txt'), '0.0.1');
        $this->app['env'] = 'staging';

        $this->withoutMiddleware(ProgramadorPorVisitas::class)->post('/login')->assertRedirect(url('/'));

        expect(app(EsquemaAlDia::class)->hayQueRevisar())->toBeFalse();
    });

    it('si acaba de fallar, lo explica y no lo intenta en cada visita', function (): void {
        File::put(storage_path(Instalador::MARCA), '{}');
        File::put(storage_path('app/private/esquema-version.txt'), '0.0.1');
        File::put(storage_path('app/private/esquema-intento.txt'), '1.0.17');
        Illuminate\Support\Facades\DB::table('migrations')->where('migration', 'like', '%registros_franja%')->delete();
        $this->app['env'] = 'staging';

        $this->withoutMiddleware(ProgramadorPorVisitas::class)->withHeader('Accept-Language', 'es')->get('/up')
            ->assertStatus(503)
            ->assertSee(__('ui.errores.esquema_titulo', [], 'es'));

        expect(app(EsquemaAlDia::class)->hayQueRevisar())->toBeTrue()
            ->and(App\Models\RegistroTuristico::query()->count())->toBe(0);
    });

    it('no se fía de la configuración en caché, que diría la versión anterior', function (): void {
        File::put(storage_path(Instalador::MARCA), '{}');
        $esquema = app(EsquemaAlDia::class);
        $esquema->marcarAlDia();

        config(['alojaflow.version' => '9.9.9']);

        expect($esquema->hayQueRevisar())->toBeFalse();
    });
});

describe('colores por usuario', function (): void {
    it('solo pasan colores válidos y la letra se elige sola', function (): void {
        $this->admin->update(['colores_estado' => [
            'confirmada' => '#1e3a8a',
            'sin_confirmar' => '#fde047',
            'en_casa' => 'red;}body{display:none',
            'otro' => '#000000',
        ]]);

        $css = ColoresEstado::css($this->admin->fresh());

        expect($css)->toContain('.af-barra--confirmada{background:#1e3a8a;color:#ffffff}')
            ->toContain('.af-barra--sin-confirmar{background:#fde047;color:#0b1f2a}')
            ->not->toContain('display:none')
            ->not->toContain('otro')
            ->and(ColoresEstado::css(null))->toBe('');
    });

    it('cada quien los guarda en su perfil', function (): void {
        Livewire::actingAs($this->recepcion)->test(EditarPerfil::class)
            ->fillForm(['colores_estado.confirmada' => '#7c3aed'])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($this->recepcion->fresh()->colores_estado['confirmada'])->toBe('#7c3aed');
    });

    it('rechaza lo que no es un color', function (): void {
        Livewire::actingAs($this->recepcion)->test(EditarPerfil::class)
            ->fillForm(['colores_estado.confirmada' => 'rojo'])
            ->call('save')
            ->assertHasFormErrors(['colores_estado.confirmada']);
    });
});

describe('catálogo de impuestos por estado', function (): void {
    it('propone el estado de la propiedad y su tasa', function (): void {
        $this->propiedad->update(['paquete_pais' => App\Enums\PaquetePais::Mexico, 'subdivision' => 'MX-GRO']);

        Livewire::actingAs($this->admin)->test(App\Filament\Resources\Impuestos\Pages\ListImpuestos::class)
            ->mountAction('catalogo')
            ->assertActionDataSet(['region' => 'Guerrero', 'tasa' => 4.0]);
    });

    it('sin región elegida, no supone ninguna', function (): void {
        $this->propiedad->update(['paquete_pais' => App\Enums\PaquetePais::Mexico]);

        Livewire::actingAs($this->admin)->test(App\Filament\Resources\Impuestos\Pages\ListImpuestos::class)
            ->mountAction('catalogo')
            ->assertActionDataSet(['region' => null, 'tasa' => null]);
    });
});
