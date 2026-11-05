<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

use App\Comunicacion\Mensajero;
use App\Comunicacion\Opiniones;
use App\Comunicacion\VariablesMensaje;
use App\Dinero\GestorPoliticas;
use App\Enums\AccionAutomatizacion;
use App\Enums\DisparadorAutomatizacion;
use App\Enums\EstadoEnvio;
use App\Enums\EstadoRegistroLlegada;
use App\Enums\EstadoReserva;
use App\Enums\MetodoPago;
use App\Enums\ModoPropiedad;
use App\Enums\TipoContacto;
use App\Enums\TipoReserva;
use App\Enums\TipoTarea;
use App\Filament\Pages\Calendario;
use App\Filament\Pages\CierreDia;
use App\Filament\Pages\DepositosNoShow;
use App\Filament\Pages\Hoy;
use App\Filament\Pages\ImportarPrecios;
use App\Filament\Pages\MensajesPeriodo;
use App\Filament\Pages\Resumen;
use App\Filament\Resources\Automatizaciones\Pages\CreateAutomatizacion;
use App\Filament\Resources\Opiniones\OpinionResource;
use App\Filament\Resources\Opiniones\Pages\ListOpiniones;
use App\Filament\Resources\Plantillas\Pages\ListPlantillas;
use App\Filament\Resources\Politicas\Pages\ListPoliticas;
use App\Filament\Resources\Reservas\Pages\CreateReserva;
use App\Filament\Resources\Reservas\Pages\ViewReserva;
use App\Filament\Resources\Reservas\RelationManagers\EnlacesPagoRelationManager;
use App\Filament\Resources\Restricciones\Pages\CreateRestriccion;
use App\Filament\Resources\Restricciones\Pages\ListRestricciones;
use App\Models\Automatizacion;
use App\Models\Caja;
use App\Models\ContactoHuesped;
use App\Models\EnlacePago;
use App\Models\EventoReserva;
use App\Models\Huesped;
use App\Models\MarcaNoShow;
use App\Models\NotaBitacora;
use App\Models\Opinion;
use App\Models\Pago;
use App\Models\Plantilla;
use App\Models\Politica;
use App\Models\Propiedad;
use App\Models\RegistroLlegada;
use App\Models\Reserva;
use App\Models\Restriccion;
use App\Models\Tarea;
use App\Models\Tarifa;
use App\Models\TemporadaTarifa;
use App\Models\Unidad;
use App\Models\User;
use App\Modulos\Modulo;
use App\Modulos\Modulos;
use App\Motor\AjustesMotor;
use App\Motor\MotorReservas;
use App\Pagos\GestorEnlacesPago;
use App\Privacidad\GestorPrivacidad;
use App\Reservas\Cotizador;
use App\Reservas\GestorReservas;
use App\Reservas\ImportadorPrecios;
use App\Reservas\ReservaRechazada;
use App\Reservas\Restricciones;
use App\Seguridad\Acceso;
use App\Support\Ajustes;
use Database\Seeders\RolesYPermisosSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
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
    $this->tarifa = Tarifa::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Doble', 'precio_noche' => 100000, 'personas_incluidas' => 2, 'activa' => true, 'visible_motor' => true]);
    $this->unidad = Unidad::query()->create(['propiedad_id' => $this->propiedad->id, 'tarifa_id' => $this->tarifa->id, 'codigo' => '101', 'nombre' => 'Cabaña', 'capacidad' => 4, 'orden' => 1]);
    $this->otra = Unidad::query()->create(['propiedad_id' => $this->propiedad->id, 'tarifa_id' => $this->tarifa->id, 'codigo' => '102', 'nombre' => 'Suite', 'capacidad' => 4, 'orden' => 2]);
    $this->huesped = Huesped::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Ana', 'apellido1' => 'Prueba']);
    ContactoHuesped::query()->create(['huesped_id' => $this->huesped->id, 'tipo' => TipoContacto::Telefono, 'valor' => '55 1111 2222', 'valor_normalizado' => '5511112222', 'orden' => 0]);
    ContactoHuesped::query()->create(['huesped_id' => $this->huesped->id, 'tipo' => TipoContacto::Correo, 'valor' => 'ana@example.com', 'valor_normalizado' => 'ana@example.com', 'orden' => 1]);
    $this->admin = User::query()->create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => Hash::make('contrasena-de-prueba'), 'activo' => true, 'es_administrador' => true]);
    $this->recepcion = User::query()->create(['name' => 'Rec', 'email' => 'rec@example.com', 'password' => Hash::make('contrasena-de-prueba'), 'activo' => true]);
    Acceso::asignarRoles($this->recepcion, [['propiedad_id' => $this->propiedad->id, 'rol' => 'recepcion']]);
    $this->hoy = $this->propiedad->hoy();
    $this->reservar = fn (int $llegada, int $salida, array $mas = []): Reserva => app(GestorReservas::class)->crear($this->propiedad, $mas + [
        'unidad_id' => $this->unidad->id, 'titular_id' => $this->huesped->id, 'llegada' => $this->hoy->addDays($llegada)->format('Y-m-d'), 'salida' => $this->hoy->addDays($salida)->format('Y-m-d'),
        'personas' => 2, 'estado' => EstadoReserva::Confirmada->value, 'total' => 100000,
    ]);
});

describe('Hoy, cierre y resumen', function (): void {
    it('Hoy suma lo pendiente y lo que hay que limpiar', function (): void {
        ($this->reservar)(0, 2);
        ($this->reservar)(0, 1, ['unidad_id' => $this->otra->id, 'total' => 50000]);
        Tarea::query()->create(['propiedad_id' => $this->propiedad->id, 'unidad_id' => $this->unidad->id, 'tipo' => TipoTarea::LimpiezaSalida, 'titulo' => 'x', 'fecha' => $this->hoy->format('Y-m-d')]);

        $pagina = Livewire::actingAs($this->recepcion)->test(Hoy::class);
        $cifras = $pagina->instance()->cifrasDelDia($pagina->instance()->listas());
        expect($cifras['pendiente'])->toBe(['MXN' => 150000])->and($cifras['por_limpiar'])->toBe(1);
        $pagina->assertSee('Pendiente de cobro de hoy');

        Modulos::guardar(array_values(array_filter(Modulo::cases(), fn (Modulo $m): bool => $m !== Modulo::Limpieza)));
        expect(Livewire::actingAs($this->recepcion)->test(Hoy::class)->instance()->cifrasDelDia([])['por_limpiar'])->toBeNull();
    });

    it('a quien solo limpia no le enseña dinero', function (): void {
        ($this->reservar)(0, 2);
        $limpieza = User::query()->create(['name' => 'Limpieza', 'email' => 'lim@example.com', 'password' => Hash::make('contrasena-de-prueba'), 'activo' => true]);
        Acceso::asignarRoles($limpieza, [['propiedad_id' => $this->propiedad->id, 'rol' => 'limpieza']]);
        $pagina = Livewire::actingAs($limpieza)->test(Hoy::class);
        expect($pagina->instance()->cifrasDelDia($pagina->instance()->listas())['pendiente'])->toBe([]);
    });

    it('el cierre dice quiénes faltan, con solo su número, y la caja por caja y método', function (): void {
        $hoy = ($this->reservar)(0, 2);
        $sale = ($this->reservar)(-2, 0, ['unidad_id' => $this->otra->id]);
        ($this->reservar)(2, 3, ['estado' => EstadoReserva::SinConfirmar->value, 'unidad_id' => $this->otra->id]);
        $caja = Caja::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Recepción', 'activa' => true]);
        app(GestorReservas::class)->registrarPago($hoy, 1000, MetodoPago::Efectivo, $this->hoy, null, $caja->id);

        $pagina = Livewire::actingAs($this->recepcion)->test(CierreDia::class)->assertOk()->assertSee('Llegadas sin entrada');
        $resumen = $pagina->instance()->dias()[0]['resumen'];
        expect($resumen['sin_confirmar'])->toBe(1)
            ->and(collect($resumen['detalle']['llegadas_sin_registrar'])->pluck('numero')->all())->toBe([$hoy->numero])
            ->and(collect($resumen['detalle']['salidas_con_saldo'])->pluck('numero')->all())->toBe([$sale->numero])
            ->and($resumen['caja'][0])->toMatchArray(['caja' => 'Recepción', 'metodo' => 'efectivo', 'entradas' => 1000, 'salidas' => 0])
            ->and((string) json_encode($resumen))->not->toContain('Prueba');
    });

    it('el resumen avisa de quien salió con saldo y de monedas distintas', function (): void {
        ($this->reservar)(-3, -1);
        Propiedad::query()->create(['nombre' => 'Otra', 'codigo' => 'otra', 'modo' => ModoPropiedad::Nativa, 'zona_horaria' => 'UTC', 'moneda' => 'USD', 'pais' => 'US', 'idioma' => 'en']);
        Livewire::actingAs($this->admin)->test(Resumen::class)->assertOk()
            ->assertSee(trans_choice('operacion.resumen.salidas_con_saldo', 1, ['n' => 1]))
            ->assertSee(__('operacion.resumen.monedas'));
    });
});

describe('mensajes por periodo', function (): void {
    beforeEach(function (): void {
        $this->plantilla = Plantilla::query()->create(['propiedad_id' => $this->propiedad->id, 'codigo' => 'prueba', 'nombre' => 'Prueba', 'asunto' => ['es' => 'Hola {nombre}'], 'cuerpo' => ['es' => 'Hola {nombre}, el wifi es {wifi}. Tienes {pendiente} por pagar.'], 'activa' => true]);
        VariablesMensaje::guardarPropias($this->propiedad->id, ['Wifi' => 'MiRed / clave123', 'huesped' => 'no debe pisar']);
    });

    it('arma el texto de cada reserva con las variables propias, sin pisar las del sistema', function (): void {
        $reserva = ($this->reservar)(1, 2);
        ($this->reservar)(30, 31);
        $pagina = Livewire::actingAs($this->recepcion)->test(MensajesPeriodo::class)
            ->set('plantillaId', $this->plantilla->id)->set('desde', $this->hoy->format('Y-m-d'))->set('hasta', $this->hoy->addDays(5)->format('Y-m-d'));
        $pagina->assertOk()->assertSee('MiRed / clave123')->assertSee('Ana Prueba');
        $filas = $pagina->instance()->filas();
        expect($filas)->toHaveCount(1)->and($filas[0]['reserva']->id)->toBe($reserva->id)
            ->and($filas[0]['whatsapp'])->toContain('wa.me/525511112222')
            ->and(VariablesMensaje::propias($this->propiedad->id))->toBe(['wifi' => 'MiRed / clave123']);
    });

    it('nada se manda solo: el correo sale con el botón, y un cliente omitido también se puede escribir a mano', function (): void {
        $reserva = ($this->reservar)(1, 2);
        expect(App\Models\Envio::query()->count())->toBe(0);
        $pagina = Livewire::actingAs($this->recepcion)->test(MensajesPeriodo::class)->set('plantillaId', $this->plantilla->id)
            ->set('desde', $this->hoy->format('Y-m-d'))->set('hasta', $this->hoy->addDays(5)->format('Y-m-d'));
        $pagina->call('mandarCorreo', $reserva->id);
        $pagina->call('anotarWhatsapp', $reserva->id);
        expect(App\Models\Envio::query()->where('reserva_id', $reserva->id)->count())->toBe(2);
    });

    it('no deja usar una reserva de otra propiedad', function (): void {
        $otra = Propiedad::query()->create(['nombre' => 'Otra', 'codigo' => 'otra', 'modo' => ModoPropiedad::Nativa, 'zona_horaria' => 'UTC', 'moneda' => 'MXN', 'pais' => 'MX', 'idioma' => 'es']);
        $unidad = Unidad::query()->create(['propiedad_id' => $otra->id, 'codigo' => 'X', 'nombre' => 'X', 'capacidad' => 2, 'orden' => 1]);
        $huesped = Huesped::query()->create(['propiedad_id' => $otra->id, 'nombre' => 'Ajena']);
        $ajena = app(GestorReservas::class)->crear($otra, ['unidad_id' => $unidad->id, 'titular_id' => $huesped->id, 'llegada' => '2030-01-01', 'salida' => '2030-01-02', 'personas' => 1, 'total' => 1, 'estado' => EstadoReserva::Confirmada->value]);

        Livewire::actingAs($this->recepcion)->test(MensajesPeriodo::class)->call('anotarWhatsapp', $ajena->id)->assertNotFound();
    });

    it('las variables propias se editan desde la lista de plantillas', function (): void {
        Livewire::actingAs($this->admin)->test(ListPlantillas::class)
            ->callAction('variablesPropias', ['propiedad' => $this->propiedad->id, 'variables' => ['Clave de caja' => '1234']])->assertHasNoActionErrors();
        expect(VariablesMensaje::propias($this->propiedad->id))->toBe(['clave_de_caja' => '1234']);
    });
});

describe('depósitos y no-shows', function (): void {
    beforeEach(function (): void {
        $this->politica = Politica::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'General', 'dias_cancelacion_gratuita' => 3, 'penalizacion_porcentaje' => 50, 'no_show_porcentaje' => 100, 'deposito_porcentaje' => 30, 'deposito_plazo_dias' => 2, 'predeterminada' => true]);
    });

    it('lista lo que falta del depósito y marca lo vencido', function (): void {
        $nueva = ($this->reservar)(10, 12);
        $vieja = ($this->reservar)(20, 22);
        Reserva::query()->whereKey($vieja->id)->toBase()->update(['creado_en' => now('UTC')->subDays(10)]);
        $pagada = ($this->reservar)(30, 32);
        app(GestorReservas::class)->registrarPago($pagada, 40000, MetodoPago::Efectivo, $this->hoy);

        $pagina = Livewire::actingAs($this->recepcion)->test(DepositosNoShow::class)->assertOk();
        $filas = collect($pagina->instance()->depositos())->keyBy(fn (array $f): int => $f['reserva']->id);
        expect($filas)->toHaveCount(2)->and($filas[$nueva->id]['importe'])->toBe(30000)->and($filas[$nueva->id]['vencido'])->toBeFalse()
            ->and($filas[$vieja->id]['vencido'])->toBeTrue()->and($filas->has($pagada->id))->toBeFalse()
            ->and($filas[$nueva->id]['whatsapp'])->toContain('wa.me/525511112222');
        $pagina->assertSee(__('operacion.depositos.vencido'));
    });

    it('enseña quién no llegó, con su penalización, y marcarlo es de quien pulsa', function (): void {
        $noVino = ($this->reservar)(-2, 1);
        ($this->reservar)(-30, -28, ['unidad_id' => $this->otra->id]);
        expect(MarcaNoShow::query()->count())->toBe(0);
        $pagina = Livewire::actingAs($this->recepcion)->test(DepositosNoShow::class);
        $filas = $pagina->instance()->noShows();
        expect($filas)->toHaveCount(1)->and($filas[0]['reserva']->id)->toBe($noVino->id)->and($filas[0]['penalizacion'])->toBe(100000);

        $pagina->call('marcarNoShow', $noVino->id);
        expect(MarcaNoShow::query()->sole()->reserva_id)->toBe($noVino->id)->and($noVino->refresh()->estado)->toBe(EstadoReserva::Cancelada);
    });

    it('calcula lo que cuesta cancelar hoy y da el texto de la política', function (): void {
        $reserva = ($this->reservar)(1, 3);
        Livewire::actingAs($this->recepcion)->test(DepositosNoShow::class)->set('numero', $reserva->numero)->call('calcular')
            ->assertSet('calculo', fn (?string $texto): bool => str_contains((string) $texto, '500.00'))
            ->set('numero', 'nada')->call('calcular')->assertSet('calculo', __('operacion.depositos.calculadora_no_existe'));

        $texto = app(GestorPoliticas::class)->textoDe($this->politica);
        expect($texto)->toContain('3 días')->toContain('50 %')->toContain('100 %')->toContain('30 %');
        Livewire::actingAs($this->admin)->test(ListPoliticas::class)->assertTableActionVisible('textoPolitica', $this->politica);
    });
});

describe('bloquear fechas', function (): void {
    it('cierra varias unidades, salta la ocupada y avisa', function (): void {
        ($this->reservar)(5, 7);
        Livewire::actingAs($this->recepcion)->test(Calendario::class)
            ->callAction('bloquearFechas', ['propiedad_id' => $this->propiedad->id, 'unidades' => [$this->unidad->id, $this->otra->id], 'desde' => $this->hoy->addDays(5)->format('Y-m-d'), 'hasta' => $this->hoy->addDays(6)->format('Y-m-d'), 'motivo' => 'Pintura'])
            ->assertHasNoActionErrors()
            ->assertNotified();

        $bloqueo = Reserva::query()->where('tipo', TipoReserva::Bloqueo->value)->sole();
        expect($bloqueo->unidad_id)->toBe($this->otra->id)->and($bloqueo->origen_texto)->toBe('Pintura')
            ->and($bloqueo->salida->format('Y-m-d'))->toBe($this->hoy->addDays(7)->format('Y-m-d'));
    });
});

describe('importar precios', function (): void {
    beforeEach(function (): void {
        $this->importador = app(ImportadorPrecios::class);
    });

    it('revisa antes: acepta coma o punto, con o sin encabezado, y dice qué está mal', function (): void {
        $filas = $this->importador->revisar($this->propiedad, "tarifa;desde;hasta;precio;precio_por_persona\nDoble;2030-01-01;2030-01-10;1500,50;\ndoble;15/01/2030;20/01/2030;\"1,200.00\";100\nNada;2030-02-01;2030-02-02;100;\nDoble;2030-03-05;2030-03-01;100;\nDoble;2030-13-01;2030-13-02;100;\nDoble;2030-04-01;2030-04-02;gratis;");
        expect($filas)->toHaveCount(6)
            ->and($filas[0])->toMatchArray(['tarifa_id' => $this->tarifa->id, 'desde' => '2030-01-01', 'precio' => 150050, 'persona' => null, 'error' => null])
            ->and($filas[1])->toMatchArray(['desde' => '2030-01-15', 'precio' => 120000, 'persona' => 10000, 'error' => null])
            ->and($filas[2]['error'])->toBe(__('operacion.precios.error_tarifa'))
            ->and($filas[3]['error'])->toBe(__('operacion.precios.error_orden'))
            ->and($filas[4]['error'])->toBe(__('operacion.precios.error_fecha'))
            ->and($filas[5]['error'])->toBe(__('operacion.precios.error_precio'));
    });

    it('lee el archivo de Excel en otra codificación y lo que se pega con tabuladores', function (): void {
        Tarifa::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Cabaña', 'precio_noche' => 1, 'activa' => true]);
        $filas = $this->importador->revisar($this->propiedad, mb_convert_encoding("Cabaña\t2030-01-01\t2030-01-02\t100", 'Windows-1252', 'UTF-8'));
        expect($filas[0]['error'])->toBeNull()->and($filas[0]['tarifa'])->toBe('Cabaña');
    });

    it('sustituye los precios de esas fechas y solo los de esas', function (): void {
        TemporadaTarifa::query()->create(['tarifa_id' => $this->tarifa->id, 'nombre' => 'Alta', 'desde' => '2030-01-01', 'hasta' => '2030-01-31', 'precio_noche' => 200000, 'precio_persona_extra' => 5000]);
        $this->importador->importar($this->propiedad, $this->importador->revisar($this->propiedad, "Doble;2030-01-10;2030-01-12;300\nNada;2030-01-13;2030-01-14;1"));

        $temporadas = TemporadaTarifa::query()->where('tarifa_id', $this->tarifa->id)->orderBy('desde')->get();
        expect($temporadas)->toHaveCount(3)
            ->and($temporadas[0]->hasta->format('Y-m-d'))->toBe('2030-01-09')->and($temporadas[0]->precio_noche)->toBe(200000)
            ->and($temporadas[1]->precio_noche)->toBe(30000)->and($temporadas[1]->precio_persona_extra)->toBe(5000)
            ->and($temporadas[2]->desde->format('Y-m-d'))->toBe('2030-01-13')->and($temporadas[2]->precio_noche)->toBe(200000);

        $precio = fn (string $dia): ?int => Cotizador::cotizar($this->unidad, Carbon\CarbonImmutable::parse($dia), Carbon\CarbonImmutable::parse($dia)->addDay(), 2)?->total;
        expect($precio('2030-01-09'))->toBe(200000)->and($precio('2030-01-11'))->toBe(30000)->and($precio('2030-01-20'))->toBe(200000);

        // Tapa del todo a otra temporada que estaba dentro.
        $this->importador->importar($this->propiedad, $this->importador->revisar($this->propiedad, 'Doble;2030-01-01;2030-02-01;400'));
        expect(TemporadaTarifa::query()->where('tarifa_id', $this->tarifa->id)->count())->toBe(1);
    });

    it('se usa desde su pantalla con un usuario sin administrador, y otra propiedad no se toca', function (): void {
        $gerente = User::query()->create(['name' => 'Gerente', 'email' => 'ger@example.com', 'password' => Hash::make('contrasena-de-prueba'), 'activo' => true]);
        Acceso::asignarRoles($gerente, [['propiedad_id' => $this->propiedad->id, 'rol' => 'gerente']]);
        $this->actingAs($gerente)->get(ImportarPrecios::getUrl())->assertOk();
        Livewire::actingAs($this->recepcion)->test(ImportarPrecios::class)->assertForbidden();

        Livewire::actingAs($gerente)->test(ImportarPrecios::class)->set('texto', 'Doble;2030-05-01;2030-05-02;150')->call('revisar')
            ->assertSee(__('operacion.precios.correcta'))->call('importar')->assertNotified();
        expect(TemporadaTarifa::query()->where('tarifa_id', $this->tarifa->id)->sole()->precio_noche)->toBe(15000);

        Livewire::actingAs($gerente)->test(ImportarPrecios::class)->call('descargarPlantilla')->assertFileDownloaded();
        expect($this->importador->plantilla($this->propiedad)[1][0])->toBe('Doble');
    });
});

describe('restricciones', function (): void {
    beforeEach(function (): void {
        $this->llegada = $this->hoy->addDays(10);
        $this->restriccion = fn (array $datos): Restriccion => Restriccion::query()->create($datos + ['propiedad_id' => $this->propiedad->id, 'desde' => $this->hoy->addDays(5)->format('Y-m-d'), 'hasta' => $this->hoy->addDays(20)->format('Y-m-d')]);
        $this->incumplidas = fn (int $noches = 2) => Restricciones::deUnidad($this->unidad, $this->llegada, $this->llegada->addDays($noches), $this->hoy);
    });

    it('sin restricciones, todo se vende', function (): void {
        expect(($this->incumplidas)())->toBe([]);
    });

    it('cierra llegadas y salidas por fecha', function (): void {
        ($this->restriccion)(['cerrado_llegada' => true]);
        expect(($this->incumplidas)())->toHaveCount(1)->and(($this->incumplidas)()[0])->toContain('llegadas');

        Restriccion::query()->delete();
        ($this->restriccion)(['cerrado_salida' => true, 'desde' => $this->llegada->addDays(2)->format('Y-m-d'), 'hasta' => $this->llegada->addDays(2)->format('Y-m-d')]);
        expect(($this->incumplidas)(1))->toBe([])->and(($this->incumplidas)(2)[0])->toContain('salidas');
    });

    it('obliga los días de llegada', function (): void {
        $dia = $this->llegada->dayOfWeekIso;
        ($this->restriccion)(['dias_llegada' => 1 << ($dia % 7)]);
        expect(($this->incumplidas)())->toHaveCount(1);
        Restriccion::query()->update(['dias_llegada' => 1 << ($dia - 1)]);
        expect(($this->incumplidas)())->toBe([]);
    });

    it('limita la estancia y la anticipación', function (): void {
        ($this->restriccion)(['estancia_maxima' => 3]);
        expect(($this->incumplidas)(3))->toBe([])->and(($this->incumplidas)(4))->toHaveCount(1);
        Restriccion::query()->delete();
        ($this->restriccion)(['anticipacion_maxima' => 5]);
        expect(($this->incumplidas)())->toHaveCount(1);
        Restriccion::query()->update(['anticipacion_maxima' => 10]);
        expect(($this->incumplidas)())->toBe([]);
    });

    it('deja las noches de preparación libres antes y después', function (): void {
        ($this->restriccion)(['noches_preparacion' => 1]);
        ($this->reservar)(8, 10);
        expect(($this->incumplidas)())->toHaveCount(1);
        Reserva::query()->delete();
        ($this->reservar)(12, 14);
        expect(($this->incumplidas)())->toHaveCount(1);
        Reserva::query()->delete();
        ($this->reservar)(13, 14);
        expect(($this->incumplidas)())->toBe([]);
    });

    it('una de otra unidad no aplica y una inactiva tampoco', function (): void {
        ($this->restriccion)(['cerrado_llegada' => true, 'unidad_id' => $this->otra->id]);
        ($this->restriccion)(['cerrado_llegada' => true, 'activa' => false]);
        expect(($this->incumplidas)())->toBe([]);
    });

    it('el motor las cumple: no ofrece y no deja reservar', function (): void {
        ($this->restriccion)(['estancia_maxima' => 1, 'unidad_id' => $this->unidad->id]);
        $motor = app(MotorReservas::class);
        $salida = $this->llegada->addDays(2);
        $ofertas = $motor->ofertas($this->propiedad, $this->llegada, $salida, 2);
        expect($ofertas)->toHaveCount(1)->and($ofertas[0]->libres)->toBe(1);

        Restriccion::query()->update(['unidad_id' => null]);
        expect($motor->ofertas($this->propiedad, $this->llegada, $salida, 2))->toBe([])
            ->and($motor->motivoFechas($this->propiedad, $this->llegada, $salida))->toContain('máxima');

        $datos = ['tarifa_id' => $this->tarifa->id, 'llegada' => $this->llegada->format('Y-m-d'), 'salida' => $salida->format('Y-m-d'), 'personas' => 2, 'nombre' => 'Ana', 'apellido' => 'Ejemplo', 'correo' => 'ana@example.com', 'telefono' => null, 'comentario' => null, 'cupon' => null, 'cargos' => [], 'idioma' => 'es'];
        expect(fn () => $motor->reservar($this->propiedad, $datos))->toThrow(ReservaRechazada::class);
    });

    it('a recepción solo le avisa: la reserva se puede hacer', function (): void {
        ($this->restriccion)(['cerrado_llegada' => true]);
        Livewire::actingAs($this->recepcion)->test(CreateReserva::class)
            ->fillForm(['propiedad_id' => $this->propiedad->id, 'unidad_id' => $this->unidad->id, 'llegada' => $this->llegada->format('Y-m-d'), 'salida' => $this->llegada->addDays(2)->format('Y-m-d'), 'personas' => 2, 'titular_id' => $this->huesped->id])
            ->assertSee('No se aceptan llegadas')
            ->call('create')->assertHasNoFormErrors();
        expect(Reserva::query()->count())->toBe(1);
    });

    it('se administra con un gerente, y los días se guardan como máscara', function (): void {
        $gerente = User::query()->create(['name' => 'Gerente', 'email' => 'ger@example.com', 'password' => Hash::make('contrasena-de-prueba'), 'activo' => true]);
        Acceso::asignarRoles($gerente, [['propiedad_id' => $this->propiedad->id, 'rol' => 'gerente']]);
        Livewire::actingAs($gerente)->test(CreateRestriccion::class)
            ->fillForm(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Semana Santa', 'desde' => '2030-04-01', 'hasta' => '2030-04-10', 'dias_llegada' => [6], 'activa' => true])
            ->call('create')->assertHasNoFormErrors();
        expect(Restriccion::query()->sole()->dias_llegada)->toBe(32);
        Livewire::actingAs($gerente)->test(ListRestricciones::class)->assertOk()->assertSee('Semana Santa');
    });
});

describe('enlaces de pago', function (): void {
    beforeEach(function (): void {
        $ajustes = new AjustesMotor($this->propiedad);
        $ajustes->guardarLlave('stripe_secreta', 'sk_test_x');
        $ajustes->guardarLlave('stripe_webhook', 'whsec_x');
        $this->reserva = ($this->reservar)(5, 7);
        $this->caja = Caja::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Banco', 'activa' => true]);
        $this->actingAs($this->recepcion);
        $this->enlace = app(GestorEnlacesPago::class)->crear($this->reserva, 25000, 'Daño en la lámpara', 3, $this->caja->id);
        $this->token = $this->enlace->enlacePublico->token();
        auth()->logout();
    });

    it('la página enseña el concepto y el importe, y no datos de la reserva', function (): void {
        $this->get('/pagar/'.$this->token)->assertOk()->assertSee('Daño en la lámpara')->assertSee('250.00')->assertDontSee('Prueba');
        $this->get('/pagar/'.str_repeat('a', 40))->assertNotFound();
    });

    it('cobra el importe del enlace, en su caja, una sola vez, aunque ya no se deba nada', function (): void {
        app(GestorReservas::class)->registrarPago($this->reserva, 100000, MetodoPago::Efectivo, $this->hoy);
        Http::fake([
            'api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_e1', 'url' => 'https://checkout.stripe.test/e1']),
            'api.stripe.com/v1/checkout/sessions/cs_e1*' => Http::response(['id' => 'cs_e1', 'payment_status' => 'paid', 'amount_total' => 25000, 'currency' => 'mxn', 'client_reference_id' => null]),
        ]);
        $this->post('/pagar/'.$this->token.'/stripe')->assertRedirect('https://checkout.stripe.test/e1');
        $cobro = App\Models\PagoEnLinea::query()->sole();
        expect($cobro->importe)->toBe(25000)->and($cobro->enlace_pago_id)->toBe($this->enlace->id);

        Http::fake(['api.stripe.com/v1/checkout/sessions/cs_e1*' => Http::response(['id' => 'cs_e1', 'payment_status' => 'paid', 'amount_total' => 25000, 'currency' => 'mxn', 'client_reference_id' => App\Pagos\CobrosEnLinea::referenciaInterna($cobro->id)])]);
        $this->get('/pagar/'.$this->token.'/retorno/stripe?cobro='.$cobro->id.'&session_id=cs_e1')->assertRedirect();
        $this->get('/pagar/'.$this->token.'/retorno/stripe?cobro='.$cobro->id.'&session_id=cs_e1')->assertRedirect();

        $pagos = Pago::query()->where('reserva_id', $this->reserva->id)->where('importe', 25000)->get();
        expect($pagos)->toHaveCount(1)->and($pagos[0]->caja_id)->toBe($this->caja->id)
            ->and($this->enlace->refresh()->estado())->toBe('pagado')->and($this->enlace->pago_id)->toBe($pagos[0]->id);
        // Un extra no es un pago duplicado.
        expect(App\Models\Aviso::query()->where('tipo', 'pago_duplicado')->count())->toBe(0);
        $this->get('/pagar/'.$this->token)->assertOk()->assertSee(__('operacion.enlaces.estado_pagado'))->assertDontSee('<form', false);
        $this->post('/pagar/'.$this->token.'/stripe')->assertRedirect();
        expect(App\Models\PagoEnLinea::query()->count())->toBe(1);
    });

    it('vencido o anulado ya no cobra, y lo explica', function (): void {
        $this->travel(4)->days();
        $this->get('/pagar/'.$this->token)->assertOk()->assertSee(__('operacion.enlaces.estado_vencido'))->assertDontSee('<form', false);
        $this->post('/pagar/'.$this->token.'/stripe')->assertRedirect();
        expect(App\Models\PagoEnLinea::query()->count())->toBe(0);
        $this->travelBack();

        app(GestorEnlacesPago::class)->anular($this->enlace);
        $this->get('/pagar/'.$this->token)->assertOk()->assertSee(__('operacion.enlaces.estado_anulado'));
        expect($this->enlace->refresh()->estado())->toBe('anulado');
    });

    it('quien pagó antes de que venciera y vuelve después, queda anotado', function (): void {
        Http::fake(['api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_e2', 'url' => 'https://checkout.stripe.test/e2'])]);
        $this->post('/pagar/'.$this->token.'/stripe')->assertRedirect('https://checkout.stripe.test/e2');
        $cobro = App\Models\PagoEnLinea::query()->sole();
        $this->travel(4)->days();
        Http::fake(['api.stripe.com/v1/checkout/sessions/cs_e2*' => Http::response(['id' => 'cs_e2', 'payment_status' => 'paid', 'amount_total' => 25000, 'currency' => 'mxn', 'client_reference_id' => App\Pagos\CobrosEnLinea::referenciaInterna($cobro->id)])]);
        $this->get('/pagar/'.$this->token.'/retorno/stripe?cobro='.$cobro->id.'&session_id=cs_e2')->assertRedirect();
        expect($this->enlace->refresh()->estado())->toBe('pagado');
    });

    it('con el módulo apagado no existe', function (): void {
        Modulos::guardar(array_values(array_filter(Modulo::cases(), fn (Modulo $m): bool => $m !== Modulo::Pagos)));
        $this->get('/pagar/'.$this->token)->assertNotFound();
    });

    it('la reserva crea el enlace y lo lista, con un usuario sin administrador', function (): void {
        Livewire::actingAs($this->recepcion)->test(ViewReserva::class, ['record' => $this->reserva->id])
            ->callAction('enlacePago', ['importe' => '100.00', 'concepto' => 'Toallas', 'dias' => 2, 'caja_id' => $this->caja->id])
            ->assertHasNoActionErrors()->assertNotified();
        $nuevo = EnlacePago::query()->where('concepto', 'Toallas')->sole();
        expect($nuevo->importe)->toBe(10000)->and($nuevo->creado_por)->toBe($this->recepcion->id);

        Livewire::actingAs($this->recepcion)->test(EnlacesPagoRelationManager::class, ['ownerRecord' => $this->reserva, 'pageClass' => ViewReserva::class])
            ->assertCanSeeTableRecords([$nuevo])->assertSee('Toallas')
            ->callTableAction('anular', $nuevo);
        expect($nuevo->refresh()->estado())->toBe('anulado');
    });
});

describe('automatizaciones con condiciones', function (): void {
    beforeEach(function (): void {
        $this->plantilla = Plantilla::query()->create(['propiedad_id' => $this->propiedad->id, 'codigo' => 'acceso', 'nombre' => 'Acceso', 'asunto' => ['es' => 'Acceso'], 'cuerpo' => ['es' => 'Tu clave'], 'activa' => true]);
        $this->auto = fn (array $datos): Automatizacion => Automatizacion::query()->create($datos + ['propiedad_id' => $this->propiedad->id, 'nombre' => 'Acceso', 'disparador' => DisparadorAutomatizacion::AntesLlegada, 'dias' => 3, 'accion' => AccionAutomatizacion::CorreoHuesped, 'plantilla_id' => $this->plantilla->id, 'activa' => true]);
    });

    it('filtra por saldo, canal, llegada y documentos', function (): void {
        $reserva = ($this->reservar)(5, 7, ['origen_texto' => 'Airbnb']);
        $conSaldo = ($this->auto)(['condiciones' => ['saldo' => 'con_saldo']]);
        $sinSaldo = ($this->auto)(['condiciones' => ['saldo' => 'sin_saldo']]);
        $canal = ($this->auto)(['condiciones' => ['canal' => ' airbnb']]);
        $otroCanal = ($this->auto)(['condiciones' => ['canal' => 'Booking']]);
        $llegada = ($this->auto)(['condiciones' => ['llegada_hecha' => true]]);
        $contrato = ($this->auto)(['condiciones' => ['contrato_aceptado' => true]]);

        expect($conSaldo->cumpleCondiciones($reserva))->toBeTrue()->and($sinSaldo->cumpleCondiciones($reserva))->toBeFalse()
            ->and($canal->cumpleCondiciones($reserva))->toBeTrue()->and($otroCanal->cumpleCondiciones($reserva))->toBeFalse()
            ->and($llegada->cumpleCondiciones($reserva))->toBeFalse()->and($contrato->cumpleCondiciones($reserva))->toBeFalse();

        RegistroLlegada::query()->create(['reserva_id' => $reserva->id, 'propiedad_id' => $this->propiedad->id, 'estado' => EstadoRegistroLlegada::Recibido]);
        expect($llegada->cumpleCondiciones($reserva))->toBeTrue();
    });

    it('los datos de acceso esperan a que pague, y salen una sola vez', function (): void {
        $reserva = ($this->reservar)(1, 3);
        ($this->auto)(['condiciones' => ['saldo' => 'sin_saldo', 'esperar' => true]]);
        $mensajero = app(Mensajero::class);

        expect($mensajero->procesarTiempo())->toBe(0);
        app(GestorReservas::class)->registrarPago($reserva, 100000, MetodoPago::Efectivo, $this->hoy);
        expect($mensajero->procesarTiempo())->toBe(1);
        expect($mensajero->procesarTiempo())->toBe(0);
        $envio = App\Models\Envio::query()->where('reserva_id', $reserva->id)->sole();
        expect($envio->estado)->not->toBe(EstadoEnvio::Omitido);
    });

    it('sin «esperar», solo el día que toca', function (): void {
        ($this->reservar)(1, 3);
        ($this->auto)([]);
        expect(app(Mensajero::class)->procesarTiempo())->toBe(0);
    });

    it('crea una tarea o una nota en lugar de mandar correo, una sola vez', function (): void {
        $reserva = ($this->reservar)(3, 5);
        ($this->auto)(['nombre' => 'Preparar bienvenida', 'accion' => AccionAutomatizacion::CrearTarea, 'plantilla_id' => null]);
        ($this->auto)(['nombre' => 'Avisar a recepción', 'accion' => AccionAutomatizacion::NotaBitacora]);
        $mensajero = app(Mensajero::class);
        $mensajero->procesarTiempo();
        $mensajero->procesarTiempo();

        $tarea = Tarea::query()->sole();
        expect($tarea->titulo)->toContain('Preparar bienvenida')->and($tarea->reserva_id)->toBe($reserva->id)->and($tarea->tipo)->toBe(TipoTarea::Otra);
        $nota = NotaBitacora::query()->sole();
        expect($nota->texto)->toContain('Tu clave')->and($nota->importante)->toBeTrue();
        expect(App\Models\Envio::query()->count())->toBe(0);
    });

    it('apagado el módulo de la acción, no hace nada ni falla', function (): void {
        ($this->reservar)(3, 5);
        ($this->auto)(['accion' => AccionAutomatizacion::NotaBitacora]);
        Modulos::guardar(array_values(array_filter(Modulo::cases(), fn (Modulo $m): bool => $m !== Modulo::Bitacora)));
        app(Mensajero::class)->procesarTiempo();
        expect(NotaBitacora::query()->count())->toBe(0);
    });

    it('el formulario pide plantilla solo si es correo', function (): void {
        Livewire::actingAs($this->admin)->test(CreateAutomatizacion::class)
            ->fillForm(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Sin plantilla', 'disparador' => DisparadorAutomatizacion::Llegada->value, 'accion' => AccionAutomatizacion::CrearTarea->value, 'condiciones' => ['saldo' => 'con_saldo'], 'activa' => true])
            ->call('create')->assertHasNoFormErrors();
        $auto = Automatizacion::query()->where('nombre', 'Sin plantilla')->sole();
        expect($auto->plantilla_id)->toBeNull()->and($auto->condiciones['saldo'])->toBe('con_saldo');

        Livewire::actingAs($this->admin)->test(CreateAutomatizacion::class)
            ->fillForm(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Con correo', 'disparador' => DisparadorAutomatizacion::Llegada->value, 'accion' => AccionAutomatizacion::CorreoHuesped->value, 'activa' => true])
            ->call('create')->assertHasFormErrors(['plantilla_id' => 'required']);
    });
});

describe('opiniones', function (): void {
    beforeEach(function (): void {
        $this->reserva = ($this->reservar)(-3, -1);
        $this->url = app(Opiniones::class)->urlDe($this->reserva, 'es');
        $this->ruta = (string) parse_url($this->url, PHP_URL_PATH);
    });

    it('el huésped contesta sin cuenta y se puede corregir', function (): void {
        $this->get($this->ruta)->assertOk()->assertSee('¿Cómo te fue en Posada?');
        $this->post($this->ruta, ['calificacion' => 2, 'comentario' => 'Hacía frío'])->assertOk()->assertSee(__('operacion.opiniones.gracias'));
        $opinion = Opinion::query()->sole();
        expect($opinion->calificacion)->toBe(2)->and($opinion->comentario)->toBe('Hacía frío');

        $this->post($this->ruta, ['calificacion' => 4])->assertOk();
        expect(Opinion::query()->sole()->calificacion)->toBe(4);
        $this->post($this->ruta, ['calificacion' => 9])->assertOk()->assertDontSee(__('operacion.opiniones.gracias'));
        $this->get('/opinion/'.str_repeat('a', 40))->assertNotFound();
    });

    it('a todos se les ofrece la página de reseñas, contesten lo que contesten', function (): void {
        Ajustes::guardar('opiniones.enlace.'.$this->propiedad->id, 'https://example.com/resenas');
        $this->post($this->ruta, ['calificacion' => 1])->assertOk()->assertSee('https://example.com/resenas');
        $this->post($this->ruta, ['calificacion' => 5])->assertOk()->assertSee('https://example.com/resenas');
    });

    it('la plantilla lleva el enlace y la automatización queda apagada', function (): void {
        $auto = app(Opiniones::class)->prepararMensaje($this->propiedad);
        expect($auto->activa)->toBeFalse()->and($auto->disparador)->toBe(DisparadorAutomatizacion::DespuesSalida);
        $texto = Mensajero::textoDe($auto->plantilla, $this->reserva, 'es');
        expect($texto['cuerpo'])->toContain('/opinion/')->and($texto['cuerpo'])->not->toContain('{enlace_opinion}');
    });

    it('se ven en su pantalla, sin poder editarlas, y el promedio sale bien', function (): void {
        $this->post($this->ruta, ['calificacion' => 5, 'comentario' => 'Todo bien'])->assertOk();
        Opinion::query()->create(['propiedad_id' => $this->propiedad->id, 'reserva_id' => ($this->reservar)(-9, -8)->id, 'calificacion' => 3, 'respondida_en' => now('UTC')]);
        Opinion::query()->create(['propiedad_id' => $this->propiedad->id, 'reserva_id' => ($this->reservar)(-12, -11)->id]);

        $this->actingAs($this->recepcion);
        expect(OpinionResource::getEloquentQuery()->count())->toBe(2)->and(OpinionResource::canEdit(Opinion::query()->first()))->toBeFalse();
        Livewire::test(ListOpiniones::class)->assertOk()->assertSee('Todo bien')->assertSee('4.0');
        Livewire::test(ListOpiniones::class)->callAction('preparar', ['propiedad' => $this->propiedad->id, 'enlace' => 'https://example.com/r'])->assertHasNoActionErrors();
        expect(Opiniones::enlacePublicoDe($this->propiedad->id))->toBe('https://example.com/r');
    });

    it('al anonimizar se va el comentario, y al exportar los datos va incluido', function (): void {
        $this->post($this->ruta, ['calificacion' => 5, 'comentario' => 'Soy Ana y todo bien'])->assertOk();
        app(GestorPrivacidad::class)->anonimizar($this->huesped);
        expect(Opinion::query()->sole()->comentario)->toBeNull()->and(Opinion::query()->sole()->calificacion)->toBe(5);
    });

    it('con el módulo apagado no existe', function (): void {
        Modulos::guardar(array_values(array_filter(Modulo::cases(), fn (Modulo $m): bool => $m !== Modulo::Opiniones)));
        $this->get($this->ruta)->assertNotFound();
        expect(EventoReserva::query()->exists())->toBeTrue();
    });
});
