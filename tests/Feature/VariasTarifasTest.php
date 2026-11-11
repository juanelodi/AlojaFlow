<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

use App\Dinero\GestorPoliticas;
use App\Enums\EstadoReserva;
use App\Enums\ModoPropiedad;
use App\Enums\ModoTarifa;
use App\Enums\TipoReserva;
use App\Filament\Resources\Politicas\Pages\CreatePolitica;
use App\Filament\Resources\Reservas\Pages\CreateReserva;
use App\Filament\Resources\Reservas\Pages\EditReserva;
use App\Filament\Resources\Tarifas\Pages\CreateTarifa;
use App\Filament\Resources\Tarifas\Pages\EditTarifa;
use App\Filament\Resources\Tarifas\Pages\ListTarifas;
use App\Filament\Resources\Unidades\Pages\CreateUnidad;
use App\Filament\Resources\Unidades\Pages\EditUnidad;
use App\Models\Huesped;
use App\Models\Politica;
use App\Models\Propiedad;
use App\Models\Reserva;
use App\Models\Tarifa;
use App\Models\TemporadaTarifa;
use App\Models\Unidad;
use App\Models\User;
use App\Modulos\Modulo;
use App\Modulos\Modulos;
use App\Motor\MotorReservas;
use App\Motor\Oferta;
use App\Reservas\Cotizador;
use App\Reservas\GestorReservas;
use App\Reservas\ImportadorPrecios;
use App\Reservas\ReservaRechazada;
use App\Seguridad\Acceso;
use App\Servicios\Disponibilidad;
use App\Servicios\Informes;
use App\Support\Ajustes;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesYPermisosSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RolesYPermisosSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Ajustes::olvidar();
    Modulos::guardar(Modulo::cases());
    $this->propiedad = Propiedad::query()->create([
        'nombre' => 'Posada', 'codigo' => 'posada', 'modo' => ModoPropiedad::Nativa, 'zona_horaria' => 'America/Mexico_City', 'moneda' => 'MXN', 'idioma' => 'es',
        'ajustes' => ['motor' => ['activo' => true, 'anticipo_porcentaje' => 50, 'minutos_retencion' => 30]],
    ]);
    $this->propiedad->refresh();
    $this->doble = Tarifa::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Doble', 'precio_noche' => 100000, 'personas_incluidas' => 2]);
    $this->otra = Tarifa::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Cuatro personas', 'precio_noche' => 180000, 'personas_incluidas' => 4, 'personas_minimas' => 3, 'personas_maximas' => 4]);
    $this->ajena = Tarifa::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'De otra unidad', 'precio_noche' => 10000, 'personas_incluidas' => 2]);
    $this->unidad = Unidad::query()->create(['propiedad_id' => $this->propiedad->id, 'tarifa_id' => $this->doble->id, 'codigo' => '101', 'nombre' => '101', 'capacidad' => 4, 'orden' => 1]);
    $this->unidad->otrasTarifas()->attach($this->otra->id);
    $this->derivar = fn (array $datos, ?Tarifa $base = null): Tarifa => Tarifa::query()->create($datos + [
        'propiedad_id' => $this->propiedad->id, 'nombre' => 'Derivada', 'tarifa_base_id' => ($base ?? $this->doble)->id, 'modo' => ModoTarifa::PorNoche, 'precio_noche' => 0, 'personas_incluidas' => 2,
    ]);
    $this->cotizar = fn (?Tarifa $tarifa, string $llegada = '2027-03-01', string $salida = '2027-03-03', int $personas = 2): ?App\Reservas\Cotizacion => Cotizador::cotizar(
        $this->unidad->refresh(),
        CarbonImmutable::parse($llegada),
        CarbonImmutable::parse($salida),
        $personas,
        $tarifa?->id,
    );
    $this->huesped = Huesped::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Ana', 'apellido1' => 'Ejemplo']);
    $this->reservar = fn (array $mas = []): Reserva => app(GestorReservas::class)->crear($this->propiedad, $mas + [
        'unidad_id' => $this->unidad->id, 'titular_id' => $this->huesped->id, 'llegada' => '2027-03-01', 'salida' => '2027-03-03', 'personas' => 2, 'estado' => EstadoReserva::Confirmada->value, 'total' => 200000,
    ]);
    $this->gerente = User::query()->create(['name' => 'Ger', 'email' => 'ger@example.com', 'password' => Hash::make('contrasena-de-prueba'), 'activo' => true, 'idioma' => 'es']);
    Acceso::asignarRoles($this->gerente, [['propiedad_id' => $this->propiedad->id, 'rol' => 'gerente']]);
    $this->hoy = $this->propiedad->hoy();
    $this->fechas = '?llegada='.$this->hoy->addDays(5)->format('Y-m-d').'&salida='.$this->hoy->addDays(7)->format('Y-m-d');
    $this->formulario = fn (array $mas = []): array => $mas + [
        'tarifa_id' => $this->doble->id, 'llegada' => $this->hoy->addDays(5)->format('Y-m-d'), 'salida' => $this->hoy->addDays(7)->format('Y-m-d'), 'personas' => 2,
        'nombre' => 'Ana', 'apellido' => 'Ejemplo', 'correo' => 'ana@example.com', 'telefono' => '+52 55 5550 0101', 'acepto' => '1',
    ];
    $this->ofertas = fn (int $personas = 2): array => app(MotorReservas::class)->ofertas($this->propiedad, $this->hoy->addDays(5), $this->hoy->addDays(7), $personas);
    $this->actingAs($this->gerente);
});

afterEach(fn () => Ajustes::olvidar());

describe('el precio', function (): void {
    it('cada unidad se cotiza con la tarifa que se pide, si es suya', function (): void {
        expect(($this->cotizar)(null)->total)->toBe(200000)
            ->and(($this->cotizar)(null)->tarifa)->toBe('Doble')
            ->and(($this->cotizar)($this->otra)->total)->toBe(360000)
            ->and(($this->cotizar)($this->otra)->tarifaId)->toBe($this->otra->id)
            // La de otra unidad no: un formulario manipulado no se lleva otro precio.
            ->and(($this->cotizar)($this->ajena)->total)->toBe(200000);
    });

    it('sin la principal activa usa la otra que tenga, y sin ninguna no hay precio', function (): void {
        $this->doble->update(['activa' => false]);
        expect(($this->cotizar)(null)->tarifa)->toBe('Cuatro personas');

        $this->otra->update(['activa' => false]);
        expect(($this->cotizar)(null))->toBeNull();
    });

    it('la que sale de otra cobra sus precios más el porcentaje, también en temporada y fin de semana', function (): void {
        $this->doble->update(['precio_fin_semana' => 150000]);
        TemporadaTarifa::query()->create(['tarifa_id' => $this->doble->id, 'nombre' => 'Alta', 'desde' => '2027-07-01', 'hasta' => '2027-07-31', 'precio_noche' => 200000]);
        $menos = ($this->derivar)(['ajuste_porcentaje' => -10]);
        $this->unidad->otrasTarifas()->attach($menos->id);

        // Lunes y martes.
        expect(($this->cotizar)($menos)->total)->toBe(180000)
            // Viernes y sábado.
            ->and(($this->cotizar)($menos, '2027-03-05', '2027-03-07')->total)->toBe(270000)
            // Dos noches de temporada: un jueves y un viernes sin precio de fin de semana propio.
            ->and(($this->cotizar)($menos, '2027-07-01', '2027-07-03')->total)->toBe(360000);
    });

    it('si cambia la base, cambia sola', function (): void {
        $menos = ($this->derivar)(['ajuste_porcentaje' => -10]);
        $this->unidad->otrasTarifas()->attach($menos->id);

        $this->doble->update(['precio_noche' => 200000]);

        expect(($this->cotizar)($menos)->total)->toBe(360000);
    });

    it('puede sumar un importe por noche, solo o con el porcentaje', function (): void {
        $desayuno = ($this->derivar)(['nombre' => 'Con desayuno', 'ajuste_noche' => 15000]);
        $ambos = ($this->derivar)(['nombre' => 'Ambos', 'ajuste_porcentaje' => 10, 'ajuste_noche' => 5000]);
        $this->unidad->otrasTarifas()->attach([$desayuno->id, $ambos->id]);

        expect(($this->cotizar)($desayuno)->total)->toBe(230000)
            ->and(($this->cotizar)($ambos)->total)->toBe(230000);
    });

    it('redondea lo acumulado, y nunca da negativo', function (): void {
        $this->doble->update(['precio_noche' => 33333]);
        $tercio = ($this->derivar)(['nombre' => 'Tercio', 'ajuste_porcentaje' => -33.33]);
        $gratis = ($this->derivar)(['nombre' => 'Gratis', 'ajuste_porcentaje' => -100]);
        $this->unidad->otrasTarifas()->attach([$tercio->id, $gratis->id]);

        $cotizacion = ($this->cotizar)($tercio, '2027-03-01', '2027-03-04');
        expect($cotizacion->total)->toBe(array_sum($cotizacion->noches))
            ->and($cotizacion->total)->toBe((int) round(33333 * 3 * 0.6667))
            ->and(($this->cotizar)($gratis)->total)->toBe(0);
    });

    it('pide la estancia mínima más larga entre la suya y la de la base', function (): void {
        $this->doble->update(['estancia_minima' => 3]);
        $dos = ($this->derivar)(['nombre' => 'Dos', 'estancia_minima' => 1]);
        $cinco = ($this->derivar)(['nombre' => 'Cinco', 'estancia_minima' => 5]);
        $this->unidad->otrasTarifas()->attach([$dos->id, $cinco->id]);

        expect(($this->cotizar)($dos)->estanciaMinima)->toBe(3)
            ->and(($this->cotizar)($cinco)->estanciaMinima)->toBe(5);
    });

    it('usa las estancias largas de la base con su ajuste', function (): void {
        $this->doble->update(['precio_semana' => 490000]);
        $menos = ($this->derivar)(['ajuste_porcentaje' => -10]);
        $this->unidad->otrasTarifas()->attach($menos->id);

        $cotizacion = ($this->cotizar)($menos, '2027-03-01', '2027-03-08');

        expect($cotizacion->total)->toBe(441000)
            ->and($cotizacion->estanciaLarga)->toBe('semana');
    });
});

describe('la reserva', function (): void {
    it('guarda con qué tarifa se vendió', function (): void {
        $reserva = ($this->reservar)(['tarifa_id' => $this->otra->id]);

        expect($reserva->tarifa_id)->toBe($this->otra->id)
            ->and($reserva->tarifa_nombre)->toBe('Cuatro personas');
    });

    it('sin elegir, la principal; una de otra unidad no entra', function (): void {
        expect(($this->reservar)()->tarifa_id)->toBe($this->doble->id)
            ->and(($this->reservar)(['llegada' => '2027-04-01', 'salida' => '2027-04-03', 'tarifa_id' => $this->ajena->id])->tarifa_nombre)->toBe('Doble');
    });

    it('la conserva al cambiar otra cosa, aunque la hayan retirado o se cambie de unidad', function (): void {
        $reserva = ($this->reservar)(['tarifa_id' => $this->otra->id]);
        $this->otra->update(['activa' => false]);
        $otraUnidad = Unidad::query()->create(['propiedad_id' => $this->propiedad->id, 'tarifa_id' => $this->doble->id, 'codigo' => '102', 'nombre' => '102', 'capacidad' => 4, 'orden' => 2]);

        $cambiada = app(GestorReservas::class)->modificar($reserva, ['unidad_id' => $otraUnidad->id, 'titular_id' => $this->huesped->id, 'llegada' => '2027-03-01', 'salida' => '2027-03-04', 'personas' => 2, 'total' => 200000]);

        expect($cambiada->tarifa_id)->toBe($this->otra->id)
            ->and($cambiada->tarifa_nombre)->toBe('Cuatro personas');
    });

    it('cambia si alguien elige otra', function (): void {
        $reserva = ($this->reservar)(['tarifa_id' => $this->otra->id]);

        $cambiada = app(GestorReservas::class)->modificar($reserva, ['unidad_id' => $this->unidad->id, 'titular_id' => $this->huesped->id, 'llegada' => '2027-03-01', 'salida' => '2027-03-03', 'personas' => 2, 'total' => 200000, 'tarifa_id' => $this->doble->id]);

        expect($cambiada->tarifa_id)->toBe($this->doble->id)
            ->and($cambiada->tarifa_nombre)->toBe('Doble');
    });

    it('un bloqueo no lleva tarifa', function (): void {
        $bloqueo = app(GestorReservas::class)->crear($this->propiedad, ['tipo' => TipoReserva::Bloqueo->value, 'unidad_id' => $this->unidad->id, 'llegada' => '2027-03-01', 'salida' => '2027-03-03', 'tarifa_id' => $this->otra->id]);

        expect($bloqueo->tarifa_id)->toBeNull();
    });

    it('se sigue encontrando la primera libre por cualquiera de sus tarifas', function (): void {
        expect(Disponibilidad::primeraLibre($this->propiedad->id, '2027-03-01', '2027-03-03', 2, $this->otra->id)?->id)->toBe($this->unidad->id)
            ->and(Disponibilidad::primeraLibre($this->propiedad->id, '2027-03-01', '2027-03-03', 2, $this->ajena->id))->toBeNull();
    });
});

describe('la política', function (): void {
    beforeEach(function (): void {
        $this->general = Politica::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'General', 'dias_cancelacion_gratuita' => 3, 'penalizacion_porcentaje' => 50, 'predeterminada' => true]);
        $this->estricta = Politica::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Sin devolución', 'no_reembolsable' => true, 'no_show_porcentaje' => 100, 'deposito_porcentaje' => 100]);
    });

    it('la de la tarifa manda sobre la general, y la del canal sobre las dos', function (): void {
        $this->otra->update(['politica_id' => $this->estricta->id]);
        $politicas = app(GestorPoliticas::class);

        expect($politicas->aplicable(($this->reservar)())->nombre)->toBe('General')
            ->and($politicas->aplicable(($this->reservar)(['llegada' => '2027-04-01', 'salida' => '2027-04-03', 'tarifa_id' => $this->otra->id]))->nombre)->toBe('Sin devolución');

        App\Models\Canal::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Booking', 'politica_id' => $this->general->id]);
        expect($politicas->aplicable(($this->reservar)(['llegada' => '2027-05-01', 'salida' => '2027-05-03', 'tarifa_id' => $this->otra->id, 'origen_texto' => 'Booking']))->nombre)->toBe('General');
    });

    it('la que sale de otra toma la de aquella si no tiene la suya', function (): void {
        $this->doble->update(['politica_id' => $this->estricta->id]);
        $menos = ($this->derivar)(['ajuste_porcentaje' => -10]);

        expect($menos->refresh()->politicaPropia()?->nombre)->toBe('Sin devolución');
    });

    it('no reembolsable cobra el total aunque falten meses', function (): void {
        $reserva = ($this->reservar)(['llegada' => $this->hoy->addDays(200)->format('Y-m-d'), 'salida' => $this->hoy->addDays(202)->format('Y-m-d')]);
        $politicas = app(GestorPoliticas::class);

        expect($politicas->penalizacionCancelacion($reserva, $this->estricta))->toBe(200000)
            ->and($politicas->penalizacionCancelacion($reserva, $this->general))->toBe(0)
            ->and($politicas->textoDe($this->estricta))->toContain(__('operacion.politicas.texto_no_reembolsable'));
    });

    it('la pantalla crea una no reembolsable, sin pedir los días', function (): void {
        Livewire::actingAs($this->gerente)->test(CreatePolitica::class)
            ->fillForm(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Dura', 'no_reembolsable' => true])
            ->assertFormFieldHidden('dias_cancelacion_gratuita')
            ->call('create')
            ->assertHasNoFormErrors();

        expect(Politica::query()->where('nombre', 'Dura')->sole()->no_reembolsable)->toBeTrue();
    });

    it('lo que falta de depósito sale de la política de su tarifa', function (): void {
        $this->otra->update(['politica_id' => $this->estricta->id]);
        $reserva = ($this->reservar)(['tarifa_id' => $this->otra->id, 'total' => 360000]);

        expect(app(GestorPoliticas::class)->depositoPendiente($reserva, $this->estricta))->toBe(360000);
    });
});

describe('el motor', function (): void {
    it('saca una opción por cada tarifa de la unidad, la más barata primero', function (): void {
        $menos = ($this->derivar)(['nombre' => 'No reembolsable', 'ajuste_porcentaje' => -10, 'descripcion_publica' => 'Pagas al reservar']);
        $this->unidad->otrasTarifas()->attach($menos->id);

        $ofertas = ($this->ofertas)();

        expect(array_map(fn (Oferta $o): string => $o->tarifa->nombre, $ofertas))->toBe(['No reembolsable', 'Doble'])
            ->and($ofertas[0]->presupuesto->total())->toBe(180000)
            ->and($ofertas[0]->libres)->toBe(1);

        $this->get('/reservar/posada'.$this->fechas.'&personas=2')->assertOk()->assertSee('No reembolsable')->assertSee('Pagas al reservar')->assertSee('$1,800.00')->assertSee('$2,000.00');
    });

    it('respeta el mínimo y el máximo de personas de cada tarifa', function (): void {
        expect(array_map(fn (Oferta $o): string => $o->tarifa->nombre, ($this->ofertas)(2)))->toBe(['Doble'])
            ->and(array_map(fn (Oferta $o): string => $o->tarifa->nombre, ($this->ofertas)(3)))->toBe(['Doble', 'Cuatro personas'])
            ->and(($this->ofertas)(3)[1]->capacidad)->toBe(4);

        $this->otra->update(['personas_maximas' => 3]);
        expect(($this->ofertas)(3)[1]->capacidad)->toBe(3)
            ->and(array_map(fn (Oferta $o): string => $o->tarifa->nombre, ($this->ofertas)(4)))->toBe(['Doble']);
    });

    it('reserva con la tarifa elegida, a su precio', function (): void {
        $menos = ($this->derivar)(['nombre' => 'No reembolsable', 'ajuste_porcentaje' => -10]);
        $this->unidad->otrasTarifas()->attach($menos->id);

        $this->post('/reservar/posada', ($this->formulario)(['tarifa_id' => $menos->id]))->assertRedirect();

        $reserva = Reserva::query()->sole();
        expect($reserva->tarifa_id)->toBe($menos->id)
            ->and($reserva->tarifa_nombre)->toBe('No reembolsable')
            ->and($reserva->total)->toBe(180000)
            ->and($reserva->unidad_id)->toBe($this->unidad->id);
    });

    it('no vende una tarifa fuera de sus personas, ni una que ninguna unidad libre tiene', function (): void {
        $this->post('/reservar/posada', ($this->formulario)(['tarifa_id' => $this->otra->id, 'personas' => 2]))->assertRedirectContains('aviso=sin_lugar');
        $this->post('/reservar/posada', ($this->formulario)(['tarifa_id' => $this->ajena->id]))->assertRedirectContains('aviso=sin_lugar');

        expect(Reserva::query()->count())->toBe(0);
    });

    it('la política de la tarifa decide el anticipo, y se lee antes de reservar', function (): void {
        $estricta = Politica::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Sin devolución', 'no_reembolsable' => true, 'deposito_porcentaje' => 100]);
        $menos = ($this->derivar)(['nombre' => 'No reembolsable', 'ajuste_porcentaje' => -10, 'politica_id' => $estricta->id]);
        $this->unidad->otrasTarifas()->attach($menos->id);
        $ofertas = collect(($this->ofertas)())->keyBy(fn (Oferta $o): string => $o->tarifa->nombre);

        expect($ofertas['No reembolsable']->anticipo)->toBe(100.0)
            ->and($ofertas['Doble']->anticipo)->toBe(50.0)
            ->and($ofertas['No reembolsable']->politica)->toContain(__('operacion.politicas.texto_no_reembolsable'))
            ->and($ofertas['Doble']->politica)->toBeNull();

        $pagina = $this->get('/reservar/posada/datos'.$this->fechas.'&personas=2&tarifa='.$menos->id)->assertOk();
        $pagina->assertSee(__('operacion.politicas.texto_no_reembolsable'))->assertSee(__('motor.anticipo', ['porcentaje' => App\Support\Formato::tasa(100.0)]));

        $this->post('/reservar/posada', ($this->formulario)(['tarifa_id' => $menos->id]))->assertRedirect();
        expect(Reserva::query()->sole()->deposito)->toBe(180000);
    });

    it('la que no tiene política propia sigue con el anticipo del motor', function (): void {
        $this->post('/reservar/posada', ($this->formulario)())->assertRedirect();

        expect(Reserva::query()->sole()->deposito)->toBe(100000);
    });

    it('la que sale de otra usa las fotos de aquella si no tiene', function (): void {
        Illuminate\Support\Facades\Storage::fake('local');
        app(App\Publico\GestorFotos::class)->recibir(imagenDeTarifas(), $this->propiedad, $this->doble->id, 'Vista al mar');
        $menos = ($this->derivar)(['nombre' => 'No reembolsable', 'ajuste_porcentaje' => -10]);
        $this->unidad->otrasTarifas()->attach($menos->id);

        $pagina = (string) $this->get('/reservar/posada'.$this->fechas.'&personas=2')->assertOk()->getContent();

        expect(substr_count($pagina, 'Vista al mar'))->toBeGreaterThanOrEqual(2);
    });

    it('un lugar del plano se ofrece con la más barata de sus tarifas', function (): void {
        (new App\Motor\AjustesMotor($this->propiedad))->guardar(['elegir_lugar' => true]);
        $this->propiedad->refresh();
        Illuminate\Support\Facades\Storage::fake('local');
        $archivo = app(App\Archivos\GestorArchivos::class)->guardar(imagenDeTarifas(), 'plano.jpg', 'image/jpeg', App\Enums\ClaseArchivo::Plano, $this->propiedad, $this->propiedad->id);
        $plano = App\Models\Plano::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Mapa', 'archivo_id' => $archivo->id]);
        $plano->posiciones()->create(['unidad_id' => $this->unidad->id, 'x' => 10, 'y' => 10]);
        $menos = ($this->derivar)(['nombre' => 'No reembolsable', 'ajuste_porcentaje' => -10]);
        $this->unidad->otrasTarifas()->attach($menos->id);
        $motor = app(MotorReservas::class);

        $mapas = $motor->mapas($this->propiedad, ($this->ofertas)(), $this->hoy->addDays(5), $this->hoy->addDays(7), 2);

        expect($mapas[0]['lugares'][0]['oferta']?->tarifa->id)->toBe($menos->id);
    });
});

function imagenDeTarifas(): string
{
    $imagen = imagecreatetruecolor(1200, 800);
    imagefill($imagen, 0, 0, (int) imagecolorallocate($imagen, 200, 220, 200));
    ob_start();
    imagejpeg($imagen);

    return (string) ob_get_clean();
}

describe('el panel', function (): void {
    it('en la reserva solo pregunta la tarifa si la unidad tiene más de una', function (): void {
        $sola = Unidad::query()->create(['propiedad_id' => $this->propiedad->id, 'tarifa_id' => $this->doble->id, 'codigo' => '102', 'nombre' => '102', 'capacidad' => 2, 'orden' => 2]);

        Livewire::actingAs($this->gerente)->test(CreateReserva::class)
            ->fillForm(['propiedad_id' => $this->propiedad->id, 'llegada' => '2027-03-01', 'salida' => '2027-03-03', 'personas' => 2, 'unidad_id' => $sola->id])
            ->assertFormFieldHidden('tarifa_id')
            ->fillForm(['unidad_id' => $this->unidad->id])
            ->assertFormFieldVisible('tarifa_id');
    });

    it('elegir otra tarifa cambia el total al crear, y se guarda', function (): void {
        Livewire::actingAs($this->gerente)->test(CreateReserva::class)
            ->fillForm(['propiedad_id' => $this->propiedad->id, 'llegada' => '2027-03-01', 'salida' => '2027-03-03', 'personas' => 3, 'titular_id' => $this->huesped->id])
            ->set('data.unidad_id', $this->unidad->id)
            ->set('data.tarifa_id', $this->otra->id)
            ->assertFormSet(['total' => '3600.00'])
            ->call('create')
            ->assertHasNoFormErrors();

        $reserva = Reserva::query()->sole();
        expect($reserva->tarifa_id)->toBe($this->otra->id)
            ->and($reserva->total)->toBe(360000);
    });

    it('al cambiar a una unidad que no la vende, se quita la tarifa elegida', function (): void {
        $sola = Unidad::query()->create(['propiedad_id' => $this->propiedad->id, 'tarifa_id' => $this->ajena->id, 'codigo' => '102', 'nombre' => '102', 'capacidad' => 4, 'orden' => 2]);

        Livewire::actingAs($this->gerente)->test(CreateReserva::class)
            ->fillForm(['propiedad_id' => $this->propiedad->id, 'llegada' => '2027-03-01', 'salida' => '2027-03-03', 'personas' => 3])
            ->set('data.unidad_id', $this->unidad->id)
            ->set('data.tarifa_id', $this->otra->id)
            ->set('data.unidad_id', $sola->id)
            ->assertFormSet(['tarifa_id' => null, 'total' => '200.00']);
    });

    it('al editar, la que ya tiene sigue en la lista aunque la hayan retirado, y el total no se mueve solo', function (): void {
        $reserva = ($this->reservar)(['tarifa_id' => $this->otra->id]);
        $this->otra->update(['activa' => false]);
        $sigue = Tarifa::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Otra activa', 'precio_noche' => 90000, 'personas_incluidas' => 2]);
        $this->unidad->otrasTarifas()->attach($sigue->id);

        Livewire::actingAs($this->gerente)->test(EditReserva::class, ['record' => $reserva->id])
            ->assertFormSet(['tarifa_id' => $this->otra->id, 'total' => '2000.00'])
            ->assertFormFieldVisible('tarifa_id')
            ->set('data.comentario', 'Llega tarde')
            ->call('save')
            ->assertHasNoFormErrors();

        expect($reserva->refresh()->tarifa_id)->toBe($this->otra->id)
            ->and($reserva->total)->toBe(200000)
            ->and($reserva->comentario)->toBe('Llega tarde');
    });

    it('avisa si las personas no son las de la tarifa, sin impedirlo', function (): void {
        Livewire::actingAs($this->gerente)->test(CreateReserva::class)
            ->fillForm(['propiedad_id' => $this->propiedad->id, 'llegada' => '2027-03-01', 'salida' => '2027-03-03', 'personas' => 2, 'titular_id' => $this->huesped->id])
            ->set('data.unidad_id', $this->unidad->id)
            ->set('data.tarifa_id', $this->otra->id)
            ->assertSee(__('ui.reservas.aviso_tarifa_minimo', ['tarifa' => 'Cuatro personas', 'personas' => 3]))
            ->call('create')
            ->assertHasNoFormErrors();
    });

    it('crea una tarifa que sale de otra, sin pedirle precios', function (): void {
        Livewire::actingAs($this->gerente)->test(CreateTarifa::class)
            ->fillForm(['propiedad_id' => $this->propiedad->id, 'nombre' => 'No reembolsable', 'estancia_minima' => 1])
            ->set('data.tarifa_base_id', $this->doble->id)
            ->assertFormFieldHidden('precio_noche')
            ->assertFormFieldHidden('modo')
            ->assertFormFieldVisible('ajuste_porcentaje')
            ->fillForm(['ajuste_porcentaje' => -10, 'ajuste_noche' => '150'])
            ->call('create')
            ->assertHasNoFormErrors();

        $tarifa = Tarifa::query()->where('nombre', 'No reembolsable')->sole();
        expect($tarifa->tarifa_base_id)->toBe($this->doble->id)
            ->and((float) $tarifa->ajuste_porcentaje)->toBe(-10.0)
            ->and($tarifa->ajuste_noche)->toBe(15000)
            ->and($tarifa->precio_noche)->toBe(0)
            ->and($tarifa->modo)->toBe($this->doble->refresh()->modo);
    });

    it('asigna unidades desde la tarifa, solo de su propiedad', function (): void {
        $otraPropiedad = Propiedad::query()->create(['nombre' => 'Otra', 'codigo' => 'otra', 'modo' => ModoPropiedad::Nativa, 'zona_horaria' => 'UTC', 'moneda' => 'MXN', 'idioma' => 'es']);
        $ajena = Unidad::query()->create(['propiedad_id' => $otraPropiedad->id, 'codigo' => 'X', 'nombre' => 'X', 'capacidad' => 2]);
        $sin = Unidad::query()->create(['propiedad_id' => $this->propiedad->id, 'codigo' => '103', 'nombre' => '103', 'capacidad' => 2, 'orden' => 3]);

        Livewire::actingAs($this->gerente)->test(EditTarifa::class, ['record' => $this->ajena->id])
            ->assertFormSet(['otras_unidades' => []])
            ->fillForm(['otras_unidades' => [$sin->id, $this->unidad->id]])
            ->call('save')
            ->assertHasNoFormErrors();

        // Aunque el formulario la dejara pasar, la de otra propiedad no entra.
        App\Filament\Resources\Tarifas\Schemas\TarifaForm::guardarOtrasUnidades($this->ajena, [$sin->id, $ajena->id, $this->unidad->id]);

        expect($this->ajena->otrasUnidades()->pluck('unidades.id')->sort()->values()->all())->toBe(collect([$this->unidad->id, $sin->id])->sort()->values()->all());
    });

    it('una unidad inactiva que ya la vendía no impide guardar la tarifa', function (): void {
        $this->otra->update(['descripcion_publica' => 'Antes']);
        $this->unidad->update(['activa' => false]);

        Livewire::actingAs($this->gerente)->test(EditTarifa::class, ['record' => $this->otra->id])
            ->assertFormSet(['otras_unidades' => [$this->unidad->id]])
            ->fillForm(['descripcion_publica' => 'Después'])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($this->otra->refresh()->descripcion_publica)->toBe('Después')
            ->and($this->otra->otrasUnidades()->pluck('unidades.id')->all())->toBe([$this->unidad->id]);
    });

    it('asigna tarifas desde la unidad, y las vuelve a enseñar', function (): void {
        Livewire::actingAs($this->gerente)->test(EditUnidad::class, ['record' => $this->unidad->id])
            ->assertFormSet(['otras_tarifas' => [$this->otra->id]])
            ->fillForm(['otras_tarifas' => [$this->ajena->id]])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($this->unidad->otrasTarifas()->pluck('tarifas.id')->all())->toBe([$this->ajena->id]);
    });

    it('una unidad nueva nace con sus otras tarifas', function (): void {
        Livewire::actingAs($this->gerente)->test(CreateUnidad::class)
            ->fillForm(['propiedad_id' => $this->propiedad->id, 'nombre' => '105', 'capacidad' => 2, 'tarifa_id' => $this->doble->id, 'otras_tarifas' => [$this->otra->id, $this->doble->id]])
            ->call('create')
            ->assertHasNoFormErrors();

        // La principal no hace falta repetirla.
        expect(Unidad::query()->where('nombre', '105')->sole()->otrasTarifas()->pluck('tarifas.id')->all())->toBe([$this->otra->id]);
    });

    it('la base no puede salir de otra, ni de sí misma, ni ser de otra propiedad', function (): void {
        $hija = ($this->derivar)(['nombre' => 'Hija', 'ajuste_porcentaje' => -5]);
        $otraPropiedad = Propiedad::query()->create(['nombre' => 'Otra', 'codigo' => 'otra', 'modo' => ModoPropiedad::Nativa, 'zona_horaria' => 'UTC', 'moneda' => 'MXN', 'idioma' => 'es']);
        $deOtra = Tarifa::query()->create(['propiedad_id' => $otraPropiedad->id, 'nombre' => 'Ajena', 'precio_noche' => 100, 'personas_incluidas' => 2]);

        expect(fn () => App\Filament\Resources\Tarifas\Schemas\TarifaForm::normalizar(['tarifa_base_id' => $hija->id], $this->propiedad->id))->toThrow(Symfony\Component\HttpKernel\Exception\HttpException::class)
            ->and(fn () => App\Filament\Resources\Tarifas\Schemas\TarifaForm::normalizar(['tarifa_base_id' => $deOtra->id], $this->propiedad->id))->toThrow(Symfony\Component\HttpKernel\Exception\HttpException::class)
            ->and(fn () => App\Filament\Resources\Tarifas\Schemas\TarifaForm::normalizar(['tarifa_base_id' => $this->otra->id], $this->propiedad->id, $this->otra))->toThrow(Symfony\Component\HttpKernel\Exception\HttpException::class)
            // De la que ya tiene hijas no se saca: daría dos vueltas.
            ->and(fn () => App\Filament\Resources\Tarifas\Schemas\TarifaForm::normalizar(['tarifa_base_id' => $this->otra->id], $this->propiedad->id, $this->doble))->toThrow(Symfony\Component\HttpKernel\Exception\HttpException::class);

        $datos = App\Filament\Resources\Tarifas\Schemas\TarifaForm::normalizar(['tarifa_base_id' => null, 'ajuste_porcentaje' => 5, 'ajuste_noche' => 10, 'politica_id' => 999999], $this->propiedad->id);
        expect($datos['ajuste_porcentaje'])->toBeNull()
            ->and($datos['ajuste_noche'])->toBeNull()
            ->and($datos['politica_id'])->toBeNull();
    });

    it('no deja eliminar una tarifa de la que salen otras', function (): void {
        ($this->derivar)(['nombre' => 'Hija', 'ajuste_porcentaje' => -5]);

        Livewire::actingAs($this->gerente)->test(EditTarifa::class, ['record' => $this->doble->id])
            ->callAction('delete')
            ->assertNotified(__('ui.tarifas.eliminar_con_derivadas', ['tarifas' => 'Hija']));

        expect(Tarifa::query()->whereKey($this->doble->id)->exists())->toBeTrue();

        Livewire::actingAs($this->gerente)->test(EditTarifa::class, ['record' => $this->ajena->id])->callAction('delete');
        expect(Tarifa::query()->whereKey($this->ajena->id)->exists())->toBeFalse();
    });

    it('la lista dice de cuál sale y con qué ajuste', function (): void {
        ($this->derivar)(['nombre' => 'Hija', 'ajuste_porcentaje' => -5, 'ajuste_noche' => 10000]);

        Livewire::actingAs($this->gerente)->test(ListTarifas::class)
            ->assertSee('Hija')
            ->assertSee('-5 %')
            ->assertSee(App\Support\Formato::dinero(10000, 'MXN'));
    });

    it('un recepcionista no administra tarifas', function (): void {
        $recepcion = User::query()->create(['name' => 'Rec', 'email' => 'rec@example.com', 'password' => Hash::make('contrasena-de-prueba'), 'activo' => true, 'idioma' => 'es']);
        Acceso::asignarRoles($recepcion, [['propiedad_id' => $this->propiedad->id, 'rol' => 'recepcion']]);
        $this->actingAs($recepcion);

        expect(App\Filament\Resources\Tarifas\TarifaResource::canViewAny())->toBeFalse();
    });
});

describe('importar precios e informes', function (): void {
    it('no importa precios a la que sale de otra, y no la pone en la plantilla', function (): void {
        ($this->derivar)(['nombre' => 'Hija', 'ajuste_porcentaje' => -5]);
        $importador = app(ImportadorPrecios::class);

        $filas = $importador->revisar($this->propiedad, "Hija;2027-01-01;2027-01-31;500\nDoble;2027-01-01;2027-01-31;500");

        expect($filas[0]['error'])->toBe(__('operacion.precios.error_tarifa_basada'))
            ->and($filas[1]['error'])->toBeNull()
            ->and(collect($importador->plantilla($this->propiedad))->flatten()->contains('Hija'))->toBeFalse();
    });

    it('el informe dice cuánto vendió cada tarifa, con el nombre de la reserva', function (): void {
        ($this->reservar)();
        ($this->reservar)(['llegada' => '2027-03-05', 'salida' => '2027-03-08', 'tarifa_id' => $this->otra->id, 'total' => 540000]);
        $this->otra->update(['nombre' => 'Otro nombre']);

        $informe = Informes::generar('tarifas', [$this->propiedad->id], CarbonImmutable::parse('2027-03-01'), CarbonImmutable::parse('2027-03-31'));

        expect($informe['filas'][0][0])->toBe('Cuatro personas')
            ->and($informe['filas'][0][1])->toBe('1')
            ->and($informe['filas'][0][2])->toBe('3')
            ->and($informe['filas'][1][0])->toBe('Doble');
    });

    it('unas fechas malas se rechazan igual con una tarifa elegida', function (): void {
        expect(fn () => app(GestorReservas::class)->crear($this->propiedad, ['unidad_id' => $this->unidad->id, 'titular_id' => $this->huesped->id, 'llegada' => '2027-03-05', 'salida' => '2027-03-01', 'personas' => 2, 'tarifa_id' => $this->otra->id]))->toThrow(ReservaRechazada::class);
    });
});

describe('demostración', function (): void {
    it('trae dos tarifas que salen de la doble, y se quita limpia', function (): void {
        $this->artisan('alojaflow:demo')->assertSuccessful();
        $demo = Propiedad::query()->where('es_demo', true)->sole();

        $tarifas = Tarifa::query()->where('propiedad_id', $demo->id)->whereNotNull('tarifa_base_id')->get();
        expect($tarifas)->toHaveCount(2)
            ->and($tarifas->every(fn (Tarifa $t): bool => $t->otrasUnidades()->exists()))->toBeTrue()
            ->and($tarifas->first()->politicaPropia()?->no_reembolsable)->toBeTrue();

        $this->artisan('alojaflow:demo', ['--quitar' => true])->assertSuccessful();
        expect(Tarifa::query()->where('propiedad_id', $demo->id)->count())->toBe(0);
    });
});
