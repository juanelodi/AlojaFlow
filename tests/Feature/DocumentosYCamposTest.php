<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

use App\Comunicacion\VariablesMensaje;
use App\Documentos\CamposPropios;
use App\Documentos\DocumentosDePartida;
use App\Documentos\GeneradorPdf;
use App\Documentos\MotorDocumentos;
use App\Enums\AlcanceDocumento;
use App\Enums\EntidadCampo;
use App\Enums\EstadoReserva;
use App\Enums\MetodoPago;
use App\Enums\ModoPropiedad;
use App\Enums\TipoCampoPropio;
use App\Enums\TipoContacto;
use App\Enums\TipoReserva;
use App\Filament\Resources\CamposPropios\CampoPropioResource;
use App\Filament\Resources\CamposPropios\Pages\CreateCampoPropio;
use App\Filament\Resources\CamposPropios\Pages\ListCamposPropios;
use App\Filament\Resources\DocumentosPropios\DocumentoPropioResource;
use App\Filament\Resources\DocumentosPropios\Pages\CreateDocumentoPropio;
use App\Filament\Resources\DocumentosPropios\Pages\ListDocumentosPropios;
use App\Filament\Resources\Huespedes\Pages\CreateHuesped;
use App\Filament\Resources\Huespedes\Pages\EditHuesped;
use App\Filament\Resources\Huespedes\Pages\ViewHuesped;
use App\Filament\Resources\Reservas\Pages\CreateReserva;
use App\Filament\Resources\Reservas\Pages\EditReserva;
use App\Filament\Resources\Reservas\Pages\ViewReserva;
use App\Models\CampoPropio;
use App\Models\DocumentoPropio;
use App\Models\Huesped;
use App\Models\Propiedad;
use App\Models\RegistroAuditoria;
use App\Models\Reserva;
use App\Models\Tarifa;
use App\Models\Unidad;
use App\Models\User;
use App\Models\ValorCampoPropio;
use App\Modulos\Modulo;
use App\Modulos\Modulos;
use App\Privacidad\GestorPrivacidad;
use App\Reservas\FusionHuespedes;
use App\Reservas\GestorReservas;
use App\Seguridad\Acceso;
use App\Support\Ajustes;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesYPermisosSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RolesYPermisosSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Storage::fake('local');
    Ajustes::olvidar();
    Modulos::guardar(Modulo::cases());
    $this->propiedad = Propiedad::query()->create(['nombre' => 'Posada', 'codigo' => 'posada', 'modo' => ModoPropiedad::Nativa, 'zona_horaria' => 'America/Mexico_City', 'moneda' => 'MXN', 'idioma' => 'es', 'domicilio' => 'Calle 1', 'telefono' => '555', 'correo' => 'posada@example.com']);
    $this->propiedad->refresh();
    $this->tarifa = Tarifa::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Doble', 'precio_noche' => 100000, 'personas_incluidas' => 2]);
    $this->unidad = Unidad::query()->create(['propiedad_id' => $this->propiedad->id, 'tarifa_id' => $this->tarifa->id, 'codigo' => '101', 'nombre' => 'Casa 1', 'capacidad' => 4]);
    $this->huesped = Huesped::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Ana', 'apellido1' => 'Ejemplo', 'documento_numero' => 'ABC12345', 'nacionalidad_texto' => 'México', 'idioma' => 'es']);
    $this->huesped->contactos()->create(['tipo' => TipoContacto::Correo, 'valor' => 'ana@example.com', 'valor_normalizado' => 'ana@example.com', 'orden' => 0]);
    $this->otro = Huesped::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Beto', 'apellido1' => 'Ejemplo', 'documento_numero' => 'ZZZ99999']);
    $this->reserva = app(GestorReservas::class)->crear($this->propiedad, ['unidad_id' => $this->unidad->id, 'titular_id' => $this->huesped->id, 'llegada' => '2027-03-01', 'salida' => '2027-03-04', 'personas' => 2, 'estado' => EstadoReserva::Confirmada->value, 'total' => 300000, 'idioma' => 'es', 'comentario' => 'Llega tarde']);
    $this->reserva->huespedes()->attach($this->otro->id, ['orden' => 1, 'parentesco_texto' => 'Hermano']);
    $this->reserva->cargos()->create(['concepto_cargo_id' => null, 'nombre' => 'Toallas', 'cantidad' => 2, 'importe' => 5000]);
    app(GestorReservas::class)->registrarPago($this->reserva, 100000, MetodoPago::Efectivo, CarbonImmutable::parse('2027-02-01'), 'REF-1');
    $this->reserva->refresh();
    $this->documento = fn (string $cuerpo, array $mas = []): DocumentoPropio => DocumentoPropio::query()->create($mas + ['propiedad_id' => $this->propiedad->id, 'nombre' => 'Doc '.uniqid(), 'cuerpo' => ['es' => $cuerpo]]);
    $this->llenar = fn (string $cuerpo, bool $conIdentidad = true): string => app(MotorDocumentos::class)->deReserva(($this->documento)($cuerpo), $this->reserva->refresh(), $conIdentidad);
    $this->gerente = User::query()->create(['name' => 'Ger', 'email' => 'ger@example.com', 'password' => Hash::make('contrasena-de-prueba'), 'activo' => true, 'idioma' => 'es']);
    Acceso::asignarRoles($this->gerente, [['propiedad_id' => $this->propiedad->id, 'rol' => 'gerente']]);
    $this->recepcion = User::query()->create(['name' => 'Rec', 'email' => 'rec@example.com', 'password' => Hash::make('contrasena-de-prueba'), 'activo' => true, 'idioma' => 'es']);
    Acceso::asignarRoles($this->recepcion, [['propiedad_id' => $this->propiedad->id, 'rol' => 'recepcion']]);
    $this->actingAs($this->gerente);
    $this->placa = CampoPropio::query()->create(['propiedad_id' => $this->propiedad->id, 'entidad' => EntidadCampo::Reserva, 'nombre' => 'Placa del coche']);
    $this->vegetariano = CampoPropio::query()->create(['propiedad_id' => $this->propiedad->id, 'entidad' => EntidadCampo::Huesped, 'nombre' => 'Vegetariano', 'tipo' => TipoCampoPropio::SiNo]);
});

afterEach(fn () => Ajustes::olvidar());

describe('campos propios', function (): void {
    it('la clave nace del nombre, sin acentos, y no se repite', function (): void {
        $otra = CampoPropio::query()->create(['propiedad_id' => $this->propiedad->id, 'entidad' => EntidadCampo::Huesped, 'nombre' => 'Placa del coche']);
        $rara = CampoPropio::query()->create(['propiedad_id' => $this->propiedad->id, 'entidad' => EntidadCampo::Huesped, 'nombre' => 'Habitación ñoña!']);

        expect($this->placa->clave)->toBe('placa_del_coche')
            ->and($this->placa->variable())->toBe('{campo_placa_del_coche}')
            ->and($otra->clave)->toBe('placa_del_coche_2')
            ->and($rara->clave)->toBe('habitacion_nona');
    });

    it('la clave no cambia al renombrarlo', function (): void {
        $this->placa->update(['nombre' => 'Matrícula']);

        expect($this->placa->refresh()->clave)->toBe('placa_del_coche');
    });

    it('guarda, lee y borra lo vacío', function (): void {
        CamposPropios::guardar($this->reserva, [$this->placa->id => ' ABC-123 ']);
        CamposPropios::guardar($this->huesped, [$this->vegetariano->id => true]);

        expect(CamposPropios::lineas($this->reserva))->toBe(['Placa del coche: ABC-123'])
            ->and(CamposPropios::lineas($this->huesped))->toBe(['Vegetariano: '.__('documentos_propios.si')])
            ->and(CamposPropios::valoresParaFormulario($this->huesped))->toBe([$this->vegetariano->id => true]);

        CamposPropios::guardar($this->reserva, [$this->placa->id => '']);
        CamposPropios::guardar($this->huesped, [$this->vegetariano->id => false]);

        expect(ValorCampoPropio::query()->count())->toBe(0);
    });

    it('no guarda en campos de otra propiedad, y lo que no llega se queda', function (): void {
        $otra = Propiedad::query()->create(['nombre' => 'Otra', 'codigo' => 'otra', 'modo' => ModoPropiedad::Nativa, 'zona_horaria' => 'UTC', 'moneda' => 'MXN', 'idioma' => 'es']);
        $ajeno = CampoPropio::query()->create(['propiedad_id' => $otra->id, 'entidad' => EntidadCampo::Reserva, 'nombre' => 'Ajeno']);
        CamposPropios::guardar($this->reserva, [$this->placa->id => 'ABC']);

        CamposPropios::guardar($this->reserva, [$ajeno->id => 'x']);

        expect(ValorCampoPropio::query()->where('campo_propio_id', $ajeno->id)->exists())->toBeFalse()
            ->and(ValorCampoPropio::query()->where('campo_propio_id', $this->placa->id)->sole()->valor)->toBe('ABC');
    });

    it('salen como variables en los mensajes, también vacíos', function (): void {
        $vacias = VariablesMensaje::de($this->reserva, 'es');
        expect($vacias['campo_placa_del_coche'])->toBe('')
            ->and($vacias['campo_vegetariano'])->toBe('');

        CamposPropios::guardar($this->reserva, [$this->placa->id => 'ABC-123']);
        CamposPropios::guardar($this->huesped, [$this->vegetariano->id => true]);
        $llenas = VariablesMensaje::de($this->reserva, 'es');

        expect($llenas['campo_placa_del_coche'])->toBe('ABC-123')
            ->and($llenas['campo_vegetariano'])->toBe(__('documentos_propios.si'))
            ->and($llenas['huesped'])->toBe('Ana Ejemplo');
    });

    it('con el módulo apagado no se ven', function (): void {
        CamposPropios::guardar($this->reserva, [$this->placa->id => 'ABC-123']);
        Modulos::guardar(array_values(array_filter(Modulo::cases(), fn (Modulo $m): bool => $m !== Modulo::Documentos)));

        expect(CamposPropios::lineas($this->reserva))->toBe([])
            ->and(CamposPropios::hay($this->propiedad->id, EntidadCampo::Reserva))->toBeFalse()
            ->and(array_key_exists('campo_placa_del_coche', VariablesMensaje::de($this->reserva, 'es')))->toBeFalse()
            ->and(CampoPropioResource::canViewAny())->toBeFalse()
            ->and(DocumentoPropioResource::canViewAny())->toBeFalse();
    });

    it('salen en la exportación, se borran al anonimizar, también los de sus reservas', function (): void {
        CamposPropios::guardar($this->reserva, [$this->placa->id => 'ABC-123']);
        CamposPropios::guardar($this->huesped, [$this->vegetariano->id => true]);
        $privacidad = app(GestorPrivacidad::class);

        $archivo = $privacidad->exportar($this->huesped);
        expect(app(App\Archivos\GestorArchivos::class)->contenido($archivo))->toContain('Vegetariano');

        $this->huesped->reservasComoTitular()->update(['salida' => '2020-01-05', 'llegada' => '2020-01-01']);
        CamposPropios::borrarDe($this->huesped);

        expect(ValorCampoPropio::query()->count())->toBe(0);
    });

    it('pasan con el huésped al fusionar, sin pisar lo que ya tiene', function (): void {
        $tercero = CampoPropio::query()->create(['propiedad_id' => $this->propiedad->id, 'entidad' => EntidadCampo::Huesped, 'nombre' => 'Almohada']);
        CamposPropios::guardar($this->huesped, [$this->vegetariano->id => true]);
        CamposPropios::guardar($this->otro, [$this->vegetariano->id => true, $tercero->id => 'Dura']);

        CamposPropios::fusionar($this->huesped, $this->otro);

        expect(ValorCampoPropio::query()->where('huesped_id', $this->huesped->id)->count())->toBe(2)
            ->and(ValorCampoPropio::query()->where('huesped_id', $this->otro->id)->count())->toBe(0);
    });

    it('la fusión de verdad los conserva', function (): void {
        CamposPropios::guardar($this->otro, [$this->vegetariano->id => true]);

        app(FusionHuespedes::class)->fusionar($this->huesped, $this->otro);

        expect(ValorCampoPropio::query()->where('huesped_id', $this->huesped->id)->exists())->toBeTrue();
    });
});

describe('campos propios en el panel', function (): void {
    it('se crean desde su pantalla', function (): void {
        Livewire::actingAs($this->gerente)->test(CreateCampoPropio::class)
            ->fillForm(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Mascota', 'entidad' => EntidadCampo::Reserva->value, 'tipo' => TipoCampoPropio::SiNo->value])
            ->call('create')
            ->assertHasNoFormErrors();

        expect(CampoPropio::query()->where('nombre', 'Mascota')->sole()->clave)->toBe('mascota');

        Livewire::actingAs($this->gerente)->test(ListCamposPropios::class)->assertSee('{campo_mascota}');
    });

    it('se capturan al crear una reserva, y se leen en su ficha', function (): void {
        Livewire::actingAs($this->gerente)->test(CreateReserva::class)
            ->fillForm(['propiedad_id' => $this->propiedad->id, 'llegada' => '2027-05-01', 'salida' => '2027-05-03', 'personas' => 2, 'unidad_id' => $this->unidad->id, 'titular_id' => $this->huesped->id, 'total' => '100'])
            ->set('data.campos_propios.'.$this->placa->id, 'XYZ-9')
            ->call('create')
            ->assertHasNoFormErrors();

        $reserva = Reserva::query()->where('llegada', '2027-05-01')->sole();
        expect(CamposPropios::lineas($reserva))->toBe(['Placa del coche: XYZ-9']);

        Livewire::actingAs($this->gerente)->test(ViewReserva::class, ['record' => $reserva->id])->assertSee('XYZ-9');
    });

    it('se editan con la reserva', function (): void {
        CamposPropios::guardar($this->reserva, [$this->placa->id => 'ABC']);

        Livewire::actingAs($this->gerente)->test(EditReserva::class, ['record' => $this->reserva->id])
            ->assertSet('data.campos_propios.'.$this->placa->id, 'ABC')
            ->set('data.campos_propios.'.$this->placa->id, 'DEF')
            ->call('save')
            ->assertHasNoFormErrors();

        expect(CamposPropios::lineas($this->reserva))->toBe(['Placa del coche: DEF']);
    });

    it('un bloqueo no los pide', function (): void {
        Livewire::actingAs($this->gerente)->test(CreateReserva::class)
            ->fillForm(['propiedad_id' => $this->propiedad->id, 'tipo' => TipoReserva::Bloqueo->value])
            ->assertDontSee('Placa del coche');
    });

    it('se capturan en el huésped', function (): void {
        Livewire::actingAs($this->gerente)->test(CreateHuesped::class)
            ->fillForm(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Carla', 'apellido1' => 'Prueba'])
            ->set('data.campos_propios.'.$this->vegetariano->id, true)
            ->call('create')
            ->assertHasNoFormErrors();

        $carla = Huesped::query()->where('nombre', 'Carla')->sole();
        expect(CamposPropios::lineas($carla))->toHaveCount(1);

        Livewire::actingAs($this->gerente)->test(EditHuesped::class, ['record' => $carla->id])
            ->assertSet('data.campos_propios.'.$this->vegetariano->id, true)
            ->set('data.campos_propios.'.$this->vegetariano->id, false)
            ->call('save')
            ->assertHasNoFormErrors();

        expect(CamposPropios::lineas($carla))->toBe([]);
    });

    it('la ventana de la ficha guarda, para quien tiene permiso', function (): void {
        Livewire::actingAs($this->recepcion)->test(ViewReserva::class, ['record' => $this->reserva->id])
            ->callAction('camposPropios', ['campos_propios' => [$this->placa->id => 'VIA-1']])
            ->assertHasNoActionErrors();
        expect(CamposPropios::lineas($this->reserva))->toBe(['Placa del coche: VIA-1']);

        Livewire::actingAs($this->recepcion)->test(ViewHuesped::class, ['record' => $this->huesped->id])
            ->callAction('camposPropios', ['campos_propios' => [$this->vegetariano->id => true]])
            ->assertHasNoActionErrors();
        expect(CamposPropios::lineas($this->huesped))->toHaveCount(1);
    });

    it('sin campos, la ventana no sale', function (): void {
        CampoPropio::query()->delete();

        Livewire::actingAs($this->gerente)->test(ViewReserva::class, ['record' => $this->reserva->id])->assertActionHidden('camposPropios');
    });

    it('un recepcionista no administra los catálogos', function (): void {
        $this->actingAs($this->recepcion);

        expect(CampoPropioResource::canViewAny())->toBeFalse()
            ->and(DocumentoPropioResource::canViewAny())->toBeFalse();
    });
});

describe('el motor de documentos', function (): void {
    it('pone los datos de la reserva', function (): void {
        $html = ($this->llenar)('<p>{huesped} en {unidad}: {noches} noches, {total}. {comentario}. {tarifa}. {huesped_correo}. {propiedad}, {domicilio_propiedad}.</p>');

        expect($html)->toContain('Ana Ejemplo en Casa 1: 3 noches')
            ->and($html)->toContain('Llega tarde')
            ->and($html)->toContain('Doble')
            ->and($html)->toContain('ana@example.com')
            ->and($html)->toContain('Posada, Calle 1');
    });

    it('una fila con datos de huésped se repite por cada uno, el titular primero', function (): void {
        $html = ($this->llenar)('<table><tbody><tr><th>Nombre</th></tr><tr><td><p>{h_numero}. {h_nombre} {h_parentesco}</p></td></tr></tbody></table>');

        expect($html)->toContain('1. Ana Ejemplo')
            ->and($html)->toContain('2. Beto Ejemplo Hermano')
            ->and(substr_count($html, '<tr>'))->toBe(3)
            ->and(mb_strpos($html, 'Ana'))->toBeLessThan(mb_strpos($html, 'Beto'));
    });

    it('un párrafo y un punto de lista también se repiten', function (): void {
        $html = ($this->llenar)('<p>Hola {nombre}</p><p>Viene {h_nombre}</p><ul><li>{c_nombre}: {c_cantidad} por {c_importe}</li></ul><ol><li>{p_metodo} {p_referencia} {p_importe}</li></ol>');

        expect(substr_count($html, 'Viene '))->toBe(2)
            ->and(substr_count($html, 'Hola Ana'))->toBe(1)
            ->and($html)->toContain('Toallas: 2 por')
            ->and($html)->toContain('REF-1');
    });

    it('si la lista está vacía, la fila no sale', function (): void {
        $this->reserva->cargos()->delete();

        $html = ($this->llenar)('<p>Antes</p><p>{c_nombre}</p><p>Después</p>');

        expect($html)->toContain('Antes')->and($html)->toContain('Después')->and($html)->not->toContain('{c_nombre}');
    });

    it('los datos se escapan, y lo escrito no ejecuta nada', function (): void {
        $this->huesped->update(['nombre' => '<img src=x onerror=alert(1)>']);

        $html = ($this->llenar)('<p onclick="alert(2)">{nombre}</p><script>alert(3)</script><iframe src="http://x"></iframe>');

        expect($html)->not->toContain('<img')
            ->and($html)->toContain('&lt;img')
            ->and($html)->not->toContain('onclick')
            ->and($html)->not->toContain('<script')
            ->and($html)->not->toContain('<iframe');
    });

    it('sin permiso, el documento de identidad sale con los últimos cuatro', function (): void {
        $con = ($this->llenar)('<p>{huesped_documento}</p>');
        $sin = ($this->llenar)('<p>{huesped_documento}</p>', false);

        expect($con)->toContain('ABC12345')
            ->and($sin)->not->toContain('ABC12345')
            ->and($sin)->toContain('2345');
    });

    it('usa los campos propios, y los de cada huésped en su fila', function (): void {
        CamposPropios::guardar($this->reserva, [$this->placa->id => 'ABC-123']);
        CamposPropios::guardar($this->otro, [$this->vegetariano->id => true]);

        $html = ($this->llenar)('<p>{campo_placa_del_coche}</p><p>{h_nombre}: {h_campo_vegetariano}.</p>');

        expect($html)->toContain('ABC-123')
            ->and($html)->toContain('Ana Ejemplo: .')
            ->and($html)->toContain('Beto Ejemplo: '.__('documentos_propios.si').'.');
    });

    it('escribe en el idioma de la reserva, y si falta, en el de la propiedad', function (): void {
        $documento = ($this->documento)('<p>Español {llegada}</p>');
        $documento->update(['cuerpo' => ['es' => '<p>Español {llegada}</p>', 'en' => '<p>English {llegada}</p>']]);
        $motor = app(MotorDocumentos::class);

        expect($motor->deReserva($documento, $this->reserva, true))->toContain('Español');

        $this->reserva->update(['idioma' => 'en']);
        expect($motor->deReserva($documento, $this->reserva->refresh(), true))->toContain('English')->toContain('March');

        $this->reserva->update(['idioma' => 'fr']);
        expect($motor->deReserva($documento, $this->reserva->refresh(), true))->toContain('Español');
    });

    it('un idioma con solo un párrafo vacío cuenta como sin escribir', function (): void {
        $documento = ($this->documento)('<p>Español</p>');
        $documento->update(['cuerpo' => ['es' => '<p>Español</p>', 'en' => '<p></p>']]);
        $this->reserva->update(['idioma' => 'en']);

        expect(app(MotorDocumentos::class)->deReserva($documento, $this->reserva->refresh(), true))->toContain('Español');
    });

    it('la cantidad de un cargo sale sin ceros de más', function (): void {
        $this->reserva->cargos()->create(['concepto_cargo_id' => null, 'nombre' => 'Sillas', 'cantidad' => 10, 'importe' => 100]);

        expect(($this->llenar)('<p>{c_nombre}: {c_cantidad}</p>'))->toContain('Sillas: 10<')->toContain('Toallas: 2<');
    });

    it('deja el idioma como estaba', function (): void {
        app()->setLocale('de');
        ($this->llenar)('<p>{llegada}</p>');

        expect(app()->getLocale())->toBe('de');
    });

    it('una variable que no existe se queda como está, para que se note', function (): void {
        expect(($this->llenar)('<p>{no_existe}</p>'))->toContain('{no_existe}');
    });
});

describe('documento de varias reservas', function (): void {
    beforeEach(function (): void {
        $this->segunda = app(GestorReservas::class)->crear($this->propiedad, ['unidad_id' => $this->unidad->id, 'titular_id' => $this->otro->id, 'llegada' => '2027-03-10', 'salida' => '2027-03-12', 'personas' => 1, 'estado' => EstadoReserva::Confirmada->value, 'total' => 200000]);
        $this->cancelada = app(GestorReservas::class)->crear($this->propiedad, ['unidad_id' => $this->unidad->id, 'titular_id' => $this->otro->id, 'llegada' => '2027-03-20', 'salida' => '2027-03-22', 'personas' => 1, 'estado' => EstadoReserva::Confirmada->value, 'total' => 200000]);
        app(GestorReservas::class)->cancelar($this->cancelada);
        app(GestorReservas::class)->crear($this->propiedad, ['tipo' => TipoReserva::Bloqueo->value, 'unidad_id' => $this->unidad->id, 'llegada' => '2027-03-25', 'salida' => '2027-03-26']);
        $this->periodo = function (string $cuerpo, string $desde = '2027-03-01', string $hasta = '2027-03-31', string $criterio = 'llegadas', ?User $usuario = null): string {
            $motor = app(MotorDocumentos::class);
            $inicio = CarbonImmutable::parse($desde);
            $fin = CarbonImmutable::parse($hasta);

            return $motor->dePeriodo(($this->documento)($cuerpo, ['alcance' => AlcanceDocumento::Periodo]), $this->propiedad, $inicio, $fin, $motor->reservasDelPeriodo($this->propiedad, $inicio, $fin, $criterio, $usuario ?? $this->gerente), true);
        };
    });

    it('lo de una reserva se repite por cada una, y lo demás una vez', function (): void {
        $html = ($this->periodo)('<p>De {desde} a {hasta}: {cuantas} en {propiedad}.</p><table><tbody><tr><td><p>{numero} {huesped} {unidad}</p></td></tr></tbody></table>');

        expect($html)->toContain('2 en Posada')
            ->and($html)->toContain('Ana Ejemplo Casa 1')
            ->and($html)->toContain('Beto Ejemplo Casa 1')
            ->and(substr_count($html, '<tr>'))->toBe(2);
    });

    it('sin canceladas ni bloqueos', function (): void {
        $motor = app(MotorDocumentos::class);
        $reservas = $motor->reservasDelPeriodo($this->propiedad, CarbonImmutable::parse('2027-03-01'), CarbonImmutable::parse('2027-03-31'), 'llegadas', $this->gerente);

        expect($reservas->pluck('id')->all())->toBe([$this->reserva->id, $this->segunda->id]);
    });

    it('elige por llegadas, salidas o quienes se hospedan', function (): void {
        $motor = app(MotorDocumentos::class);
        $buscar = fn (string $desde, string $hasta, string $criterio): array => $motor->reservasDelPeriodo($this->propiedad, CarbonImmutable::parse($desde), CarbonImmutable::parse($hasta), $criterio, $this->gerente)->pluck('id')->all();

        expect($buscar('2027-03-02', '2027-03-03', 'llegadas'))->toBe([])
            ->and($buscar('2027-03-02', '2027-03-03', 'en_casa'))->toBe([$this->reserva->id])
            // El día de salida ya no se hospeda.
            ->and($buscar('2027-03-04', '2027-03-04', 'en_casa'))->toBe([])
            ->and($buscar('2027-03-04', '2027-03-04', 'salidas'))->toBe([$this->reserva->id]);
    });

    it('dentro de cada reserva, también se repite por huésped', function (): void {
        $html = ($this->periodo)('<table><tbody><tr><td><p>{numero}: {h_nombre}</p></td></tr></tbody></table>', '2027-03-01', '2027-03-05');

        expect(substr_count($html, '<tr>'))->toBe(2)
            ->and($html)->toContain('Ana Ejemplo')
            ->and($html)->toContain('Beto Ejemplo');
    });

    it('respeta a quien solo ve lo suyo, y a los omitidos', function (): void {
        App\Models\AccesoUsuario::query()->updateOrCreate(['user_id' => $this->recepcion->id, 'propiedad_id' => $this->propiedad->id], ['solo_propias' => true]);
        $motor = app(MotorDocumentos::class);

        expect($motor->reservasDelPeriodo($this->propiedad->refresh(), CarbonImmutable::parse('2027-03-01'), CarbonImmutable::parse('2027-03-31'), 'llegadas', $this->recepcion)->count())->toBe(0);
    });
});

describe('documentos en el panel', function (): void {
    it('se crean desde su pantalla', function (): void {
        Livewire::actingAs($this->gerente)->test(CreateDocumentoPropio::class)
            ->fillForm(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Carta', 'alcance' => AlcanceDocumento::Reserva->value, 'cuerpo' => ['es' => '<p>Hola {huesped}</p>']])
            ->call('create')
            ->assertHasNoFormErrors();

        expect(DocumentoPropio::query()->where('nombre', 'Carta')->sole()->texto('es'))->toContain('{huesped}');
    });

    it('pide el texto en el idioma de la propiedad', function (): void {
        Livewire::actingAs($this->gerente)->test(CreateDocumentoPropio::class)
            ->fillForm(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Carta', 'alcance' => AlcanceDocumento::Reserva->value])
            ->call('create')
            ->assertHasFormErrors(['cuerpo.es']);
    });

    it('crea los de partida en todos los idiomas, y no duplica', function (): void {
        Livewire::actingAs($this->gerente)->test(ListDocumentosPropios::class)
            ->callAction('dePartida', ['propiedad' => $this->propiedad->id])
            ->assertHasNoActionErrors();

        $documentos = DocumentoPropio::query()->where('propiedad_id', $this->propiedad->id)->get();
        expect($documentos)->toHaveCount(3)
            ->and(array_keys($documentos->first()->cuerpo))->toEqualCanonicalizing((array) config('alojaflow.idiomas'))
            ->and($documentos->where('alcance', AlcanceDocumento::Periodo)->count())->toBe(1)
            ->and(DocumentosDePartida::sembrar($this->propiedad))->toBe(0);
    });

    it('los de partida salen bien llenos', function (): void {
        DocumentosDePartida::sembrar($this->propiedad);
        $motor = app(MotorDocumentos::class);

        foreach (DocumentoPropio::query()->where('alcance', AlcanceDocumento::Reserva->value)->get() as $documento) {
            $html = $motor->deReserva($documento, $this->reserva->refresh(), true);
            expect($html)->not->toMatch('/\{[a-z_]+\}/');
        }
        $inicio = CarbonImmutable::parse('2027-03-01');
        $fin = CarbonImmutable::parse('2027-03-31');
        $lista = DocumentoPropio::query()->where('alcance', AlcanceDocumento::Periodo->value)->sole();
        $html = $motor->dePeriodo($lista, $this->propiedad, $inicio, $fin, $motor->reservasDelPeriodo($this->propiedad, $inicio, $fin, 'llegadas', $this->gerente), true);
        expect($html)->not->toMatch('/\{[a-z_]+\}/')->and($html)->toContain('Ana Ejemplo');
    });

    it('baja el PDF desde la reserva, y queda anotado', function (): void {
        $documento = ($this->documento)('<p>Hola {huesped}</p>', ['nombre' => 'Carta de bienvenida']);

        Livewire::actingAs($this->recepcion)->test(ViewReserva::class, ['record' => $this->reserva->id])
            ->callAction('documentoPropio', ['documento' => $documento->id])
            ->assertHasNoActionErrors()
            ->assertFileDownloaded('carta-de-bienvenida.pdf');

        expect(RegistroAuditoria::query()->where('accion', 'documento_propio.generar')->exists())->toBeTrue();
    });

    it('en la reserva no ofrece los de varias reservas, ni los inactivos', function (): void {
        ($this->documento)('<p>{huesped}</p>', ['nombre' => 'De varias', 'alcance' => AlcanceDocumento::Periodo]);
        ($this->documento)('<p>{huesped}</p>', ['nombre' => 'Inactivo', 'activo' => false]);

        Livewire::actingAs($this->gerente)->test(ViewReserva::class, ['record' => $this->reserva->id])->assertActionHidden('documentoPropio');
    });

    it('genera el de varias reservas desde la lista', function (): void {
        $documento = ($this->documento)('<table><tbody><tr><td><p>{huesped}</p></td></tr></tbody></table>', ['alcance' => AlcanceDocumento::Periodo, 'nombre' => 'Llegadas']);

        Livewire::actingAs($this->gerente)->test(ListDocumentosPropios::class)
            ->assertTableActionHidden('vistaPrevia', $documento)
            ->callTableAction('generar', $documento, ['desde' => '2027-03-01', 'hasta' => '2027-03-31', 'criterio' => 'llegadas'])
            ->assertHasNoTableActionErrors()
            ->assertFileDownloaded('llegadas.pdf');
    });

    it('la vista previa usa la última reserva, y sin reservas lo dice', function (): void {
        $documento = ($this->documento)('<p>{huesped}</p>', ['nombre' => 'Carta']);

        Livewire::actingAs($this->gerente)->test(ListDocumentosPropios::class)
            ->assertTableActionHidden('generar', $documento)
            ->callTableAction('vistaPrevia', $documento)
            ->assertFileDownloaded('carta.pdf');

        Reserva::query()->delete();
        Livewire::actingAs($this->gerente)->test(ListDocumentosPropios::class)
            ->callTableAction('vistaPrevia', $documento)
            ->assertNotified(__('documentos_propios.sin_reservas_previa'));
    });

    it('el PDF se arma con lo que escribió el alojamiento', function (): void {
        $pdf = app(GeneradorPdf::class)->documentoPropio('<p>Hola</p>', 'Carta', $this->propiedad, $this->reserva, 'es');

        expect(substr($pdf, 0, 4))->toBe('%PDF');
    });

    it('funciona en una propiedad subordinada, sin escribir en sus reservas', function (): void {
        $subordinada = Propiedad::query()->create(['nombre' => 'Sub', 'codigo' => 'sub', 'zona_horaria' => 'UTC', 'moneda' => 'MXN', 'idioma' => 'es']);
        $this->actingAs(User::query()->create(['name' => 'Adm', 'email' => 'adm@example.com', 'password' => Hash::make('contrasena-de-prueba'), 'activo' => true, 'idioma' => 'es', 'es_administrador' => true]));

        expect(in_array($subordinada->id, DocumentoPropioResource::propiedadesGestionables(), true))->toBeTrue()
            ->and(in_array($subordinada->id, CampoPropioResource::propiedadesGestionables(), true))->toBeTrue();
    });
});

describe('demostración', function (): void {
    it('trae los documentos de partida y dos campos', function (): void {
        $this->artisan('alojaflow:demo')->assertSuccessful();
        $demo = Propiedad::query()->where('es_demo', true)->sole();

        expect(DocumentoPropio::query()->where('propiedad_id', $demo->id)->count())->toBe(3)
            ->and(CampoPropio::query()->where('propiedad_id', $demo->id)->count())->toBe(2);
    });
});
