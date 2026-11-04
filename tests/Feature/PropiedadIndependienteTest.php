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
use App\Enums\TipoEventoReserva;
use App\Enums\TipoReserva;
use App\Filament\Pages\Hoy;
use App\Filament\Resources\Huespedes\Pages\CreateHuesped;
use App\Filament\Resources\Huespedes\Pages\EditHuesped;
use App\Filament\Resources\Huespedes\Pages\ListHuespedes;
use App\Filament\Resources\Propiedades\Pages\CreatePropiedad;
use App\Filament\Resources\Propiedades\Pages\EditPropiedad;
use App\Filament\Resources\Reservas\Pages\CreateReserva;
use App\Filament\Resources\Reservas\Pages\EditReserva;
use App\Filament\Resources\Reservas\Pages\ListReservas;
use App\Filament\Resources\Reservas\Pages\ViewReserva;
use App\Filament\Resources\Reservas\RelationManagers\PagosRelationManager;
use App\Filament\Resources\Tarifas\Pages\CreateTarifa;
use App\Filament\Resources\Unidades\Pages\CreateUnidad;
use App\Filament\Resources\Unidades\Pages\EditUnidad;
use App\Filament\Resources\Unidades\Pages\ListUnidades;
use App\Models\Huesped;
use App\Models\Principal;
use App\Models\Propiedad;
use App\Models\Reserva;
use App\Models\Tarifa;
use App\Models\Unidad;
use App\Models\User;
use App\Reservas\Cotizador;
use App\Reservas\GestorReservas;
use App\Reservas\ReservaRechazada;
use App\Seguridad\Acceso;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesYPermisosSeeder;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesYPermisosSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('app'));

    $this->propiedad = Propiedad::query()->create([
        'nombre' => 'Posada Propia', 'codigo' => 'propia', 'modo' => ModoPropiedad::Nativa,
        'zona_horaria' => 'America/Mexico_City', 'moneda' => 'MXN',
    ]);
    $this->tarifa = Tarifa::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Doble', 'precio_noche' => 100000]);
    $this->unidad = Unidad::query()->create([
        'propiedad_id' => $this->propiedad->id, 'tarifa_id' => $this->tarifa->id, 'codigo' => '101', 'nombre' => '101', 'capacidad' => 2,
    ]);
    $this->huesped = Huesped::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Ana', 'apellido1' => 'Ejemplo']);
    $this->gestor = app(GestorReservas::class);
    $this->hoy = $this->propiedad->hoy();
    $this->datos = fn (int $desde = 1, int $hasta = 3, array $mas = []): array => $mas + [
        'unidad_id' => $this->unidad->id,
        'titular_id' => $this->huesped->id,
        'llegada' => $this->hoy->addDays($desde)->format('Y-m-d'),
        'salida' => $this->hoy->addDays($hasta)->format('Y-m-d'),
        'personas' => 2,
        'total' => 200000,
    ];
});

function administrador(): User
{
    return User::factory()->create(['es_administrador' => true, 'activo' => true, 'idioma' => 'es']);
}

function usuarioDe(Propiedad $propiedad, string $rol): User
{
    $usuario = User::factory()->create(['es_administrador' => false, 'activo' => true, 'idioma' => 'es']);
    Acceso::asignarRoles($usuario, [['propiedad_id' => $propiedad->id, 'rol' => $rol]]);

    return $usuario;
}

it('crea una reserva propia con número, noches exclusivas y evento', function () {
    $reserva = $this->gestor->crear($this->propiedad, ($this->datos)());

    expect($reserva->numero)->toBe('1')
        ->and($reserva->sincronizada)->toBeFalse()
        ->and($reserva->noches)->toBe(2)
        ->and($reserva->pendiente)->toBe(200000)
        ->and($reserva->tarifa_nombre)->toBe('Doble')
        ->and($reserva->nochesOcupadas()->sum('importe'))->toEqual(200000)
        ->and($reserva->nochesOcupadas()->where('exclusiva', 1)->count())->toBe(2)
        ->and($reserva->eventos()->first()->tipo)->toBe(TipoEventoReserva::Creada)
        ->and($reserva->huespedes()->pluck('huespedes.id')->all())->toBe([$this->huesped->id]);

    $otra = $this->gestor->crear($this->propiedad, ($this->datos)(5, 6));
    expect($otra->numero)->toBe('2');
});

it('la base de datos rechaza la doble reserva y deja libre la noche de salida', function () {
    $this->gestor->crear($this->propiedad, ($this->datos)(1, 3));

    expect(fn () => $this->gestor->crear($this->propiedad, ($this->datos)(2, 4)))
        ->toThrow(ReservaRechazada::class, __('ui.reservas.error_ocupada'));
    expect(Reserva::query()->count())->toBe(1);
    // El consecutivo se deshace con la transacción: no quedan huecos.
    expect($this->propiedad->refresh()->consecutivo_reserva)->toBe(1);

    // Llegar el día que otro se va no es un choque.
    expect($this->gestor->crear($this->propiedad, ($this->datos)(3, 4))->numero)->toBe('2');
});

it('comprueba capacidad, fechas, unidad y huésped de otra propiedad', function () {
    $otra = Propiedad::query()->create(['nombre' => 'Otra', 'codigo' => 'otra', 'modo' => ModoPropiedad::Nativa, 'zona_horaria' => 'UTC', 'moneda' => 'MXN']);
    $unidadAjena = Unidad::query()->create(['propiedad_id' => $otra->id, 'codigo' => 'X', 'nombre' => 'X']);
    $huespedAjeno = Huesped::query()->create(['propiedad_id' => $otra->id, 'nombre' => 'Otro']);

    expect(fn () => $this->gestor->crear($this->propiedad, ($this->datos)(1, 2, ['personas' => 3])))->toThrow(ReservaRechazada::class);
    expect(fn () => $this->gestor->crear($this->propiedad, ($this->datos)(3, 2)))->toThrow(ReservaRechazada::class);
    expect(fn () => $this->gestor->crear($this->propiedad, ($this->datos)(1, 2, ['unidad_id' => $unidadAjena->id])))->toThrow(ReservaRechazada::class);
    expect(fn () => $this->gestor->crear($this->propiedad, ($this->datos)(1, 2, ['titular_id' => $huespedAjeno->id])))->toThrow(ReservaRechazada::class);
    expect(Reserva::query()->count())->toBe(0);
});

it('una propiedad subordinada no crea ni cambia reservas por el gestor', function () {
    $subordinada = Propiedad::query()->create(['nombre' => 'Sub', 'codigo' => 'sub', 'zona_horaria' => 'UTC', 'moneda' => 'MXN']);
    expect(fn () => $this->gestor->crear($subordinada, ($this->datos)()))->toThrow(LogicException::class);

    $reserva = $this->gestor->crear($this->propiedad, ($this->datos)());
    DB::table('reservas')->where('id', $reserva->id)->update(['sincronizada' => true]);
    expect(fn () => $this->gestor->cancelar($reserva))->toThrow(LogicException::class);
});

it('al cambiar fechas libera las noches viejas y revisa la versión', function () {
    $reserva = $this->gestor->crear($this->propiedad, ($this->datos)(1, 3));
    $otra = $this->gestor->crear($this->propiedad, ($this->datos)(5, 7));

    expect(fn () => $this->gestor->modificar($reserva, ($this->datos)(4, 6), $reserva->version))->toThrow(ReservaRechazada::class);
    expect($reserva->refresh()->llegada->format('Y-m-d'))->toBe($this->hoy->addDay()->format('Y-m-d'));

    $cambiada = $this->gestor->modificar($reserva, ($this->datos)(3, 5), 1);
    expect($cambiada->version)->toBe(2)
        ->and($cambiada->nochesOcupadas()->orderBy('fecha')->pluck('fecha')->map->format('Y-m-d')->all())
        ->toBe([$this->hoy->addDays(3)->format('Y-m-d'), $this->hoy->addDays(4)->format('Y-m-d')]);

    // Otra persona editó con la versión vieja.
    expect(fn () => $this->gestor->modificar($reserva, ($this->datos)(3, 4), 1))
        ->toThrow(ReservaRechazada::class, __('ui.reservas.error_version'));
    expect($otra->refresh()->version)->toBe(1);
});

it('cancelar libera las noches y reactivar las vuelve a ocupar si siguen libres', function () {
    $reserva = $this->gestor->crear($this->propiedad, ($this->datos)(1, 3));

    $this->gestor->cancelar($reserva);
    expect($reserva->refresh()->estado)->toBe(EstadoReserva::Cancelada)
        ->and($reserva->nochesOcupadas()->count())->toBe(0);

    $ocupa = $this->gestor->crear($this->propiedad, ($this->datos)(2, 3));
    expect(fn () => $this->gestor->reactivar($reserva))->toThrow(ReservaRechazada::class);
    expect($reserva->refresh()->estado)->toBe(EstadoReserva::Cancelada);

    $this->gestor->cancelar($ocupa);
    $this->gestor->reactivar($reserva);
    expect($reserva->refresh()->estado)->toBe(EstadoReserva::SinConfirmar)
        ->and($reserva->nochesOcupadas()->count())->toBe(2);
});

it('registra entrada y salida con sus reglas', function () {
    $futura = $this->gestor->crear($this->propiedad, ($this->datos)(5, 6));
    expect(fn () => $this->gestor->registrarEntrada($futura))->toThrow(ReservaRechazada::class, __('ui.reservas.error_entrada_futura'));

    $reserva = $this->gestor->crear($this->propiedad, ($this->datos)(0, 2, ['estado' => EstadoReserva::SinConfirmar->value]));
    expect(fn () => $this->gestor->registrarSalida($reserva))->toThrow(ReservaRechazada::class);

    $this->gestor->registrarEntrada($reserva);
    expect($reserva->refresh()->estaEnCasa())->toBeTrue()
        ->and($reserva->estado)->toBe(EstadoReserva::Confirmada);
    expect(fn () => $this->gestor->cancelar($reserva))->toThrow(ReservaRechazada::class);

    $this->gestor->registrarSalida($reserva);
    expect($reserva->refresh()->salida_registrada_en)->not->toBeNull();

    $this->gestor->deshacerRegistro($reserva);
    expect($reserva->refresh()->estaEnCasa())->toBeTrue();
});

it('los pagos suman en pagado, admiten devoluciones y se pueden quitar', function () {
    $reserva = $this->gestor->crear($this->propiedad, ($this->datos)());
    $hoy = CarbonImmutable::now();

    $pago = $this->gestor->registrarPago($reserva, 150000, MetodoPago::Tarjeta, $hoy, 'REF-1');
    expect($reserva->refresh()->pagado)->toBe(150000)->and($reserva->pendiente)->toBe(50000);

    $this->gestor->registrarPago($reserva, -20000, MetodoPago::Efectivo, $hoy);
    expect($reserva->refresh()->pagado)->toBe(130000);

    $this->gestor->quitarPago($pago);
    expect($reserva->refresh()->pagado)->toBe(-20000);
    expect(fn () => $this->gestor->registrarPago($reserva, 0, MetodoPago::Efectivo, $hoy))->toThrow(ReservaRechazada::class);
});

it('un bloqueo ocupa noches sin huésped ni dinero', function () {
    $bloqueo = $this->gestor->crear($this->propiedad, [
        'tipo' => TipoReserva::Bloqueo->value, 'unidad_id' => $this->unidad->id,
        'llegada' => $this->hoy->addDay()->format('Y-m-d'), 'salida' => $this->hoy->addDays(3)->format('Y-m-d'), 'origen_texto' => 'Pintura',
    ]);

    expect($bloqueo->titular_id)->toBeNull()->and($bloqueo->total)->toBe(0)->and($bloqueo->nochesOcupadas()->count())->toBe(2);
    expect(fn () => $this->gestor->crear($this->propiedad, ($this->datos)(1, 2)))->toThrow(ReservaRechazada::class);
    expect(fn () => $this->gestor->registrarPago($bloqueo, 100, MetodoPago::Efectivo, CarbonImmutable::now()))->toThrow(ReservaRechazada::class);
});

it('cotiza cada noche con su temporada; si se pisan, vale la que empieza más tarde', function () {
    $this->tarifa->temporadas()->createMany([
        ['nombre' => 'Alta', 'desde' => '2030-01-02', 'hasta' => '2030-01-10', 'precio_noche' => 150000],
        ['nombre' => 'Puente', 'desde' => '2030-01-03', 'hasta' => '2030-01-03', 'precio_noche' => 300000],
    ]);

    $cotizacion = Cotizador::cotizar($this->unidad, CarbonImmutable::parse('2030-01-01'), CarbonImmutable::parse('2030-01-05'));

    expect($cotizacion->noches)->toBe([100000, 150000, 300000, 150000])->and($cotizacion->total)->toBe(700000);

    $this->unidad->update(['tarifa_id' => null]);
    expect(Cotizador::cotizar($this->unidad->refresh(), CarbonImmutable::parse('2030-01-01'), CarbonImmutable::parse('2030-01-05')))->toBeNull();
});

it('al crear una propiedad se elige el modo; la independiente no tiene principal', function () {
    $this->actingAs(administrador());

    Livewire::test(CreatePropiedad::class)
        ->fillForm([
            'modo' => ModoPropiedad::Nativa->value, 'nombre' => 'Cabañas del Lago', 'codigo' => 'lago',
            'zona_horaria' => 'America/Mexico_City', 'moneda' => 'MXN', 'idioma' => 'es', 'paquete_pais' => 'mexico', 'color' => '#0f766e',
        ])
        ->assertFormFieldIsHidden('principal.url_base')
        ->call('create')
        ->assertHasNoFormErrors();

    $nueva = Propiedad::query()->where('codigo', 'lago')->firstOrFail();
    expect($nueva->modo)->toBe(ModoPropiedad::Nativa)->and($nueva->principal)->toBeNull();
});

it('el modo es obligatorio y una subordinada sigue pidiendo su principal', function () {
    $this->actingAs(administrador());
    $base = ['nombre' => 'Hotel', 'codigo' => 'hotel', 'zona_horaria' => 'UTC', 'moneda' => 'MXN', 'idioma' => 'es', 'paquete_pais' => 'mexico', 'color' => '#0f766e'];

    Livewire::test(CreatePropiedad::class)->fillForm($base)->call('create')->assertHasFormErrors(['modo' => 'required']);

    Livewire::test(CreatePropiedad::class)->fillForm($base + ['modo' => ModoPropiedad::Subordinada->value])
        ->call('create')->assertHasFormErrors(['principal.url_base' => 'required']);

    Livewire::test(CreatePropiedad::class)
        ->fillForm($base + [
            'modo' => ModoPropiedad::Subordinada->value,
            'principal' => ['url_base' => 'https://hotel.example/hd/', 'usuario' => 'u', 'contrasena' => 'c', 'frecuencia_minutos' => 30, 'metodo' => 'post'],
        ])
        ->call('create')->assertHasNoFormErrors();
    expect(Principal::query()->where('url_base', 'https://hotel.example/hd/')->exists())->toBeTrue();
});

it('borrar una independiente pide escribir su código y se lleva sus datos', function () {
    $this->actingAs(administrador());
    $this->gestor->crear($this->propiedad, ($this->datos)());

    Livewire::test(EditPropiedad::class, ['record' => $this->propiedad->getKey()])
        ->callAction(DeleteAction::class, ['confirmacion' => 'otro'])
        ->assertHasActionErrors(['confirmacion']);
    expect(Propiedad::query()->whereKey($this->propiedad->id)->exists())->toBeTrue();

    Livewire::test(EditPropiedad::class, ['record' => $this->propiedad->getKey()])
        ->callAction(DeleteAction::class, ['confirmacion' => 'propia'])
        ->assertHasNoActionErrors();
    expect(Propiedad::query()->whereKey($this->propiedad->id)->exists())->toBeFalse()
        ->and(Reserva::query()->count())->toBe(0)
        ->and(Unidad::query()->count())->toBe(0)
        ->and(Huesped::query()->count())->toBe(0);
});

it('crea unidad, tarifa con temporada y huésped con contacto normalizado desde los formularios', function () {
    $this->actingAs(administrador());

    Livewire::test(CreateTarifa::class)
        ->fillForm(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Suite', 'precio_noche' => '1500.50'])
        ->set('data.temporadas', [['nombre' => 'Alta', 'desde' => '2030-01-01', 'hasta' => '2030-01-31', 'precio_noche' => '2000']])
        ->call('create')->assertHasNoFormErrors();
    $tarifa = Tarifa::query()->where('nombre', 'Suite')->firstOrFail();
    expect($tarifa->precio_noche)->toBe(150050)->and($tarifa->temporadas()->first()->precio_noche)->toBe(200000);

    Livewire::test(CreateUnidad::class)
        ->fillForm(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Suite 1', 'capacidad' => 3, 'tarifa_id' => $tarifa->id])
        ->call('create')->assertHasNoFormErrors();
    $unidad = Unidad::query()->where('nombre', 'Suite 1')->firstOrFail();
    expect($unidad->codigo)->toBe('Suite 1')->and($unidad->tarifa_id)->toBe($tarifa->id);

    Livewire::test(CreateUnidad::class)
        ->fillForm(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Suite 1', 'capacidad' => 3])
        ->call('create')->assertHasFormErrors(['nombre' => 'unique']);

    Livewire::test(CreateHuesped::class)
        ->fillForm(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Luis', 'apellido1' => 'Prueba', 'documento_numero' => 'ab-12 3'])
        ->set('data.contactos', [['tipo' => 'telefono', 'valor' => '+52 (55) 5550-0101']])
        ->call('create')->assertHasNoFormErrors();
    $huesped = Huesped::query()->where('nombre', 'Luis')->firstOrFail();
    expect($huesped->contactos()->first()->valor_normalizado)->toBe('+525555500101')
        ->and($huesped->documento_numero)->toBe('ab-12 3')
        ->and(DB::table('huespedes')->where('id', $huesped->id)->value('documento_numero'))->not->toContain('ab-12')
        ->and(DB::table('huespedes')->where('id', $huesped->id)->value('documento_numero_hmac'))->not->toBeNull();
});

it('crea y edita una reserva desde el formulario, con el importe en la unidad menor', function () {
    $this->actingAs(administrador());
    $llegada = $this->hoy->addDays(10)->format('Y-m-d');
    $salida = $this->hoy->addDays(12)->format('Y-m-d');

    Livewire::withQueryParams(['unidad' => $this->unidad->id, 'llegada' => $llegada, 'salida' => $salida])
        ->test(CreateReserva::class)
        ->assertFormSet(['propiedad_id' => $this->propiedad->id, 'unidad_id' => $this->unidad->id, 'total' => '2000.00'])
        ->fillForm(['titular_id' => $this->huesped->id, 'total' => '1800.50'])
        ->call('create')->assertHasNoFormErrors();

    $reserva = Reserva::query()->firstOrFail();
    expect($reserva->total)->toBe(180050)->and($reserva->tarifa_nombre)->toBe('Doble');

    Livewire::test(EditReserva::class, ['record' => $reserva->getKey()])
        ->assertFormSet(['total' => '1800.50'])
        ->fillForm(['total' => '2100'])
        ->call('save')->assertHasNoFormErrors();
    expect($reserva->refresh()->total)->toBe(210000)->and($reserva->version)->toBe(2);
});

it('el formulario muestra el choque en el campo de la unidad', function () {
    $this->actingAs(administrador());
    $this->gestor->crear($this->propiedad, ($this->datos)(1, 3));

    Livewire::test(CreateReserva::class)
        ->fillForm([
            'propiedad_id' => $this->propiedad->id, 'unidad_id' => $this->unidad->id, 'titular_id' => $this->huesped->id,
            'llegada' => $this->hoy->addDay()->format('Y-m-d'), 'salida' => $this->hoy->addDays(2)->format('Y-m-d'),
            'personas' => 1, 'total' => '100', 'estado' => 'confirmada',
        ])
        ->call('create')->assertHasFormErrors(['unidad_id']);
    expect(Reserva::query()->count())->toBe(1);
});

it('la ficha de la reserva muestra el cambio en cuanto se registra la entrada', function () {
    $this->actingAs(administrador());
    $reserva = $this->gestor->crear($this->propiedad, ($this->datos)(0, 2));

    Livewire::test(ViewReserva::class, ['record' => $reserva->getKey()])
        ->assertActionVisible('registrarEntrada')->assertActionHidden('registrarSalida')
        ->callAction('registrarEntrada')
        ->assertActionHidden('registrarEntrada')->assertActionVisible('registrarSalida')
        ->assertActionVisible('deshacerRegistro');
    expect($reserva->refresh()->estaEnCasa())->toBeTrue();
});

it('registra pagos desde la reserva', function () {
    $this->actingAs(administrador());
    $reserva = $this->gestor->crear($this->propiedad, ($this->datos)());

    Livewire::test(PagosRelationManager::class, ['ownerRecord' => $reserva, 'pageClass' => ViewReserva::class])
        ->callTableAction('registrarPago', data: ['importe' => '500.25', 'fecha' => $this->hoy->format('Y-m-d'), 'metodo' => 'tarjeta', 'referencia' => 'A1'])
        ->assertHasNoTableActionErrors();

    expect($reserva->refresh()->pagado)->toBe(50025);
});

it('recepción gestiona reservas de su propiedad pero no el catálogo ni otras propiedades', function () {
    $otra = Propiedad::query()->create(['nombre' => 'Ajena', 'codigo' => 'ajena', 'modo' => ModoPropiedad::Nativa, 'zona_horaria' => 'UTC', 'moneda' => 'MXN']);
    $unidadAjena = Unidad::query()->create(['propiedad_id' => $otra->id, 'codigo' => 'X', 'nombre' => 'X', 'capacidad' => 2]);
    $huespedAjeno = Huesped::query()->create(['propiedad_id' => $otra->id, 'nombre' => 'Otro']);
    $reservaAjena = (new GestorReservas())->crear($otra, [
        'unidad_id' => $unidadAjena->id, 'titular_id' => $huespedAjeno->id, 'personas' => 1,
        'llegada' => $this->hoy->format('Y-m-d'), 'salida' => $this->hoy->addDay()->format('Y-m-d'),
    ]);

    $recepcion = usuarioDe($this->propiedad, 'recepcion');
    $this->actingAs($recepcion);

    $this->get('/reservas/create')->assertOk();
    $this->get('/huespedes/create')->assertOk();
    $this->get('/unidades')->assertForbidden();
    $this->get('/tarifas')->assertForbidden();
    $this->get('/reservas/'.$reservaAjena->id)->assertNotFound();

    Livewire::test(CreateReserva::class)
        ->set('data.propiedad_id', $otra->id)
        ->fillForm([
            'propiedad_id' => $otra->id, 'unidad_id' => $unidadAjena->id, 'titular_id' => $huespedAjeno->id,
            'llegada' => $this->hoy->addDays(3)->format('Y-m-d'), 'salida' => $this->hoy->addDays(4)->format('Y-m-d'),
            'personas' => 1, 'total' => '100', 'estado' => 'confirmada',
        ])
        ->call('create')
        ->assertHasFormErrors(['propiedad_id']);
    expect(Reserva::query()->where('propiedad_id', $otra->id)->count())->toBe(1);

    // Tampoco por una llamada directa a la pantalla Hoy.
    Livewire::test(Hoy::class)->call('registrarEntrada', $reservaAjena->id)->assertNotFound();
    expect($reservaAjena->refresh()->entrada_registrada_en)->toBeNull();
});

it('el gerente gestiona el catálogo; limpieza y contador no escriben', function () {
    $this->actingAs(usuarioDe($this->propiedad, 'gerente'));
    $this->get('/unidades')->assertOk();
    $this->get('/tarifas/create')->assertOk();

    foreach (['limpieza', 'contador'] as $rol) {
        $this->actingAs(usuarioDe($this->propiedad, $rol));
        $this->get('/reservas/create')->assertForbidden();
        $this->get('/unidades')->assertForbidden();
    }
});

it('en una subordinada las reservas siguen siendo de solo consulta', function () {
    $subordinada = Propiedad::query()->create(['nombre' => 'Sub', 'codigo' => 'sub', 'zona_horaria' => 'UTC', 'moneda' => 'MXN']);
    $subordinada->principal()->create(['url_base' => 'https://sub.example/hd/', 'usuario' => 'u', 'contrasena' => 'c']);
    $this->actingAs(usuarioDe($subordinada, 'gerente'));

    $this->get('/reservas/create')->assertForbidden();
    $this->get('/huespedes/create')->assertForbidden();
    $this->get('/tarifas')->assertForbidden();
    $this->get('/unidades')->assertOk();
    $this->get('/unidades/create')->assertForbidden();
});

it('una reserva subordinada no ofrece editar, cancelar ni registrar, ni siquiera al administrador', function () {
    $subordinada = Propiedad::query()->create(['nombre' => 'Sub', 'codigo' => 'sub', 'zona_horaria' => 'UTC', 'moneda' => 'MXN']);
    $unidad = Unidad::query()->create(['propiedad_id' => $subordinada->id, 'codigo' => 'S1', 'nombre' => 'S1', 'sincronizada' => true]);
    $reserva = Reserva::query()->create([
        'propiedad_id' => $subordinada->id, 'unidad_id' => $unidad->id, 'tipo' => TipoReserva::Reserva, 'estado' => EstadoReserva::Confirmada,
        'numero' => '1/2030', 'codigo_publico' => 'ABCDEFGH23', 'llegada' => $this->hoy->format('Y-m-d'), 'salida' => $this->hoy->addDay()->format('Y-m-d'),
        'moneda' => 'MXN', 'sincronizada' => true,
    ]);
    $this->actingAs(administrador());

    Livewire::test(ViewReserva::class, ['record' => $reserva->getKey()])
        ->assertActionHidden(EditAction::class)
        ->assertActionHidden('registrarEntrada')
        ->assertActionHidden('cancelar');
    $this->get('/reservas/'.$reserva->id.'/edit')->assertForbidden();
    Livewire::test(ListReservas::class)->assertTableActionHidden(EditAction::class, $reserva);
    Livewire::test(ListUnidades::class)->assertTableActionHidden(EditAction::class, $unidad);
});

it('quien solo ve subordinadas no tiene botones de alta', function () {
    $subordinada = Propiedad::query()->create(['nombre' => 'Sub', 'codigo' => 'sub', 'zona_horaria' => 'UTC', 'moneda' => 'MXN']);
    $this->actingAs(usuarioDe($subordinada, 'gerente'));

    Livewire::test(ListReservas::class)->assertActionHidden(CreateAction::class);
    Livewire::test(ListHuespedes::class)->assertActionHidden(CreateAction::class);
    Livewire::test(ListUnidades::class)->assertActionHidden(CreateAction::class);
});

it('una unidad o un huésped con reservas no se pueden borrar', function () {
    $this->actingAs(administrador());
    $this->gestor->crear($this->propiedad, ($this->datos)());

    Livewire::test(EditUnidad::class, ['record' => $this->unidad->getKey()])->assertActionHidden(DeleteAction::class);
    Livewire::test(EditHuesped::class, ['record' => $this->huesped->getKey()])->assertActionHidden(DeleteAction::class);

    $libre = Unidad::query()->create(['propiedad_id' => $this->propiedad->id, 'codigo' => '999', 'nombre' => '999']);
    Livewire::test(EditUnidad::class, ['record' => $libre->getKey()])->callAction(DeleteAction::class);
    expect(Unidad::query()->whereKey($libre->id)->exists())->toBeFalse();
});

it('los errores de validación salen en español', function () {
    $this->actingAs(administrador());

    expect(__('validation.required', ['attribute' => 'nombre']))->toBe('Nombre es obligatorio.');

    Livewire::withQueryParams([])->test(CreateReserva::class)
        ->fillForm(['propiedad_id' => $this->propiedad->id, 'llegada' => null])
        ->call('create')
        ->assertSee('es obligatorio');
});

it('las pantallas operativas funcionan con una propiedad independiente', function (string $ruta) {
    $this->gestor->crear($this->propiedad, ($this->datos)(0, 2));

    $this->actingAs(administrador())->get($ruta)->assertOk();
})->with(['/', '/hoy', '/calendario', '/disponibilidad', '/indicadores', '/reservas', '/huespedes', '/unidades', '/tarifas', '/propiedades', '/reservas/create', '/unidades/create', '/tarifas/create', '/huespedes/create']);

it('la pantalla Hoy registra la entrada y el calendario abre una reserva nueva', function () {
    $reserva = $this->gestor->crear($this->propiedad, ($this->datos)(0, 2));
    $this->actingAs(usuarioDe($this->propiedad, 'recepcion'));

    Livewire::test(Hoy::class)->assertSee(__('ui.reservas.registrar_entrada'))->call('registrarEntrada', $reserva->id);
    expect($reserva->refresh()->estaEnCasa())->toBeTrue();

    $this->get('/calendario')->assertOk()->assertSee('/reservas/create?unidad='.$this->unidad->id, false);
    $this->get('/hoy')->assertDontSee(__('ui.comun.solo_consulta'));
});
