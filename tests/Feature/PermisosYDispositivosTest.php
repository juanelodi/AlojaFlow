<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

use App\Enums\EstadoReserva;
use App\Enums\ModoPropiedad;
use App\Filament\Pages\Calendario;
use App\Filament\Pages\Hoy;
use App\Filament\Pages\SesionesDispositivos;
use App\Filament\Resources\Auditoria\Pages\ListRegistrosAuditoria;
use App\Filament\Resources\Huespedes\HuespedResource;
use App\Filament\Resources\Reservas\ReservaResource;
use App\Filament\Resources\Roles\Pages\CreateRol;
use App\Filament\Resources\Roles\Pages\ListRoles;
use App\Filament\Resources\Roles\RolResource;
use App\Filament\Resources\Usuarios\Pages\EditUsuario;
use App\Filament\Resources\Usuarios\Pages\ListUsuarios;
use App\Models\AccesoUsuario;
use App\Models\DispositivoRecordado;
use App\Models\Huesped;
use App\Models\Propiedad;
use App\Models\RegistroAuditoria;
use App\Models\Reserva;
use App\Models\Unidad;
use App\Models\User;
use App\Reservas\GestorReservas;
use App\Seguridad\Acceso;
use App\Seguridad\Dispositivos;
use App\Seguridad\Limites;
use App\Servicios\Operacion;
use App\Support\Ajustes;
use Database\Seeders\RolesYPermisosSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    $this->seed(RolesYPermisosSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('app'));
    $this->propiedad = Propiedad::query()->create(['nombre' => 'Casa', 'codigo' => 'casa', 'modo' => ModoPropiedad::Nativa, 'zona_horaria' => 'America/Mexico_City', 'moneda' => 'MXN', 'pais' => 'MX', 'idioma' => 'es']);
    $this->unidad = Unidad::query()->create(['propiedad_id' => $this->propiedad->id, 'codigo' => 'U1', 'nombre' => 'Uno', 'capacidad' => 2, 'orden' => 1]);
    $this->titular = Huesped::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Zacarías']);
    $this->usuario = User::query()->create(['name' => 'Ana', 'email' => 'ana@example.com', 'password' => Hash::make('contrasena-de-prueba'), 'activo' => true]);
    Acceso::asignarRoles($this->usuario, [['propiedad_id' => $this->propiedad->id, 'rol' => 'recepcion']]);
    $this->reservaDe = function (?int $autor, int $dias = 10): Reserva {
        $llegada = $this->propiedad->hoy()->addDays($dias);
        $reserva = app(GestorReservas::class)->crear($this->propiedad, [
            'unidad_id' => $this->unidad->id, 'titular_id' => $this->titular->id, 'llegada' => $llegada->format('Y-m-d'), 'salida' => $llegada->addDays(2)->format('Y-m-d'),
            'personas' => 2, 'estado' => EstadoReserva::Confirmada->value, 'total' => 100000,
        ]);
        $reserva->forceFill(['creada_por' => $autor])->saveQuietly();

        return $reserva;
    };
});

afterEach(fn () => Ajustes::olvidar());

it('al entrar con «Recordarme» usa un dispositivo, sin el token de Laravel, y al salir lo olvida', function (): void {
    Auth::guard('web')->login($this->usuario, true);

    $nombres = array_map(fn ($c) => $c->getName(), app('cookie')->getQueuedCookies());
    expect($nombres)->toContain(Dispositivos::COOKIE)
        ->and($nombres)->not->toContain(Auth::guard('web')->getRecallerName())
        ->and(DispositivoRecordado::query()->count())->toBe(1);

    $valor = (string) collect(app('cookie')->getQueuedCookies())->first(fn ($c) => $c->getName() === Dispositivos::COOKIE)->getValue();
    app('request')->cookies->set(Dispositivos::COOKIE, $valor);
    Auth::guard('web')->logout();

    expect(DispositivoRecordado::query()->count())->toBe(0);
});

it('agrega y quita permisos sobre lo que da el rol', function (): void {
    expect(Acceso::puede($this->usuario, Acceso::RESERVAS, $this->propiedad->id))->toBeTrue()
        ->and(Acceso::puede($this->usuario, Acceso::INDICADORES, $this->propiedad->id))->toBeFalse();

    Acceso::asignarRoles($this->usuario, [[
        'propiedad_id' => $this->propiedad->id, 'rol' => 'recepcion',
        'agregados' => [Acceso::INDICADORES, 'inventado.total'], 'quitados' => [Acceso::RESERVAS],
    ]]);
    $this->usuario->refresh();

    expect(Acceso::puede($this->usuario, Acceso::INDICADORES, $this->propiedad->id))->toBeTrue()
        ->and(Acceso::puede($this->usuario, Acceso::RESERVAS, $this->propiedad->id))->toBeFalse()
        ->and(AccesoUsuario::query()->sole()->permisos_agregados)->toBe([Acceso::INDICADORES]);
});

it('no deja fila de ajustes si no hay nada que ajustar, y conserva los ajustes al editar', function (): void {
    expect(AccesoUsuario::query()->count())->toBe(0);

    Acceso::asignarRoles($this->usuario, [['propiedad_id' => $this->propiedad->id, 'rol' => 'recepcion', 'solo_propias' => true, 'horas_cancelar' => '24']]);
    $filas = Acceso::rolesDe($this->usuario);
    expect($filas[0]['solo_propias'])->toBeTrue()->and($filas[0]['horas_cancelar'])->toBe(24);

    Acceso::asignarRoles($this->usuario, [['propiedad_id' => $this->propiedad->id, 'rol' => 'recepcion']]);
    expect(AccesoUsuario::query()->count())->toBe(0);
});

it('solo deja ver lo propio con ese límite, sin esconder a otros usuarios lo demás', function (): void {
    $this->actingAs($this->usuario);
    $mia = ($this->reservaDe)($this->usuario->id);
    $ajena = ($this->reservaDe)(null, 20);
    $huespedMio = Huesped::query()->create(['propiedad_id' => $this->propiedad->id, 'apellido1' => 'Mía']);
    auth()->logout();
    $huespedAjeno = Huesped::query()->create(['propiedad_id' => $this->propiedad->id, 'apellido1' => 'Ajena']);
    $this->actingAs($this->usuario);

    expect(ReservaResource::getEloquentQuery()->count())->toBe(2);

    Acceso::asignarRoles($this->usuario, [['propiedad_id' => $this->propiedad->id, 'rol' => 'recepcion', 'solo_propias' => true]]);

    expect(ReservaResource::getEloquentQuery()->pluck('id')->all())->toBe([$mia->id])
        ->and(ReservaResource::canView($ajena))->toBeFalse()
        ->and(ReservaResource::canView($mia))->toBeTrue()
        ->and(ReservaResource::canEdit($ajena))->toBeFalse()
        ->and(HuespedResource::getEloquentQuery()->orderBy('id')->pluck('id')->all())->toBe([$this->titular->id, $huespedMio->id])
        ->and($huespedAjeno->creado_por)->toBeNull();
});

it('no deja cambiar lo ya iniciado ni pasado el plazo', function (): void {
    Acceso::asignarRoles($this->usuario, [['propiedad_id' => $this->propiedad->id, 'rol' => 'recepcion', 'sin_iniciadas' => true, 'horas_modificar' => 2, 'horas_cancelar' => 48]]);
    $this->actingAs($this->usuario);

    $hoy = ($this->reservaDe)(null, 0);
    $lejana = ($this->reservaDe)(null, 30);
    expect(Limites::puedeModificar($this->usuario, $hoy))->toBeFalse()
        ->and(Limites::puedeCancelar($this->usuario, $hoy))->toBeFalse()
        ->and(Limites::puedeModificar($this->usuario, $lejana))->toBeTrue();

    $this->travel(3)->hours();
    expect(Limites::puedeModificar($this->usuario, $lejana))->toBeFalse()
        ->and(ReservaResource::canEdit($lejana))->toBeFalse()
        ->and(Limites::puedeCancelar($this->usuario, $lejana))->toBeTrue();
});

it('un administrador no tiene límites', function (): void {
    $administrador = User::query()->create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => Hash::make('contrasena-de-prueba'), 'activo' => true, 'es_administrador' => true]);
    AccesoUsuario::query()->create(['user_id' => $administrador->id, 'propiedad_id' => $this->propiedad->id, 'solo_propias' => true, 'sin_iniciadas' => true]);
    $reserva = ($this->reservaDe)(null, 0);

    expect(Limites::puedeModificar($administrador, $reserva))->toBeTrue()
        ->and(Limites::puedeVer($administrador, $reserva))->toBeTrue();
});

it('apunta quién creó la reserva, y nadie si la crea el motor', function (): void {
    expect(($this->reservaDe)(null)->creada_por)->toBeNull();

    $this->actingAs($this->usuario);
    $hoy = $this->propiedad->hoy();
    $reserva = app(GestorReservas::class)->crear($this->propiedad, [
        'unidad_id' => $this->unidad->id, 'titular_id' => $this->titular->id, 'llegada' => $hoy->addDays(40)->format('Y-m-d'), 'salida' => $hoy->addDays(41)->format('Y-m-d'),
        'personas' => 2, 'estado' => EstadoReserva::Confirmada->value, 'total' => 100000,
    ]);
    expect($reserva->creada_por)->toBe($this->usuario->id);
});

it('edita los roles: solo quien administra, los de fábrica no se borran y uno propio se puede asignar', function (): void {
    $this->actingAs($this->usuario);
    expect(RolResource::canViewAny())->toBeFalse();
    $this->get(RolResource::getUrl('index'))->assertForbidden();

    $administrador = User::query()->create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => Hash::make('contrasena-de-prueba'), 'activo' => true, 'es_administrador' => true]);
    $this->actingAs($administrador);
    Livewire::test(ListRoles::class)->assertOk();

    Livewire::test(CreateRol::class)
        ->fillForm(['name' => 'Vigilancia', 'permisos' => [Acceso::OPERACION]])
        ->call('create')
        ->assertHasNoFormErrors();

    $rol = Role::query()->where('name', 'Vigilancia')->sole();
    expect($rol->permissions()->pluck('name')->all())->toBe([Acceso::OPERACION])
        ->and(RolResource::canDelete($rol))->toBeTrue()
        ->and(RolResource::canDelete(Role::query()->where('name', 'recepcion')->sole()))->toBeFalse()
        ->and(Acceso::opcionesRol())->toHaveKey('Vigilancia');

    Acceso::asignarRoles($this->usuario, [['propiedad_id' => $this->propiedad->id, 'rol' => 'Vigilancia']]);
    expect(Acceso::puede($this->usuario, Acceso::OPERACION, $this->propiedad->id))->toBeTrue()
        ->and(Acceso::puede($this->usuario, Acceso::RESERVAS, $this->propiedad->id))->toBeFalse()
        ->and(RolResource::canDelete($rol))->toBeFalse();

    Acceso::guardarPermisosDeRol($rol, [Acceso::OPERACION, Acceso::RESERVAS]);
    expect(Acceso::puede($this->usuario, Acceso::RESERVAS, $this->propiedad->id))->toBeTrue();
});

it('recuerda el dispositivo, cambia el validador en cada uso y anula la cookie copiada', function (): void {
    $dispositivos = app(Dispositivos::class);
    $cookies = [];
    $leerCookie = function () use (&$cookies): string {
        foreach (app('cookie')->getQueuedCookies() as $cookie) {
            if ($cookie->getName() === Dispositivos::COOKIE) {
                $cookies[] = (string) $cookie->getValue();
                app('cookie')->unqueue(Dispositivos::COOKIE);
            }
        }

        return end($cookies);
    };

    $dispositivos->recordar($this->usuario, 'Mozilla/5.0 (Windows NT 10.0) Chrome/120 Safari/537', '203.0.113.9');
    $primera = $leerCookie();
    expect($primera)->toMatch('/^[0-9a-f]{18}\.[0-9a-f]{64}$/');

    $guardado = DispositivoRecordado::query()->sole();
    expect($guardado->validador_hash)->not->toContain(explode('.', $primera)[1])
        ->and(Dispositivos::describir($guardado->agente))->toBe('Chrome · Windows');

    expect($dispositivos->usar($primera, null, null)?->id)->toBe($this->usuario->id);
    $segunda = $leerCookie();
    expect($segunda)->not->toBe($primera);

    // Otra pestaña con la cookie anterior, dentro del margen: entra y no vuelve a cambiarla.
    expect($dispositivos->usar($primera, null, null)?->id)->toBe($this->usuario->id);

    // Un validador cualquiera no entra, ni anula el dispositivo de su dueño.
    expect($dispositivos->usar(explode('.', $primera)[0].'.'.str_repeat('0', 64), null, null))->toBeNull()
        ->and(DispositivoRecordado::query()->count())->toBe(1);

    // La cookie anterior, ya fuera del margen: pudo copiarse.
    $this->travel(3)->minutes();
    expect($dispositivos->usar($primera, null, null))->toBeNull()
        ->and(DispositivoRecordado::query()->count())->toBe(0)
        ->and($dispositivos->usar($segunda, null, null))->toBeNull();
});

it('olvida los dispositivos al caducar, al cambiar la contraseña y al desactivar al usuario', function (): void {
    $dispositivos = app(Dispositivos::class);
    $cookie = function () use ($dispositivos): string {
        $dispositivos->recordar($this->usuario, null, null);
        $valor = collect(app('cookie')->getQueuedCookies())->first(fn ($c) => $c->getName() === Dispositivos::COOKIE)->getValue();
        app('cookie')->unqueue(Dispositivos::COOKIE);

        return (string) $valor;
    };

    $valor = $cookie();
    $this->travel(31)->days();
    expect($dispositivos->usar($valor, null, null))->toBeNull()->and(DispositivoRecordado::query()->count())->toBe(0);
    $this->travelBack();

    $cookie();
    $this->usuario->update(['password' => Hash::make('otra-contrasena-de-prueba')]);
    expect(DispositivoRecordado::query()->count())->toBe(0);

    $cookie();
    $this->usuario->update(['activo' => false]);
    expect(DispositivoRecordado::query()->count())->toBe(0);
});

it('vuelve a entrar con la cookie, y con cero días no recuerda nada', function (): void {
    app(Dispositivos::class)->recordar($this->usuario, 'prueba', null);
    $valor = (string) collect(app('cookie')->getQueuedCookies())->first(fn ($c) => $c->getName() === Dispositivos::COOKIE)->getValue();
    app('cookie')->unqueue(Dispositivos::COOKIE);

    $this->withCookie(Dispositivos::COOKIE, $valor)->get('/sesiones')->assertOk();
    $this->assertAuthenticatedAs($this->usuario);

    auth()->logout();
    $this->flushSession();
    expect(DispositivoRecordado::query()->count())->toBe(0);

    $administrador = User::query()->create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => Hash::make('contrasena-de-prueba'), 'activo' => true, 'es_administrador' => true]);
    $this->actingAs($administrador);
    Livewire::test(SesionesDispositivos::class)->set('diasRecordar', 0)->call('guardarDias')->assertHasNoErrors();
    expect(Dispositivos::diasRecordar())->toBe(0);
    app(Dispositivos::class)->recordar($this->usuario, null, null);
    expect(DispositivoRecordado::query()->count())->toBe(0);
});

it('cada quien ve y cierra solo lo suyo; quien administra, lo de todos', function (): void {
    $otro = User::query()->create(['name' => 'Otro', 'email' => 'otro@example.com', 'password' => Hash::make('contrasena-de-prueba'), 'activo' => true]);
    app(Dispositivos::class)->recordar($this->usuario, null, null);
    app(Dispositivos::class)->recordar($otro, null, null);
    $delOtro = DispositivoRecordado::query()->where('user_id', $otro->id)->sole();

    $this->actingAs($this->usuario);
    $this->get(SesionesDispositivos::getUrl())->assertOk();
    $pagina = Livewire::test(SesionesDispositivos::class);
    expect($pagina->instance()->dispositivos())->toHaveCount(1);

    $pagina->call('olvidarDispositivo', $delOtro->id);
    expect(DispositivoRecordado::query()->whereKey($delOtro->id)->exists())->toBeTrue();

    $pagina->set('diasRecordar', 0)->call('guardarDias');
    expect(Dispositivos::diasRecordar())->toBe(30);

    $pagina->call('cerrarLasDemas');
    expect(DispositivoRecordado::query()->where('user_id', $this->usuario->id)->count())->toBe(0)
        ->and(DispositivoRecordado::query()->where('user_id', $otro->id)->count())->toBe(1);

    $administrador = User::query()->create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => Hash::make('contrasena-de-prueba'), 'activo' => true, 'es_administrador' => true]);
    $this->actingAs($administrador);
    $pagina = Livewire::test(SesionesDispositivos::class)->call('olvidarDispositivo', $delOtro->id);
    expect(DispositivoRecordado::query()->count())->toBe(0);
});

it('anota antes y después, sin secretos ni datos personales', function (): void {
    $this->actingAs($this->usuario);
    $this->unidad->update(['nombre' => 'Dos']);
    $huesped = Huesped::query()->create(['propiedad_id' => $this->propiedad->id, 'apellido1' => 'Secreto', 'notas' => 'alergia']);
    $huesped->update(['apellido1' => 'OtroSecreto']);
    $this->usuario->update(['password' => Hash::make('otra-contrasena-de-prueba'), 'name' => 'Ana María']);

    $unidad = RegistroAuditoria::query()->where('accion', 'cambio.editar')->where('modelo_tipo', 'like', '%Unidad')->sole();
    expect($unidad->cambios['nombre'])->toBe(['antes' => 'Uno', 'despues' => 'Dos'])
        ->and($unidad->user_id)->toBe($this->usuario->id);

    $todo = (string) json_encode(RegistroAuditoria::query()->pluck('cambios')->all());
    expect($todo)->not->toContain('Secreto')->not->toContain('alergia')->not->toContain('$2y$')->not->toContain('María')
        ->and($todo)->toContain('apellido1');
});

it('no anota lo que no hizo una persona del equipo', function (): void {
    $this->unidad->update(['nombre' => 'Sin sesión']);
    expect(RegistroAuditoria::query()->where('accion', 'like', 'cambio.%')->count())->toBe(0);
});

it('en el calendario tampoco se mueve una reserva fuera de los límites', function (): void {
    $reserva = ($this->reservaDe)(null, 10);
    $llegada = $reserva->llegada->format('Y-m-d');
    Acceso::asignarRoles($this->usuario, [['propiedad_id' => $this->propiedad->id, 'rol' => 'recepcion', 'solo_propias' => true]]);

    $destino = $this->propiedad->hoy()->addDays(20)->format('Y-m-d');
    Livewire::actingAs($this->usuario)->test(Calendario::class)->call('moverReserva', $reserva->id, $this->unidad->id, $destino);

    expect($reserva->fresh()->llegada->format('Y-m-d'))->toBe($llegada);
});

it('el super administrador quita la verificación a otro, con su contraseña; nadie más puede', function (): void {
    $this->usuario->saveAppAuthenticationSecret('secreto-de-prueba');
    $administrador = User::query()->create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => Hash::make('contrasena-de-prueba'), 'activo' => true, 'es_administrador' => true]);
    $super = User::query()->create(['name' => 'Super', 'email' => 'super@example.com', 'password' => Hash::make('contrasena-de-prueba'), 'activo' => true, 'es_administrador' => true, 'es_superadministrador' => true]);

    Livewire::actingAs($administrador)->test(ListUsuarios::class)->assertTableActionHidden('quitarDosPasos', $this->usuario);

    Livewire::actingAs($super)->test(ListUsuarios::class)
        ->callTableAction('quitarDosPasos', $this->usuario, ['contrasena' => 'equivocada'])
        ->assertHasTableActionErrors(['contrasena']);
    expect($this->usuario->fresh()->app_authentication_secret)->not->toBeNull();

    Livewire::actingAs($super)->test(ListUsuarios::class)
        ->callTableAction('quitarDosPasos', $this->usuario, ['contrasena' => 'contrasena-de-prueba'])
        ->assertHasNoTableActionErrors();
    expect($this->usuario->fresh()->app_authentication_secret)->toBeNull()
        ->and(RegistroAuditoria::query()->where('accion', 'usuario.quitar_dos_pasos')->count())->toBe(1);
});

it('exporta la auditoría y guarda el plazo que se elija', function (): void {
    $administrador = User::query()->create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => Hash::make('contrasena-de-prueba'), 'activo' => true, 'es_administrador' => true]);
    RegistroAuditoria::anotar('prueba.exportar');

    Livewire::actingAs($administrador)->test(ListRegistrosAuditoria::class)
        ->callAction('exportar', ['desde' => now()->subDay()->format('Y-m-d'), 'hasta' => now()->addDay()->format('Y-m-d')])
        ->assertFileDownloaded();

    expect(RegistroAuditoria::diasConservar())->toBe((int) config('alojaflow.dias_conservar_auditoria'));
    Livewire::actingAs($administrador)->test(ListRegistrosAuditoria::class)->callAction('plazo', ['dias' => 730])->assertHasNoActionErrors();
    expect(RegistroAuditoria::diasConservar())->toBe(730);
});

it('deja editar a un usuario y conserva sus ajustes en el formulario', function (): void {
    $administrador = User::query()->create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => Hash::make('contrasena-de-prueba'), 'activo' => true, 'es_administrador' => true]);
    Acceso::asignarRoles($this->usuario, [['propiedad_id' => $this->propiedad->id, 'rol' => 'recepcion', 'horas_modificar' => 5, 'quitados' => [Acceso::MENSAJES]]]);

    Livewire::actingAs($administrador)->test(EditUsuario::class, ['record' => $this->usuario->id])->call('save')->assertHasNoFormErrors();

    $ajuste = AccesoUsuario::query()->sole();
    expect($ajuste->horas_modificar)->toBe(5)->and($ajuste->permisos_quitados)->toBe([Acceso::MENSAJES]);
});

it('quien solo ve lo suyo tampoco ve lo ajeno en Hoy, en el calendario ni en los buscadores', function (): void {
    $ajena = ($this->reservaDe)(null, 0);
    Acceso::asignarRoles($this->usuario, [['propiedad_id' => $this->propiedad->id, 'rol' => 'recepcion', 'solo_propias' => true]]);
    $this->actingAs($this->usuario);

    $hoy = $this->propiedad->hoy()->format('Y-m-d');
    expect(Operacion::dia([$this->propiedad->id], $hoy)['llegan'])->toHaveCount(0);

    Livewire::actingAs($this->usuario)->test(Hoy::class)->call('registrarEntrada', $ajena->id);
    expect($ajena->fresh()->entrada_registrada_en)->toBeNull();

    $this->get(Calendario::getUrl())->assertOk()->assertDontSee('Zacarías')->assertSee(__('permisos.limites.ocupada'))->assertDontSee('data-reserva="'.$ajena->id.'"', false);

    Acceso::asignarRoles($this->usuario, [['propiedad_id' => $this->propiedad->id, 'rol' => 'recepcion']]);
    $this->usuario->refresh();
    $this->get(Calendario::getUrl())->assertOk()->assertSee('Zacarías');
});

it('los permisos tienen su nombre en cada idioma, no la clave', function (): void {
    foreach (['es', 'en', 'fr', 'it', 'pt', 'de'] as $idioma) {
        app()->setLocale($idioma);
        foreach (Acceso::opcionesPermiso() as $permiso => $etiqueta) {
            expect($etiqueta)->not->toContain('permisos.nombres')->and($permiso)->toBeIn(Acceso::PERMISOS);
        }
    }
});

it('borra los dispositivos caducados en la limpieza diaria', function (): void {
    app(Dispositivos::class)->recordar($this->usuario, null, null);
    $this->travel(31)->days();
    $this->artisan('alojaflow:limpiar')->assertSuccessful();
    expect(DispositivoRecordado::query()->count())->toBe(0);
});

it('vaciar los días da un error de validación, no un fallo', function (): void {
    $administrador = User::query()->create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => Hash::make('contrasena-de-prueba'), 'activo' => true, 'es_administrador' => true]);
    Livewire::actingAs($administrador)->test(SesionesDispositivos::class)->set('diasRecordar', '')->call('guardarDias')->assertHasErrors(['diasRecordar']);
});
