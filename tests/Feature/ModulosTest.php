<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

use App\Enums\AccionAutomatizacion;
use App\Enums\CanalEnvio;
use App\Enums\DisparadorAutomatizacion;
use App\Enums\EstadoEnvio;
use App\Enums\ModoPropiedad;
use App\Filament\Pages\CierreDia;
use App\Filament\Pages\Indicadores;
use App\Filament\Pages\Informes;
use App\Filament\Pages\Limpieza;
use App\Filament\Pages\ModulosInstalacion;
use App\Filament\Pages\MotorDeReservas;
use App\Filament\Pages\PlanoPropiedad;
use App\Filament\Pages\PrimerasPreguntas;
use App\Filament\Pages\Resumen;
use App\Filament\Resources\Automatizaciones\AutomatizacionResource;
use App\Filament\Resources\Cajas\CajaResource;
use App\Filament\Resources\Cupones\CuponResource;
use App\Filament\Resources\FuentesIcal\FuenteIcalResource;
use App\Filament\Resources\Grupos\GrupoResource;
use App\Filament\Resources\Llegadas\RegistroLlegadaResource;
use App\Filament\Resources\Propiedades\PropiedadResource;
use App\Filament\Resources\Tareas\TareaResource;
use App\Filament\Resources\Usuarios\UsuarioResource;
use App\Filament\Resources\Webhooks\WebhookResource;
use App\Instalacion\EnlaceEntrada;
use App\Instalacion\Instalador;
use App\Models\Automatizacion;
use App\Models\Envio;
use App\Models\Plantilla;
use App\Models\Propiedad;
use App\Models\Tarifa;
use App\Models\Unidad;
use App\Models\User;
use App\Modulos\Modulo;
use App\Modulos\Modulos;
use App\Modulos\PrimerosPasos;
use App\Programador\TareasPorVisita;
use App\Seguridad\Acceso;
use App\Support\Ajustes;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

afterEach(fn () => Ajustes::olvidar());

function superAdmin(array $datos = []): User
{
    return User::factory()->create(['es_administrador' => true, 'es_superadministrador' => true, 'activo' => true, 'idioma' => 'es'] + $datos);
}

function propiedadSimple(array $datos = []): Propiedad
{
    return Propiedad::query()->create($datos + ['nombre' => 'Hotel '.uniqid(), 'codigo' => 'h'.uniqid(), 'modo' => ModoPropiedad::Nativa, 'pais' => 'MX', 'moneda' => 'MXN', 'zona_horaria' => 'America/Mexico_City', 'idioma' => 'es']);
}

describe('módulos', function () {
    it('sin decidir nada, todo está prendido', function () {
        foreach (Modulo::cases() as $modulo) {
            expect(Modulos::activo($modulo))->toBeTrue();
        }
        expect(Modulos::nuevos())->toBe([]);
    });

    it('prender uno prende lo que necesita', function () {
        Modulos::guardar([Modulo::Pagos]);

        expect(Modulos::activo(Modulo::Pagos))->toBeTrue()
            ->and(Modulos::activo(Modulo::Motor))->toBeTrue()
            ->and(Modulos::activo(Modulo::Limpieza))->toBeFalse();
    });

    it('un módulo apagado sale del acceso, de las pantallas y de los recursos', function () {
        $admin = superAdmin();
        propiedadSimple();
        $this->actingAs($admin);

        $de = [
            Limpieza::class => Modulo::Limpieza, TareaResource::class => Modulo::Limpieza, PlanoPropiedad::class => Modulo::Plano,
            Informes::class => Modulo::Informes, Indicadores::class => Modulo::Informes, CierreDia::class => Modulo::Caja,
            CajaResource::class => Modulo::Caja, MotorDeReservas::class => Modulo::Motor,
            FuenteIcalResource::class => Modulo::Ical, GrupoResource::class => Modulo::Grupos, CuponResource::class => Modulo::Cupones,
            RegistroLlegadaResource::class => Modulo::Llegada, AutomatizacionResource::class => Modulo::Mensajes, WebhookResource::class => Modulo::Integraciones,
        ];
        foreach ($de as $clase => $modulo) {
            Modulos::guardar(Modulo::cases());
            expect($clase::canAccess())->toBeTrue($clase);
            Modulos::guardar(array_values(array_filter(Modulo::cases(), fn (Modulo $m): bool => $m !== $modulo && ! in_array($modulo, $m->requiere(), true))));
            expect($clase::canAccess())->toBeFalse($clase);
        }

        Modulos::guardar([]);
        $this->get(Limpieza::getUrl())->assertForbidden();
        $this->get(TareaResource::getUrl())->assertForbidden();
        Modulos::marcarPreguntas('preguntas');
        $this->get(Resumen::getUrl())->assertOk()->assertDontSee(__('limpieza.titulo'));
    });

    it('lo público de un módulo apagado no existe, pero el aviso de un cobro sigue llegando', function () {
        $propiedad = propiedadSimple();

        Modulos::guardar(array_values(array_filter(Modulo::cases(), fn (Modulo $m): bool => ! in_array($m, [Modulo::Motor, Modulo::Pagos, Modulo::Ical, Modulo::Llegada], true))));

        $this->get(route('motor.buscar', ['codigo' => $propiedad->codigo]))->assertNotFound();
        $this->get('/ical/'.str_repeat('a', 40).'.ics')->assertNotFound();
        $this->get('/llegada/'.str_repeat('a', 40))->assertNotFound();
        // No dice 404 por el módulo: dice que no conoce esa pasarela sin llaves.
        $this->post(route('motor.aviso', ['codigo' => $propiedad->codigo, 'pasarela' => 'stripe']))->assertStatus(404);

        Modulos::guardar(Modulo::cases());
        $this->get(route('motor.buscar', ['codigo' => $propiedad->codigo]))->assertStatus(404); // el motor de esta propiedad está cerrado
    });

    it('las tareas se detienen con su módulo', function () {
        Modulos::guardar([Modulo::Mensajes]);

        expect(array_keys(TareasPorVisita::tareas()))->not->toContain('alojaflow:sincronizar')
            ->and(array_keys(TareasPorVisita::tareas()))->not->toContain('alojaflow:ical')
            ->and(array_keys(TareasPorVisita::tareas()))->not->toContain('alojaflow:limpieza')
            ->and(array_keys(TareasPorVisita::tareas()))->toContain('alojaflow:mensajes');
    });

    it('al apagar mensajes no sale meses después lo que estaba por salir, y lo que se mandó a mano sí', function () {
        $propiedad = propiedadSimple();
        $plantilla = Plantilla::query()->create(['propiedad_id' => $propiedad->id, 'codigo' => 'hola', 'nombre' => 'Hola', 'asunto' => ['es' => 'Hola'], 'cuerpo' => ['es' => 'Hola']]);
        $regla = Automatizacion::query()->create(['propiedad_id' => $propiedad->id, 'nombre' => 'Regla', 'disparador' => DisparadorAutomatizacion::Llegada, 'accion' => AccionAutomatizacion::CorreoHuesped, 'plantilla_id' => $plantilla->id]);
        $automatica = Envio::query()->create(['propiedad_id' => $propiedad->id, 'automatizacion_id' => $regla->id, 'canal' => CanalEnvio::Correo, 'destino' => 'a@example.com', 'estado' => EstadoEnvio::Pendiente]);
        $mano = Envio::query()->create(['propiedad_id' => $propiedad->id, 'canal' => CanalEnvio::Correo, 'destino' => 'b@example.com', 'estado' => EstadoEnvio::Pendiente]);

        Modulos::guardar(array_values(array_filter(Modulo::cases(), fn (Modulo $m): bool => $m !== Modulo::Mensajes)));

        expect($mano->refresh()->estado)->toBe(EstadoEnvio::Pendiente)
            ->and($automatica->refresh()->estado)->toBe(EstadoEnvio::Omitido)
            ->and($automatica->ultimo_error)->toBe(__('modulos.omitido_al_apagar'));
    });

    it('la pantalla la abre solo el super administrador, y prende lo que hace falta', function () {
        $admin = User::factory()->create(['es_administrador' => true, 'activo' => true, 'idioma' => 'es']);
        $this->actingAs($admin);
        $this->get(ModulosInstalacion::getUrl())->assertForbidden();
        $this->get(PrimerasPreguntas::getUrl())->assertForbidden();

        $super = superAdmin();
        $this->actingAs($super);
        $this->get(ModulosInstalacion::getUrl())->assertOk()->assertSee(__('modulos.lista.motor.nombre'));

        Livewire::actingAs($super)->test(ModulosInstalacion::class)
            ->set('elegidos.motor', false)
            ->assertSet('elegidos.pagos', false)
            ->set('elegidos.pagos', true)
            ->assertSet('elegidos.motor', true)
            ->set('elegidos.limpieza', false)
            ->call('guardar');

        expect(Modulos::activo(Modulo::Limpieza))->toBeFalse()
            ->and(Modulos::activo(Modulo::Pagos))->toBeTrue();
    });

    it('con un rol por propiedad no se abren las pantallas de módulos, y el resumen no enseña los primeros pasos', function () {
        $this->seed(RolesYPermisosSeeder::class);
        $propiedad = propiedadSimple();
        $recepcion = User::factory()->create(['activo' => true, 'idioma' => 'es']);
        Acceso::asignarRoles($recepcion, [['propiedad_id' => $propiedad->id, 'rol' => 'recepcion']]);
        Modulos::marcarPreguntas('preguntas');
        $this->actingAs($recepcion);

        $this->get(ModulosInstalacion::getUrl())->assertForbidden();
        $this->get(PrimerasPreguntas::getUrl())->assertForbidden();
        $this->get(Resumen::getUrl())->assertOk()->assertDontSee(__('modulos.pasos.titulo'));
    });

    it('avisa de los módulos nuevos que llegan con una actualización', function () {
        $super = superAdmin();
        Ajustes::guardar('modulos.preguntas', ['como' => 'actualizacion']);
        Ajustes::guardar('modulos.activos', ['motor']);
        Ajustes::guardar('modulos.conocidos', ['motor', 'pagos']);
        Ajustes::olvidar();

        expect(Modulos::nuevos())->toContain(Modulo::Limpieza)->not->toContain(Modulo::Motor)
            ->and(Modulos::activo(Modulo::Limpieza))->toBeFalse();
        $this->actingAs($super)->get(Resumen::getUrl())->assertOk()->assertSee(__('modulos.lista.limpieza.nombre'));

        $this->get(ModulosInstalacion::getUrl())->assertOk();
        Ajustes::olvidar();
        expect(Modulos::nuevos())->toBe([]);
    });

    it('sin el módulo de varias propiedades se crea la primera y ya', function () {
        $super = superAdmin();
        $this->actingAs($super);
        Modulos::guardar([]);

        expect(PropiedadResource::canCreate())->toBeTrue();
        propiedadSimple();
        expect(PropiedadResource::canCreate())->toBeFalse();

        Modulos::guardar([Modulo::VariasPropiedades]);
        expect(PropiedadResource::canCreate())->toBeTrue();
    });

    it('cuenta lo que se dejaría sin atender antes de apagar', function () {
        propiedadSimple();
        propiedadSimple();

        expect(Modulos::enUso(Modulo::VariasPropiedades))->toBe(1)
            ->and(Modulos::enUso(Modulo::Subordinacion))->toBe(0);
    });
});

describe('preguntas de la primera entrada', function () {
    it('quien instaló entra primero a las preguntas; los demás, no', function () {
        $admin = User::factory()->create(['es_administrador' => true, 'activo' => true, 'idioma' => 'es']);
        $this->actingAs($admin)->get(Resumen::getUrl())->assertOk();

        $super = superAdmin();
        Livewire::actingAs($super)->test(Resumen::class)->assertRedirect(PrimerasPreguntas::getUrl());
    });

    it('las respuestas deciden qué queda prendido', function () {
        $super = superAdmin();

        Livewire::actingAs($super)->test(PrimerasPreguntas::class)
            ->set('propiedades', 'varias')
            ->set('otroSistema', 'no')
            ->set('elegidos.limpieza', false)
            ->set('elegidos.pagos', true)
            ->call('siguiente')->call('siguiente')->call('siguiente')->call('siguiente')->call('siguiente')
            ->assertSet('paso', 6)
            ->call('terminar')
            ->assertRedirect(Resumen::getUrl());

        expect(Modulos::activo(Modulo::VariasPropiedades))->toBeTrue()
            ->and(Modulos::activo(Modulo::Subordinacion))->toBeFalse()
            ->and(Modulos::activo(Modulo::Limpieza))->toBeFalse()
            ->and(Modulos::activo(Modulo::Pagos))->toBeTrue()
            ->and(Modulos::activo(Modulo::Motor))->toBeTrue()
            ->and(Modulos::faltanPreguntas())->toBeFalse();
    });

    it('al saltarlas queda todo prendido, y no se vuelven a hacer solas', function () {
        $super = superAdmin();

        Livewire::actingAs($super)->test(PrimerasPreguntas::class)->call('saltar')->assertRedirect(Resumen::getUrl());

        expect(Modulos::prendidos())->toBe(Modulo::cases())
            ->and(Modulos::faltanPreguntas())->toBeFalse();
        Livewire::actingAs($super)->test(Resumen::class)->assertNoRedirect();
    });

    it('al repetirlas parte de lo que hay', function () {
        $super = superAdmin();
        Modulos::guardar([Modulo::Subordinacion, Modulo::Grupos]);
        Modulos::marcarPreguntas('preguntas');

        Livewire::actingAs($super)->test(PrimerasPreguntas::class)
            ->assertSet('otroSistema', 'principal')
            ->assertSet('propiedades', 'una')
            ->assertSet('elegidos.grupos', true)
            ->assertSet('elegidos.limpieza', false);
    });
});

describe('super administrador', function () {
    it('el instalador lo crea, y el enlace de entrada sirve una sola vez', function () {
        $instalador = app(Instalador::class);
        $usuario = $instalador->crearAdministrador('Dueña', 'duena@example.com', 'contrasena-de-prueba-1');

        expect($usuario->refresh()->es_superadministrador)->toBeTrue()
            ->and($usuario->es_administrador)->toBeTrue();

        $enlace = $instalador->terminar('/usr/local/php83/bin/php');
        expect($enlace)->toContain('/entrar/')
            ->and(Ajustes::obtener(TareasPorVisita::AJUSTE_PHP_CONSOLA))->toBe('/usr/local/php83/bin/php')
            ->and(TareasPorVisita::comandoCron())->toContain('/usr/local/php83/bin/php');

        $this->get(parse_url($enlace, PHP_URL_PATH))->assertRedirect(Resumen::getUrl());
        expect(Auth::id())->toBe($usuario->id);

        Auth::logout();
        $this->get(parse_url($enlace, PHP_URL_PATH))->assertRedirect();
        expect(Auth::check())->toBeFalse();
    });

    it('un enlace viejo o con otro token no deja entrar', function () {
        $usuario = superAdmin();
        $token = basename(EnlaceEntrada::crear($usuario));

        expect(EnlaceEntrada::usar(str_repeat('a', 64)))->toBeNull();

        $this->travel(31)->minutes();
        expect(EnlaceEntrada::usar($token))->toBeNull();
    });

    it('la primera migración deja como super administrador al más antiguo', function () {
        $primero = User::factory()->create(['es_administrador' => true, 'activo' => true]);
        User::factory()->create(['es_administrador' => true, 'activo' => true]);

        $migracion = require database_path('migrations/2026_11_05_100000_anadir_modulos_y_super_administrador.php');
        $migracion->down();
        $migracion->up();
        Ajustes::olvidar();

        expect($primero->refresh()->es_superadministrador)->toBeTrue()
            ->and(User::query()->where('es_superadministrador', true)->count())->toBe(1)
            // Lo que había sigue prendido, y no se preguntan cosas a quien ya usa el sistema.
            ->and(Modulos::faltanPreguntas())->toBeFalse()
            ->and(Modulos::prendidos())->toContain(Modulo::Motor, Modulo::Limpieza)
            // Lo que llegó después de esa versión, apagado y con aviso.
            ->and(Modulos::prendidos())->not->toContain(Modulo::AvisoEquipo)
            ->and(Modulos::nuevos())->toContain(Modulo::AvisoEquipo);
    });

    it('un administrador no nombra administradores ni toca a otro', function () {
        $admin = User::factory()->create(['es_administrador' => true, 'activo' => true, 'idioma' => 'es']);
        $super = superAdmin();
        $otro = User::factory()->create(['es_administrador' => true, 'activo' => true, 'idioma' => 'es']);
        $empleado = User::factory()->create(['activo' => true, 'idioma' => 'es']);
        $this->actingAs($admin);

        expect(UsuarioResource::canEdit($super))->toBeFalse()
            ->and(UsuarioResource::canDelete($super))->toBeFalse()
            ->and(UsuarioResource::canEdit($otro))->toBeFalse()
            ->and(UsuarioResource::canEdit($empleado))->toBeTrue()
            ->and(UsuarioResource::canEdit($admin))->toBeTrue();

        $this->get(UsuarioResource::getUrl('edit', ['record' => $empleado]))->assertOk()->assertDontSee(__('ui.usuarios.super'));

        $this->actingAs($super);
        expect(UsuarioResource::canEdit($otro))->toBeTrue();
        $this->get(UsuarioResource::getUrl('edit', ['record' => $empleado]))->assertOk()->assertSee(__('ui.usuarios.super'));
    });

    it('el enlace de entrada ya no sirve si se activó la verificación en dos pasos', function () {
        $usuario = superAdmin();
        $token = basename(EnlaceEntrada::crear($usuario));
        $usuario->saveAppAuthenticationSecret('SECRETO');

        expect(EnlaceEntrada::usar($token))->toBeNull();
    });

    it('un administrador termina la actualización que trae la columna, antes de su migración', function () {
        $admin = User::factory()->create(['es_administrador' => true, 'activo' => true]);
        expect(Acceso::puedeActualizar($admin))->toBeFalse();

        Schema::table('users', fn (Blueprint $tabla) => $tabla->dropColumn('es_superadministrador'));
        try {
            expect(Acceso::puedeActualizar($admin))->toBeTrue()
                ->and(Acceso::puedeActualizar(User::factory()->make(['es_administrador' => false, 'activo' => true])))->toBeFalse();
        } finally {
            Schema::table('users', fn (Blueprint $tabla) => $tabla->boolean('es_superadministrador')->default(false)->after('es_administrador'));
        }
    });

    it('nunca es menos que administrador', function () {
        $usuario = User::factory()->create(['es_administrador' => false, 'es_superadministrador' => true]);

        expect($usuario->refresh()->es_administrador)->toBeTrue();
    });

    it('desde la consola se recupera el acceso: sin otro super administrador, este lo es', function () {
        $this->artisan('alojaflow:crear-administrador', ['correo' => 'nuevo@example.com'])
            ->expectsQuestion('Contraseña (mínimo 12 caracteres)', 'contrasena-de-prueba-1')
            ->expectsQuestion('Repite la contraseña', 'contrasena-de-prueba-1')
            ->assertSuccessful();

        expect(User::query()->where('email', 'nuevo@example.com')->first()?->es_superadministrador)->toBeTrue();

        $this->artisan('alojaflow:crear-administrador', ['correo' => 'otro@example.com'])
            ->expectsQuestion('Contraseña (mínimo 12 caracteres)', 'contrasena-de-prueba-1')
            ->expectsQuestion('Repite la contraseña', 'contrasena-de-prueba-1')
            ->assertSuccessful();

        expect(User::query()->where('email', 'otro@example.com')->first()?->es_superadministrador)->toBeFalse();
    });
});

describe('primeros pasos', function () {
    it('salen de los datos y solo los ve quien administra', function () {
        $super = superAdmin();
        $pasos = collect(PrimerosPasos::de($super))->keyBy('clave');

        expect($pasos['propiedad']['hecho'])->toBeFalse()
            ->and($pasos->has('unidades'))->toBeFalse()
            ->and($pasos['cron']['comando'])->toContain('schedule:run');

        propiedadSimple();
        $pasos = collect(PrimerosPasos::de($super))->keyBy('clave');
        expect($pasos['propiedad']['hecho'])->toBeTrue()
            ->and($pasos->has('unidades'))->toBeTrue()
            ->and($pasos->has('motor'))->toBeTrue();

        Modulos::guardar([]);
        expect(collect(PrimerosPasos::de($super))->keyBy('clave')->has('motor'))->toBeFalse();

        expect(PrimerosPasos::mostrar(User::factory()->create(['activo' => true])))->toBe([])
            ->and(PrimerosPasos::mostrar($super))->not->toBe([]);

        PrimerosPasos::ocultar();
        expect(PrimerosPasos::mostrar($super))->toBe([]);
    });

    it('se cierran solos al terminar, aunque no se invite a nadie', function () {
        $super = superAdmin();
        $super->saveAppAuthenticationSecret('SECRETO');
        Modulos::guardar([]);
        Ajustes::guardar('correo.host', 'smtp.example.com');
        Ajustes::guardar('copias.ultima', ['en' => now('UTC')->toIso8601String()]);
        TareasPorVisita::anotarLatido();
        $propiedad = propiedadSimple();
        expect(PrimerosPasos::mostrar($super))->not->toBe([]);

        Unidad::query()->create(['propiedad_id' => $propiedad->id, 'nombre' => 'Uno', 'codigo' => 'u1', 'capacidad' => 2]);
        Tarifa::query()->create(['propiedad_id' => $propiedad->id, 'nombre' => 'Base', 'precio_noche' => 1000]);

        expect(PrimerosPasos::mostrar($super))->toBe([])
            ->and(Ajustes::obtener('primeros_pasos.oculto'))->toBeTrue();
    });

    it('se ven en el resumen', function () {
        $super = superAdmin();
        Modulos::marcarPreguntas('preguntas');

        $this->actingAs($super)->get(Resumen::getUrl())->assertOk()
            ->assertSee(__('modulos.pasos.titulo'))
            ->assertSee(__('modulos.pasos.propiedad.titulo'))
            ->assertSee('schedule:run');
    });
});
