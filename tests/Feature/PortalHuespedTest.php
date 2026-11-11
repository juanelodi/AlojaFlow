<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

use App\Comprobantes\ComprobanteRechazado;
use App\Comunicacion\Mensajero;
use App\Enums\EstadoEnvio;
use App\Enums\EstadoReserva;
use App\Enums\MetodoPago;
use App\Enums\ModoPropiedad;
use App\Enums\MomentoGuia;
use App\Enums\PaquetePais;
use App\Enums\TipoContacto;
use App\Enums\TipoSeccionGuia;
use App\Filament\Pages\CodigosGuia;
use App\Filament\Pages\PaginaPublicaAjustes;
use App\Filament\Resources\Fotos\Pages\ListFotos;
use App\Filament\Resources\Guia\Pages\CreateGuiaSeccion;
use App\Models\Archivo;
use App\Models\Aviso;
use App\Models\Envio;
use App\Models\Foto;
use App\Models\GuiaSeccion;
use App\Models\NotaReserva;
use App\Models\Plantilla;
use App\Models\Politica;
use App\Models\Propiedad;
use App\Models\Reserva;
use App\Models\Tarifa;
use App\Models\Unidad;
use App\Models\User;
use App\Modulos\Modulo;
use App\Modulos\Modulos;
use App\Motor\AjustesMotor;
use App\Publico\GestorFotos;
use App\Publico\GuiaDigital;
use App\Publico\MiReserva;
use App\Publico\PaginaPublica;
use App\Publico\Textos;
use App\Reservas\GestorReservas;
use App\Seguridad\Acceso;
use App\Support\Ajustes;
use Database\Seeders\RolesYPermisosSeeder;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

afterEach(fn () => Ajustes::olvidar());

function fotoDePrueba(int $ancho = 1200, int $alto = 800): string
{
    $imagen = imagecreatetruecolor($ancho, $alto);
    imagefill($imagen, 0, 0, (int) imagecolorallocate($imagen, 15, 118, 110));
    ob_start();
    imagepng($imagen);

    return (string) ob_get_clean();
}

beforeEach(function (): void {
    $this->seed(RolesYPermisosSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Ajustes::olvidar();
    Modulos::guardar(Modulo::cases());
    $this->propiedad = Propiedad::query()->create(['nombre' => 'Posada', 'codigo' => 'posada', 'modo' => ModoPropiedad::Nativa, 'zona_horaria' => 'America/Mexico_City', 'moneda' => 'MXN', 'pais' => 'MX', 'idioma' => 'es', 'paquete_pais' => PaquetePais::Mexico, 'telefono' => '+52 55 1234 5678', 'correo' => 'hola@example.com', 'ajustes' => ['motor' => ['activo' => true, 'anticipo_porcentaje' => 50]]])->refresh();
    $this->tarifa = Tarifa::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Doble', 'precio_noche' => 100000, 'personas_incluidas' => 2, 'activa' => true]);
    $this->unidad = Unidad::query()->create(['propiedad_id' => $this->propiedad->id, 'tarifa_id' => $this->tarifa->id, 'codigo' => '101', 'nombre' => 'Cuarto 101', 'capacidad' => 2, 'activa' => true]);
    $this->hoy = $this->propiedad->hoy();
    $this->gerente = User::query()->create(['name' => 'Ger', 'email' => 'ger@example.com', 'password' => Hash::make('contrasena-de-prueba'), 'activo' => true]);
    Acceso::asignarRoles($this->gerente, [['propiedad_id' => $this->propiedad->id, 'rol' => 'gerente']]);
    $this->recepcion = User::query()->create(['name' => 'Rec', 'email' => 'rec@example.com', 'password' => Hash::make('contrasena-de-prueba'), 'activo' => true]);
    Acceso::asignarRoles($this->recepcion, [['propiedad_id' => $this->propiedad->id, 'rol' => 'recepcion']]);
    $this->huesped = App\Models\Huesped::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Ana', 'apellido1' => 'Ejemplo', 'documento_numero' => 'SECRETO123']);
    $this->huesped->contactos()->create(['tipo' => TipoContacto::Correo, 'valor' => 'ana@example.com', 'valor_normalizado' => 'ana@example.com', 'orden' => 0]);
    $this->reservar = fn (int $dias = 5, array $mas = []): Reserva => app(GestorReservas::class)->crear($this->propiedad, $mas + [
        'unidad_id' => $this->unidad->id, 'titular_id' => $this->huesped->id, 'llegada' => $this->hoy->addDays($dias)->format('Y-m-d'), 'salida' => $this->hoy->addDays($dias + 2)->format('Y-m-d'),
        'personas' => 2, 'estado' => EstadoReserva::Confirmada->value, 'total' => 200000,
    ]);
    $this->portal = app(MiReserva::class);
    $this->seccion = fn (array $mas = []): GuiaSeccion => GuiaSeccion::query()->create($mas + ['propiedad_id' => $this->propiedad->id, 'tipo' => TipoSeccionGuia::Otro, 'momento' => MomentoGuia::Siempre, 'titulo' => ['es' => 'Título'], 'cuerpo' => ['es' => 'Texto de la sección'], 'activa' => true])->refresh();
});

describe('fotos', function (): void {
    it('las vuelve a dibujar en dos tamaños, y salen públicas con caché larga', function (): void {
        $foto = app(GestorFotos::class)->recibir(fotoDePrueba(), $this->propiedad, null, 'La fachada');

        expect($foto->orden)->toBe(1)
            ->and($foto->refresh()->archivo_id)->not->toBeNull()
            ->and($foto->miniatura_id)->not->toBeNull();

        $grande = $this->get($foto->url())->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        expect((string) $grande->headers->get('Cache-Control'))->toContain('max-age=31536000')->toContain('immutable');
        $medidasGrande = getimagesizefromstring((string) $grande->getContent());
        $medidasChica = getimagesizefromstring((string) $this->get($foto->url('chica'))->getContent());
        expect($medidasGrande[0])->toBe(1200)
            ->and($medidasChica[0])->toBe(640)
            ->and($medidasGrande['mime'])->toBe('image/jpeg');
    });

    it('rechaza lo que no es una imagen', function (): void {
        expect(fn () => app(GestorFotos::class)->recibir('<?php echo 1;', $this->propiedad, null))->toThrow(ComprobanteRechazado::class);
    });

    it('quitarla borra sus dos archivos', function (): void {
        $foto = app(GestorFotos::class)->recibir(fotoDePrueba(), $this->propiedad, null);
        expect(Archivo::query()->count())->toBe(2);

        app(GestorFotos::class)->eliminar($foto);

        expect(Archivo::query()->count())->toBe(0)
            ->and(Foto::query()->count())->toBe(0);
    });

    it('se suben desde su lista con un gerente, a la propiedad o a una tarifa', function (): void {
        Livewire::actingAs($this->gerente)->test(ListFotos::class)
            ->callAction('subir', ['propiedad_id' => $this->propiedad->id, 'tarifa_id' => $this->tarifa->id, 'archivos' => [UploadedFile::fake()->image('cuarto.jpg', 900, 600)]])
            ->assertHasNoActionErrors()
            ->assertNotified(__('portal.fotos.subidas', ['cuantas' => 1]));

        expect(Foto::query()->sole()->tarifa_id)->toBe($this->tarifa->id);
    });

    it('la foto de una propiedad apagada, o una que no existe, no se ve', function (): void {
        $foto = app(GestorFotos::class)->recibir(fotoDePrueba(), $this->propiedad, null);
        $this->get('/fotos/999999/grande')->assertNotFound();
        $this->get('/fotos/'.$foto->id.'/otra')->assertNotFound();

        $this->propiedad->update(['activa' => false]);

        $this->get($foto->url())->assertNotFound();
    });
});

describe('página pública', function (): void {
    it('sin nada escrito sigue siendo la de siempre', function (): void {
        $this->get('/reservar/posada')->assertOk()->assertDontSee('class="galeria"', false)->assertDontSee(__('portal.ubicacion'));
    });

    it('enseña fotos, textos, cómo llegar y las condiciones, con datos para buscadores', function (): void {
        $foto = app(GestorFotos::class)->recibir(fotoDePrueba(), $this->propiedad, null, 'La fachada');
        Politica::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'General', 'dias_cancelacion_gratuita' => 3, 'penalizacion_porcentaje' => 50, 'predeterminada' => true, 'texto' => 'Gratis hasta tres días antes']);
        $this->propiedad->update(['pagina_publica' => ['descripcion' => ['es' => 'Frente al mar'], 'ubicacion' => ['es' => 'A dos cuadras del muelle'], 'servicios' => ['es' => 'Wi-Fi y desayuno'], 'whatsapp' => '+52 55 1234 5678', 'mapa_url' => 'https://maps.example.com/x']]);

        $this->get('/reservar/posada')->assertOk()
            ->assertSee($foto->url('chica'), false)->assertSee('La fachada')
            ->assertSee('Frente al mar')->assertSee('A dos cuadras del muelle')->assertSee('Wi-Fi y desayuno')
            ->assertSee('https://maps.example.com/x', false)->assertSee('wa.me', false)
            ->assertSee('Gratis hasta tres días antes')
            ->assertSee('"@type":"LodgingBusiness"', false);
    });

    it('cada tarifa enseña sus fotos en los resultados', function (): void {
        $foto = app(GestorFotos::class)->recibir(fotoDePrueba(), $this->propiedad, $this->tarifa->id);

        $this->get('/reservar/posada?llegada='.$this->hoy->addDays(5)->format('Y-m-d').'&salida='.$this->hoy->addDays(7)->format('Y-m-d').'&personas=2')
            ->assertOk()->assertSee($foto->url('chica'), false)->assertSee(__('portal.foto_de', ['nombre' => 'Doble']));
    });

    it('un texto sin traducir sale en el idioma de la propiedad', function (): void {
        $this->propiedad->update(['pagina_publica' => ['descripcion' => ['es' => 'Frente al mar', 'en' => 'By the sea']]]);

        $this->get('/reservar/posada?idioma=en')->assertSee('By the sea');
        $this->get('/reservar/posada?idioma=fr')->assertSee('Frente al mar');
        expect(Textos::elegir(['de' => 'Am Meer'], 'fr', 'es'))->toBe('Am Meer')
            ->and(Textos::elegir(['es' => '  '], 'es', 'es'))->toBeNull();
    });

    it('un mapa que no es https no se enseña', function (): void {
        $this->propiedad->update(['pagina_publica' => ['mapa_url' => 'javascript:alert(1)']]);

        expect((new PaginaPublica($this->propiedad->refresh()))->mapaUrl())->toBeNull();
        $this->get('/reservar/posada')->assertDontSee('javascript:alert', false);
    });

    it('se escribe desde su pantalla con un gerente; recepción no entra', function (): void {
        Livewire::actingAs($this->gerente)->test(PaginaPublicaAjustes::class)
            ->set('datos.descripcion.es', 'Frente al mar')
            ->set('datos.whatsapp', '+52 55 1234 5678')
            ->set('datos.mapa_url', 'https://maps.example.com/x')
            ->set('datos.huesped_cancela', true)
            ->call('guardar')
            ->assertHasNoErrors();

        $pagina = new PaginaPublica($this->propiedad->refresh());
        expect($pagina->descripcion('es'))->toBe('Frente al mar')
            ->and($pagina->mapaUrl())->toBe('https://maps.example.com/x')
            ->and($pagina->huespedPuedeCancelar())->toBeTrue();

        Livewire::actingAs($this->gerente)->test(PaginaPublicaAjustes::class)->set('datos.mapa_url', 'http://inseguro.example.com')->call('guardar')->assertHasErrors('datos.mapa_url');
        Livewire::actingAs($this->recepcion)->test(PaginaPublicaAjustes::class)->assertForbidden();
    });
});

describe('Mi reserva', function (): void {
    it('enseña la reserva con un enlace personal, sin documentos', function (): void {
        $reserva = ($this->reservar)();
        $url = $this->portal->urlDe($reserva);

        $this->get($url)->assertOk()->assertSee('Ana Ejemplo')->assertSee($reserva->codigo_publico)->assertDontSee('SECRETO123');
        $this->get('/mi-reserva/'.str_repeat('a', 40))->assertNotFound();
    });

    it('es el mismo enlace aunque se mueva la reserva, y caduca una semana tras la salida', function (): void {
        $reserva = ($this->reservar)();
        $url = $this->portal->urlDe($reserva);

        app(GestorReservas::class)->mover($reserva->refresh(), $this->unidad->id, $this->hoy->addDays(30));
        expect($this->portal->urlDe($reserva->refresh()))->toBe($url);

        $this->travelTo($this->hoy->addDays(38)->endOfDay()->utc());
        $this->get($url)->assertOk();

        $this->travelTo($this->hoy->addDays(40)->utc());
        $this->get($url)->assertNotFound();
    });

    it('con el módulo apagado no existe', function (): void {
        $url = $this->portal->urlDe(($this->reservar)());
        Modulos::guardar([Modulo::Motor]);
        Ajustes::olvidar();

        $this->get($url)->assertNotFound();
    });

    it('un mensaje le llega al alojamiento como nota, aviso y correo; no cambia la reserva', function (): void {
        $reserva = ($this->reservar)();
        $url = $this->portal->urlDe($reserva);

        $this->post($url.'/pedir', ['mensaje' => 'Llegamos a las 11 de la noche'])->assertRedirectContains('aviso=pedido');

        expect(NotaReserva::query()->where('reserva_id', $reserva->id)->sole()->texto)->toContain('Llegamos a las 11 de la noche')
            ->and(Aviso::query()->where('tipo', 'huesped_pide')->exists())->toBeTrue();
        $envio = Envio::query()->where('destino', 'hola@example.com')->sole();
        expect($envio->cuerpo)->toContain('Llegamos a las 11 de la noche')->toContain('Ana Ejemplo')
            ->and(in_array($envio->estado, [EstadoEnvio::Manual, EstadoEnvio::Pendiente], true))->toBeTrue()
            ->and($reserva->refresh()->estado)->toBe(EstadoReserva::Confirmada);
        $this->get($url.'?aviso=pedido')->assertSee(__('portal.avisos.pedido'));
    });

    it('no acepta un mensaje vacío ni el del campo trampa', function (): void {
        $url = $this->portal->urlDe(($this->reservar)());

        $this->from($url)->post($url.'/pedir', ['mensaje' => ''])->assertSessionHasErrors('mensaje');
        $this->from($url)->post($url.'/pedir', ['mensaje' => 'Hola, soy un robot', 'sitio_web' => 'x'])->assertSessionHasErrors('sitio_web');
        expect(NotaReserva::query()->exists())->toBeFalse();
    });

    describe('cancelar', function (): void {
        beforeEach(function (): void {
            $this->permitir = fn () => $this->propiedad->update(['pagina_publica' => ['huesped_cancela' => true]]);
        });

        it('de entrada no se puede: decide el alojamiento', function (): void {
            $reserva = ($this->reservar)();
            $url = $this->portal->urlDe($reserva);

            $this->get($url)->assertSee(__('portal.cancelar_pedir'))->assertDontSee(__('portal.cancelar_confirmo'));
            $this->post($url.'/cancelar')->assertRedirectContains('aviso=no_cancelada');

            expect($reserva->refresh()->estado)->toBe(EstadoReserva::Confirmada);
        });

        it('si está permitido y no cuesta nada, se cancela y le avisa', function (): void {
            ($this->permitir)();
            $reserva = ($this->reservar)();
            $url = $this->portal->urlDe($reserva);

            $this->get($url)->assertSee(__('portal.cancelar_confirmo'));
            $this->post($url.'/cancelar')->assertRedirectContains('aviso=cancelada');

            expect($reserva->refresh()->estado)->toBe(EstadoReserva::Cancelada)
                ->and(NotaReserva::query()->where('reserva_id', $reserva->id)->exists())->toBeTrue();
            $this->get($url)->assertOk()->assertSee(__('motor.estado_cancelada'))->assertDontSee(__('portal.pedir'));
        });

        it('con un pago hecho no: hay dinero que devolver', function (): void {
            ($this->permitir)();
            $reserva = ($this->reservar)();
            app(GestorReservas::class)->registrarPago($reserva, 1000, MetodoPago::Efectivo, $this->hoy);

            expect($this->portal->puedeCancelarSolo($reserva->refresh()))->toBeFalse();
        });

        it('fuera de la cancelación gratuita no; dentro, sí', function (): void {
            ($this->permitir)();
            Politica::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'General', 'dias_cancelacion_gratuita' => 3, 'penalizacion_porcentaje' => 50, 'predeterminada' => true]);

            expect($this->portal->puedeCancelarSolo(($this->reservar)(2)))->toBeFalse()
                ->and($this->portal->puedeCancelarSolo(($this->reservar)(20)))->toBeTrue();
        });

        it('el día de llegada o ya dentro, no', function (): void {
            ($this->permitir)();
            $reserva = ($this->reservar)(0);

            expect($this->portal->puedeCancelarSolo($reserva))->toBeFalse();
        });
    });

    it('enseña cómo pagar si hay pasarela, y el formulario de llegada', function (): void {
        (new AjustesMotor($this->propiedad))->guardarLlave('stripe_secreta', 'sk_test_x');
        $reserva = ($this->reservar)(5, ['deposito' => 50000]);

        $this->get($this->portal->urlDe($reserva))->assertOk()
            ->assertSee(__('motor.pagar_con.stripe'))
            ->assertSee(__('portal.registro_boton'));
    });

    it('las páginas que mandan a una pasarela no la bloquean; las demás siguen protegidas', function (): void {
        $reserva = ($this->reservar)();
        $politica = fn (string $url): string => (string) $this->get($url)->headers->get('Content-Security-Policy');
        $enlace = app(App\Pagos\GestorEnlacesPago::class)->crear($reserva, 1000, 'Anticipo', 3, null);

        expect($politica($this->portal->urlDe($reserva)))->not->toContain('form-action')->toContain("frame-ancestors 'self'")
            ->and($politica((string) app(App\Pagos\GestorEnlacesPago::class)->url($enlace)))->not->toContain('form-action')
            ->and($politica('/reservar/posada'))->not->toContain('form-action')->toContain('frame-ancestors *')
            ->and($politica('/entrar'))->toContain("form-action 'self'");
    });

    it('le enseña las condiciones, no el resumen para el personal; ya dentro, no le pide datos de llegada', function (): void {
        Politica::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'General', 'dias_cancelacion_gratuita' => 3, 'penalizacion_porcentaje' => 50, 'predeterminada' => true, 'texto' => 'Gratis hasta tres días antes']);
        $reserva = ($this->reservar)(0);
        app(GestorReservas::class)->registrarPago($reserva, 1000, MetodoPago::Efectivo, $this->hoy);
        $url = $this->portal->urlDe($reserva);

        $this->get($url)->assertSee('Gratis hasta tres días antes')->assertDontSee('devolver')->assertSee(__('portal.registro_boton'));

        app(GestorReservas::class)->registrarEntrada($reserva->refresh());
        $this->get($url)->assertDontSee(__('portal.registro_boton'));
    });

    it('baja su confirmación en PDF', function (): void {
        $url = $this->portal->urlDe(($this->reservar)());

        $this->get($url.'/confirmacion.pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf');
    });

    it('las plantillas pueden llevar su enlace', function (): void {
        $reserva = ($this->reservar)();
        $plantilla = (new Plantilla(['propiedad_id' => $this->propiedad->id, 'nombre' => 'x', 'asunto' => ['es' => 'Tu reserva'], 'cuerpo' => ['es' => 'Mira: {enlace_mi_reserva}']]))->setRelation('propiedad', $this->propiedad);

        $texto = Mensajero::textoDe($plantilla, $reserva, 'es');

        expect($texto['cuerpo'])->toContain('/mi-reserva/');
    });
});

describe('guía digital', function (): void {
    it('el código QR de la unidad enseña lo general y lo de esa unidad', function (): void {
        ($this->seccion)(['cuerpo' => ['es' => 'La clave del Wi-Fi es 1234']]);
        ($this->seccion)(['unidad_id' => $this->unidad->id, 'cuerpo' => ['es' => 'El boiler está en el patio']]);
        $otra = Unidad::query()->create(['propiedad_id' => $this->propiedad->id, 'tarifa_id' => $this->tarifa->id, 'codigo' => '102', 'nombre' => 'Otra', 'capacidad' => 2, 'activa' => true]);
        ($this->seccion)(['unidad_id' => $otra->id, 'cuerpo' => ['es' => 'Eso es de otra unidad']]);

        $this->get(app(GuiaDigital::class)->urlDe($this->unidad))->assertOk()
            ->assertSee('La clave del Wi-Fi es 1234')->assertSee('El boiler está en el patio')->assertDontSee('Eso es de otra unidad');
    });

    it('lo delicado no sale en el código QR', function (): void {
        ($this->seccion)(['solo_con_reserva' => true, 'cuerpo' => ['es' => 'La puerta abre con 9876']]);
        ($this->seccion)(['momento' => MomentoGuia::Antes, 'cuerpo' => ['es' => 'Cómo llegar en coche']]);

        $this->get(app(GuiaDigital::class)->urlDe($this->unidad))->assertDontSee('La puerta abre con 9876')->assertDontSee('Cómo llegar en coche');
    });

    it('en Mi reserva lo delicado sale solo durante la estancia', function (): void {
        ($this->seccion)(['solo_con_reserva' => true, 'cuerpo' => ['es' => 'La puerta abre con 9876']]);
        $antes = ($this->reservar)(5);
        $ahora = ($this->reservar)(0);

        $this->get($this->portal->urlDe($antes))->assertDontSee('La puerta abre con 9876');
        $this->get($this->portal->urlDe($ahora))->assertSee('La puerta abre con 9876');
    });

    it('con «solo si no debe nada», espera a que pague', function (): void {
        ($this->seccion)(['solo_con_reserva' => true, 'requiere_pago' => true, 'cuerpo' => ['es' => 'La puerta abre con 9876']]);
        $reserva = ($this->reservar)(0);
        $url = $this->portal->urlDe($reserva);

        $this->get($url)->assertDontSee('La puerta abre con 9876');

        app(GestorReservas::class)->registrarPago($reserva, 200000, MetodoPago::Efectivo, $this->hoy);
        $this->get($url)->assertSee('La puerta abre con 9876');
    });

    it('cada parte sale cuando sirve', function (string $momento, int $dias, bool $seVe): void {
        ($this->seccion)(['momento' => $momento, 'cuerpo' => ['es' => 'Texto de esa parte']]);
        $reserva = ($this->reservar)($dias);

        $respuesta = $this->get($this->portal->urlDe($reserva));

        $seVe ? $respuesta->assertSee('Texto de esa parte') : $respuesta->assertDontSee('Texto de esa parte');
    })->with([
        'antes, faltando días' => ['antes', 5, true],
        'antes, el mismo día' => ['antes', 0, true],
        'durante, faltando días' => ['durante', 5, false],
        'durante, el día de llegada' => ['durante', 0, true],
        'después, antes de salir' => ['despues', 5, false],
    ]);

    it('después de salir se ve lo de después y ya no lo de antes', function (): void {
        ($this->seccion)(['momento' => MomentoGuia::Despues, 'cuerpo' => ['es' => 'Gracias por venir']]);
        ($this->seccion)(['momento' => MomentoGuia::Antes, 'cuerpo' => ['es' => 'Cómo llegar en coche']]);
        $reserva = ($this->reservar)(1);
        $url = $this->portal->urlDe($reserva);

        $this->travelTo($this->hoy->addDays(4)->utc());

        $this->get($url)->assertSee('Gracias por venir')->assertDontSee('Cómo llegar en coche');
    });

    it('una reserva cancelada ya no ve lo delicado ni lo de la estancia', function (): void {
        ($this->seccion)(['solo_con_reserva' => true, 'cuerpo' => ['es' => 'La puerta abre con 9876']]);
        ($this->seccion)(['momento' => MomentoGuia::Durante, 'cuerpo' => ['es' => 'Texto de la estancia']]);
        $reserva = ($this->reservar)(0);
        $url = $this->portal->urlDe($reserva);
        app(GestorReservas::class)->cancelar($reserva);

        $this->get($url)->assertDontSee('La puerta abre con 9876')->assertDontSee('Texto de la estancia');
    });

    it('sale en su idioma, o en el de la propiedad', function (): void {
        ($this->seccion)(['titulo' => ['es' => 'Red', 'en' => 'Network'], 'cuerpo' => ['es' => 'La clave es 1234', 'en' => 'The password is 1234']]);
        $url = app(GuiaDigital::class)->urlDe($this->unidad);

        $this->get($url.'?idioma=en')->assertSee('The password is 1234')->assertSee('Network');
        $this->get($url.'?idioma=de')->assertSee('La clave es 1234');
    });

    it('un gerente saca los códigos en SVG y puede cambiarlos; el anterior deja de valer', function (): void {
        $url = app(GuiaDigital::class)->urlDe($this->unidad);

        $pagina = Livewire::actingAs($this->gerente)->test(CodigosGuia::class)->assertOk()->assertSee('<svg', false)->assertSee('Cuarto 101');

        $pagina->call('cambiar', $this->unidad->id)->assertNotified(__('portal.guia_panel.codigo_cambiado'));

        $this->get($url)->assertNotFound();
        expect(app(GuiaDigital::class)->urlDe($this->unidad->refresh()))->not->toBe($url);
    });

    it('recepción no entra a los códigos, y no se cambia el de otra propiedad', function (): void {
        Livewire::actingAs($this->recepcion)->test(CodigosGuia::class)->assertForbidden();

        $otra = Propiedad::query()->create(['nombre' => 'Otra', 'codigo' => 'otra', 'modo' => ModoPropiedad::Nativa, 'zona_horaria' => 'UTC', 'moneda' => 'MXN', 'paquete_pais' => PaquetePais::Mexico]);
        $ajena = Unidad::query()->create(['propiedad_id' => $otra->id, 'codigo' => 'X', 'nombre' => 'X', 'capacidad' => 2, 'activa' => true]);

        Livewire::actingAs($this->gerente)->test(CodigosGuia::class)->call('cambiar', $ajena->id)->assertStatus(404);
    });

    it('se escribe desde su lista con un gerente, y exige el texto en el idioma de la propiedad', function (): void {
        $datos = ['propiedad_id' => $this->propiedad->id, 'tipo' => 'wifi', 'momento' => 'siempre', 'activa' => true, 'orden' => 0];

        Livewire::actingAs($this->gerente)->test(CreateGuiaSeccion::class)
            ->fillForm($datos + ['cuerpo' => ['en' => 'Only in English']])->call('create')->assertHasFormErrors(['cuerpo.es']);

        Livewire::actingAs($this->gerente)->test(CreateGuiaSeccion::class)
            ->fillForm($datos + ['titulo' => ['es' => 'Wi-Fi'], 'cuerpo' => ['es' => 'La clave es 1234']])->call('create')->assertHasNoFormErrors();

        expect(GuiaSeccion::query()->sole()->cuerpo['es'])->toBe('La clave es 1234');
    });
});
