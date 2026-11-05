<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

use App\Archivos\GestorArchivos;
use App\Comunicacion\Mensajero;
use App\Comunicacion\MensajeTexto;
use App\Enums\ClaseArchivo;
use App\Enums\EstadoRegistroLlegada;
use App\Enums\EstadoReserva;
use App\Enums\ModoPropiedad;
use App\Enums\OrigenFila;
use App\Enums\PaquetePais;
use App\Enums\TipoContacto;
use App\Enums\TipoDocumentoLegal;
use App\Enums\VerificacionAceptacion;
use App\Filament\Resources\DocumentosLegales\DocumentoLegalResource;
use App\Filament\Resources\DocumentosLegales\Pages\ListDocumentosLegales;
use App\Filament\Resources\Huespedes\Pages\ViewHuesped;
use App\Filament\Resources\Llegadas\Pages\ViewLlegada;
use App\Filament\Resources\Llegadas\RegistroLlegadaResource;
use App\Filament\Resources\Reservas\Pages\ViewReserva;
use App\Llegada\GestorDocumentosLegales;
use App\Llegada\GestorLlegadas;
use App\Models\Aceptacion;
use App\Models\Archivo;
use App\Models\CodigoOtp;
use App\Models\DocumentoLegal;
use App\Models\Envio;
use App\Models\Huesped;
use App\Models\Plantilla;
use App\Models\Propiedad;
use App\Models\RegistroLlegada;
use App\Models\Reserva;
use App\Models\SolicitudPrivacidad;
use App\Models\Unidad;
use App\Models\User;
use App\Paises\Paises;
use App\Privacidad\GestorPrivacidad;
use App\Reservas\GestorReservas;
use App\Seguridad\Acceso;
use App\Support\Ajustes;
use Database\Seeders\RolesYPermisosSeeder;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesYPermisosSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Ajustes::olvidar();
    $this->withHeader('Accept-Language', 'es');
    Storage::fake('local');
    Mail::fake();
    $this->propiedad = Propiedad::query()->create(['nombre' => 'Posada', 'codigo' => 'posada', 'modo' => ModoPropiedad::Nativa, 'paquete_pais' => PaquetePais::Mexico, 'zona_horaria' => 'UTC', 'moneda' => 'MXN', 'idioma' => 'es', 'correo' => 'hola@posada.example', 'domicilio' => 'Calle 1'])->refresh();
    $this->unidad = Unidad::query()->create(['propiedad_id' => $this->propiedad->id, 'codigo' => '101', 'nombre' => 'Cuarto 101', 'capacidad' => 4]);
    $this->huesped = Huesped::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Ana', 'apellido1' => 'Ejemplo']);
    $this->huesped->contactos()->create(['tipo' => TipoContacto::Correo, 'valor' => 'ana@example.com', 'valor_normalizado' => 'ana@example.com', 'orden' => 0]);
    $this->hoy = $this->propiedad->hoy();
    $this->reserva = app(GestorReservas::class)->crear($this->propiedad, [
        'unidad_id' => $this->unidad->id, 'titular_id' => $this->huesped->id, 'llegada' => $this->hoy->addDays(3)->format('Y-m-d'), 'salida' => $this->hoy->addDays(5)->format('Y-m-d'),
        'personas' => 2, 'estado' => EstadoReserva::Confirmada->value, 'total' => 100000, 'comentario' => 'Comentario privado',
    ]);
    $this->llegadas = app(GestorLlegadas::class);
    $this->token = $this->llegadas->enlaceDe($this->reserva)->token();
    $this->url = '/llegada/'.$this->token;
    $this->aviso = app(GestorDocumentosLegales::class);
    $this->formulario = fn (array $mas = []): array => array_replace_recursive([
        'personas' => [
            ['nombre' => 'Ana', 'apellido1' => 'Ejemplo', 'documento_tipo' => 'ine', 'documento_numero' => 'INE12345', 'correo' => 'ana@example.com', 'telefono' => '+52 55 5550 0101', 'domicilio_calle' => 'Falsa 123', 'domicilio_ciudad' => 'CDMX', 'domicilio_pais_texto' => 'México', 'procedencia' => 'Puebla', 'fecha_nacimiento' => '1990-05-01'],
            ['nombre' => 'Luis', 'apellido1' => 'Ejemplo'],
        ],
        'confirmo' => '1',
    ], $mas);
    $this->actuarComoAdmin = fn () => $this->actingAs(User::factory()->create(['es_administrador' => true, 'activo' => true, 'idioma' => 'es']));
});

afterEach(fn () => Ajustes::olvidar());

function documentoPublicado($test, TipoDocumentoLegal $tipo = TipoDocumentoLegal::AvisoPrivacidad): DocumentoLegal
{
    $documento = DocumentoLegal::query()->create(['propiedad_id' => $test->propiedad->id, 'tipo' => $tipo, 'idioma' => 'es', 'version' => 1, 'titulo' => 'Aviso', 'cuerpo' => 'Texto del aviso']);
    $test->aviso->publicar($documento);

    return $documento->refresh();
}

describe('formulario del huésped', function () {
    it('se abre solo con el enlace correcto y enseña lo que conoce', function () {
        $this->get($this->url)->assertOk()->assertDontSee($this->reserva->codigo_publico)->assertDontSee('ana@example.com')->assertSee(__('legal.documentos.ine'))->assertSee(__('legal.campos.procedencia'));
        $this->get('/llegada/'.str_repeat('a', 40))->assertNotFound();
        $this->get('/llegada/corto')->assertNotFound();

        // Un enlace de otro tipo no abre el formulario.
        $ical = App\Models\EnlacePublico::nuevo($this->propiedad->id, App\Enums\TipoEnlace::Ical, $this->unidad);
        $this->get('/llegada/'.$ical->token())->assertNotFound();

        // Reserva cancelada: el enlace deja de servir.
        app(GestorReservas::class)->cancelar($this->reserva->refresh());
        $this->get($this->url)->assertNotFound();
    });

    it('guarda a las personas, con el documento cifrado y la foto aparte', function () {
        $documento = documentoPublicado($this);
        $foto = UploadedFile::fake()->image('ine.jpg');

        $this->post($this->url, ($this->formulario)(['aceptar' => [$documento->id => '1'], 'hora_llegada' => '15:30', 'personas' => [['foto' => $foto]]]))
            ->assertRedirect(route('llegada.listo', ['token' => $this->token, 'idioma' => 'es']));

        $registro = RegistroLlegada::query()->with('personas')->firstOrFail();
        expect($registro->estado)->toBe(EstadoRegistroLlegada::Recibido)
            ->and($registro->hora_llegada_estimada)->toBe('15:30')
            ->and($registro->personas)->toHaveCount(2)
            ->and($registro->personas[0]->es_titular)->toBeTrue()
            ->and($registro->personas[0]->documento_numero)->toBe('INE12345')
            ->and($registro->personas[0]->datos_pais)->toBe(['procedencia' => 'Puebla'])
            ->and($registro->conservar_hasta->format('Y-m-d'))->toBe($this->hoy->addDays(5 + 365)->format('Y-m-d'))
            ->and((string) DB::table('registros_llegada_personas')->where('orden', 0)->value('documento_numero'))->not->toContain('INE12345');

        $archivo = Archivo::query()->where('clase', ClaseArchivo::DocumentoIdentidad->value)->firstOrFail();
        expect($archivo->cifrado)->toBeTrue()
            ->and($archivo->conservar_hasta->format('Y-m-d'))->toBe($this->hoy->addDays(5 + 30)->format('Y-m-d'))
            ->and(Storage::disk('local')->get($archivo->ruta))->not->toContain('JFIF');

        $aceptacion = Aceptacion::query()->firstOrFail();
        expect($aceptacion->verificacion)->toBe(VerificacionAceptacion::Casilla)
            ->and($aceptacion->huella_documento)->toBe(hash('sha256', 'Texto del aviso'))
            ->and($aceptacion->firmante_nombre)->toBe('Ana Ejemplo')
            ->and($aceptacion->copia_archivo_id)->not->toBeNull();
        expect(app(GestorArchivos::class)->contenido($aceptacion->copia))->toStartWith('%PDF');

        // Una vez recibido no se vuelve a abrir el formulario.
        $this->get($this->url)->assertRedirectContains('/listo');
        $this->post($this->url, ($this->formulario)(['aceptar' => [$documento->id => '1']]))->assertRedirectContains('/listo');
        expect(Aceptacion::query()->count())->toBe(1);
    });

    it('valida lo obligatorio, los documentos, la foto y el campo trampa', function () {
        $documento = documentoPublicado($this);

        $this->from($this->url)->post($this->url, ($this->formulario)())->assertSessionHasErrors('aceptar.'.$documento->id);
        $this->from($this->url)->post($this->url, ($this->formulario)(['aceptar' => [$documento->id => '1'], 'personas' => [['documento_numero' => '']]]))->assertSessionHasErrors('personas.0.documento_numero');
        $this->from($this->url)->post($this->url, ($this->formulario)(['aceptar' => [$documento->id => '1'], 'personas' => [['procedencia' => '']]]))->assertSessionHasErrors('personas.0.procedencia');
        $this->from($this->url)->post($this->url, ($this->formulario)(['aceptar' => [$documento->id => '1'], 'personas' => [['documento_tipo' => 'inventado']]]))->assertSessionHasErrors('personas.0.documento_tipo');
        $this->from($this->url)->post($this->url, ($this->formulario)(['aceptar' => [$documento->id => '1'], 'sitio_web' => 'x']))->assertSessionHasErrors('sitio_web');
        $this->from($this->url)->post($this->url, ($this->formulario)(['aceptar' => [$documento->id => '1'], 'personas' => [['foto' => UploadedFile::fake()->create('virus.php', 10, 'application/x-php')]]]))->assertSessionHasErrors('personas.0.foto');
        expect(RegistroLlegada::query()->count())->toBe(0);
    });

    it('verifica con un código por correo, que no se guarda en claro y caduca', function () {
        Ajustes::guardar('correo.host', 'smtp.example.com');
        Ajustes::guardar('correo.remitente_correo', 'hola@posada.example');
        $this->propiedad->update(['ajustes' => ['llegada' => ['otp' => true]]]);
        $documento = documentoPublicado($this);
        $codigo = null;

        $this->post($this->url, ($this->formulario)(['aceptar' => [$documento->id => '1']]))->assertRedirectContains('/verificar');
        Mail::assertSent(MensajeTexto::class, function (MensajeTexto $m) use (&$codigo): bool {
            preg_match('/\d{6}/', $m->cuerpo, $coincide);
            $codigo = $coincide[0];

            return $m->hasTo('ana@example.com');
        });
        $otp = CodigoOtp::query()->firstOrFail();
        expect($otp->codigo_hash)->not->toContain($codigo)->and($otp->destino_hmac)->not->toContain('ana');
        expect(RegistroLlegada::query()->first()->estado)->toBe(EstadoRegistroLlegada::Pendiente)
            ->and(Aceptacion::query()->count())->toBe(0);

        $this->get($this->url.'/verificar')->assertOk()->assertSee('ana@example.com');
        $this->from($this->url.'/verificar')->post($this->url.'/verificar', ['codigo' => $codigo === '000000' ? '000001' : '000000'])->assertSessionHasErrors('codigo');
        expect($otp->refresh()->intentos)->toBe(1);

        $this->post($this->url.'/verificar', ['codigo' => $codigo])->assertRedirectContains('/listo');
        expect(RegistroLlegada::query()->first()->estado)->toBe(EstadoRegistroLlegada::Recibido)
            ->and(Aceptacion::query()->first()->verificacion)->toBe(VerificacionAceptacion::OtpCorreo)
            ->and(Aceptacion::query()->first()->codigo_otp_id)->toBe($otp->id);
    });

    it('el código deja de servir tras varios intentos o al vencer', function () {
        Ajustes::guardar('correo.host', 'smtp.example.com');
        $this->propiedad->update(['ajustes' => ['llegada' => ['otp' => true]]]);
        $documento = documentoPublicado($this);
        $this->post($this->url, ($this->formulario)(['aceptar' => [$documento->id => '1']]));
        $registro = RegistroLlegada::query()->firstOrFail();

        expect($this->llegadas->verificarCodigo($registro, '0'))->toBeNull();
        CodigoOtp::query()->update(['intentos' => 5]);
        $codigoBueno = '123456';
        CodigoOtp::query()->update(['codigo_hash' => Hash::make($codigoBueno)]);
        expect($this->llegadas->verificarCodigo($registro, $codigoBueno))->toBeNull();

        CodigoOtp::query()->update(['intentos' => 0, 'expira_en' => now()->subMinute()]);
        expect($this->llegadas->verificarCodigo($registro, $codigoBueno))->toBeNull();
    });

    it('sin correo configurado se queda en la casilla', function () {
        $this->propiedad->update(['ajustes' => ['llegada' => ['otp' => true]]]);
        $documento = documentoPublicado($this);

        $this->post($this->url, ($this->formulario)(['aceptar' => [$documento->id => '1']]))->assertRedirectContains('/listo');

        expect(Aceptacion::query()->first()->verificacion)->toBe(VerificacionAceptacion::Casilla);
    });
});

describe('panel de llegadas', function () {
    beforeEach(function () {
        $this->post($this->url, ($this->formulario)());
        $this->registro = RegistroLlegada::query()->firstOrFail();
    });

    it('se revisa, se pasa a las fichas y da la tarjeta en PDF', function () {
        ($this->actuarComoAdmin)();

        expect(RegistroLlegadaResource::getNavigationBadge())->toBe('1');
        Livewire::test(ViewLlegada::class, ['record' => $this->registro->getRouteKey()])
            ->assertSee('INE12345')->assertSee('Puebla')
            ->callAction('revisar')
            ->callAction('aFichas')
            ->callAction('pdf')->assertFileDownloaded();

        expect($this->registro->refresh()->estado)->toBe(EstadoRegistroLlegada::Revisado)
            ->and(RegistroLlegadaResource::getNavigationBadge())->toBeNull();

        $titular = $this->huesped->refresh();
        expect($titular->documento_numero)->toBe('INE12345')
            ->and($titular->domicilio_ciudad)->toBe('CDMX')
            ->and($titular->contactos()->where('tipo', TipoContacto::Telefono->value)->first()->valor)->toBe('+52 55 5550 0101')
            ->and($this->reserva->huespedes()->count())->toBe(2)
            ->and($this->reserva->huespedes()->where('huespedes.nombre', 'Luis')->exists())->toBeTrue();
    });

    it('el rol de limpieza no lo ve, ni otra propiedad', function () {
        $limpieza = User::factory()->create(['activo' => true, 'idioma' => 'es']);
        Acceso::asignarRoles($limpieza, [['propiedad_id' => $this->propiedad->id, 'rol' => 'limpieza']]);
        $this->actingAs($limpieza);
        expect(RegistroLlegadaResource::canViewAny())->toBeFalse();

        $otra = Propiedad::query()->create(['nombre' => 'Otra', 'codigo' => 'otra', 'modo' => ModoPropiedad::Nativa, 'zona_horaria' => 'UTC', 'moneda' => 'MXN']);
        $recepcion = User::factory()->create(['activo' => true, 'idioma' => 'es']);
        Acceso::asignarRoles($recepcion, [['propiedad_id' => $otra->id, 'rol' => 'recepcion']]);
        $this->actingAs($recepcion);
        expect(RegistroLlegadaResource::canViewAny())->toBeTrue();
        Livewire::test(ViewLlegada::class, ['record' => $this->registro->getRouteKey()])->assertNotFound();
    });

    it('en una subordinada no toca las fichas', function () {
        $this->propiedad->forceFill(['modo' => ModoPropiedad::Subordinada])->save();

        expect($this->llegadas->pasarAFichas($this->registro->refresh()))->toBe(0);
        expect($this->huesped->refresh()->documento_numero)->toBeNull();
    });
});

it('la reserva da el enlace, y las plantillas lo pueden usar', function () {
    ($this->actuarComoAdmin)();
    Livewire::test(ViewReserva::class, ['record' => $this->reserva->getRouteKey()])->assertActionExists('enlaceLlegada');
    expect($this->llegadas->urlDe($this->reserva))->toContain($this->token);

    $plantilla = Plantilla::query()->create(['propiedad_id' => $this->propiedad->id, 'codigo' => 'x', 'nombre' => 'X', 'asunto' => ['es' => 'A'], 'cuerpo' => ['es' => 'Regístrate: {enlace_llegada}']]);
    expect(Mensajero::textoDe($plantilla, $this->reserva, 'es')['cuerpo'])->toContain('/llegada/'.$this->token);
    expect(App\Models\EnlacePublico::query()->count())->toBe(1);
});

describe('documentos legales', function () {
    it('publicar fija la huella, retira el anterior y ya no se edita', function () {
        ($this->actuarComoAdmin)();
        $uno = documentoPublicado($this);
        expect($uno->huella)->toBe(hash('sha256', 'Texto del aviso'))
            ->and(DocumentoLegalResource::canEdit($uno))->toBeFalse();

        $dos = $this->aviso->nuevaVersion($uno);
        expect($dos->version)->toBe(2)->and(DocumentoLegalResource::canEdit($dos))->toBeTrue();
        $dos->update(['cuerpo' => 'Texto nuevo']);
        $this->aviso->publicar($dos);

        expect($uno->refresh()->vigente())->toBeFalse()
            ->and($this->llegadas->documentosPara($this->propiedad, 'es')->pluck('id')->all())->toBe([$dos->id]);
    });

    it('borrar la propiedad se lleva sus aceptaciones y sus archivos del disco', function () {
        $documento = documentoPublicado($this);
        $this->post($this->url, ($this->formulario)(['aceptar' => [$documento->id => '1'], 'personas' => [['foto' => UploadedFile::fake()->image('ine.jpg')]]]));
        $ruta = Archivo::query()->where('clase', ClaseArchivo::DocumentoIdentidad->value)->firstOrFail()->ruta;
        Storage::disk('local')->assertExists($ruta);

        app(App\Propiedades\BorradorPropiedad::class)->borrar($this->propiedad);

        expect(Propiedad::query()->whereKey($this->propiedad->id)->exists())->toBeFalse()
            ->and(Aceptacion::query()->count())->toBe(0);
        Storage::disk('local')->assertMissing($ruta);
    });

    it('un documento aceptado no se puede borrar', function () {
        $documento = documentoPublicado($this);
        $this->post($this->url, ($this->formulario)(['aceptar' => [$documento->id => '1']]));

        expect(fn () => $documento->delete())->toThrow(Illuminate\Database\QueryException::class);
    });

    it('cae al idioma de la propiedad si no está en el del huésped', function () {
        $documento = documentoPublicado($this);

        expect($this->llegadas->documentosPara($this->propiedad, 'fr')->pluck('id')->all())->toBe([$documento->id]);
    });

    it('crea los textos de partida según el país, una sola vez', function () {
        ($this->actuarComoAdmin)();

        Livewire::test(ListDocumentosLegales::class)->callAction('crearBase', ['propiedad' => $this->propiedad->id])->assertHasNoActionErrors();
        expect(DocumentoLegal::query()->where('idioma', 'es')->count())->toBe(5)
            ->and(DocumentoLegal::query()->where('tipo', TipoDocumentoLegal::AvisoPrivacidad->value)->where('idioma', 'es')->first()->cuerpo)->toContain('Posada')->toContain('ARCO')->toContain('hola@posada.example')
            ->and(DocumentoLegal::query()->whereNotNull('publicado_en')->count())->toBe(0);
        expect($this->aviso->crearBase($this->propiedad))->toBe(0);
    });

    it('los paquetes de país dicen lo suyo', function () {
        expect(array_keys(Paises::de($this->propiedad)->tiposDocumento()))->toContain('ine');
        foreach ([PaquetePais::Espana, PaquetePais::EstadosUnidos, PaquetePais::Generico] as $paquete) {
            $this->propiedad->paquete_pais = $paquete;
            $pais = Paises::de($this->propiedad);
            expect($pais->tiposDocumento())->not->toBeEmpty()->and($pais->textosBase('es'))->toHaveCount(5)->and($pais->legalRevisado())->toBeFalse();
        }
        $this->propiedad->paquete_pais = PaquetePais::Espana;
        expect(Paises::de($this->propiedad)->documentoDeTodos())->toBeTrue();
    });
});

describe('privacidad', function () {
    beforeEach(function () {
        $this->documento = documentoPublicado($this);
        $this->post($this->url, ($this->formulario)(['aceptar' => [$this->documento->id => '1'], 'personas' => [['foto' => UploadedFile::fake()->image('ine.jpg')]]]));
        Envio::query()->create(['propiedad_id' => $this->propiedad->id, 'reserva_id' => $this->reserva->id, 'huesped_id' => $this->huesped->id, 'canal' => 'correo', 'destino' => 'ana@example.com', 'asunto' => 'Hola Ana', 'cuerpo' => 'Texto', 'estado' => 'enviado']);
    });

    it('exporta todo en un archivo cifrado que se borra solo', function () {
        ($this->actuarComoAdmin)();

        Livewire::test(ViewHuesped::class, ['record' => $this->huesped->getRouteKey()])->callAction('exportarDatos')->assertHasNoActionErrors();

        $solicitud = SolicitudPrivacidad::query()->firstOrFail();
        $archivo = $solicitud->archivo;
        $datos = json_decode(app(GestorArchivos::class)->contenido($archivo), true);
        expect($datos['ficha']['nombre'])->toBe('Ana')
            ->and($datos['contactos'][0]['valor'])->toBe('ana@example.com')
            ->and($datos['reservas'][0]['numero'])->toBe($this->reserva->numero)
            ->and($datos['registros_llegada'][0]['personas'][0]['documento_numero'])->toBe('INE12345')
            ->and($datos['aceptaciones'][0]['huella'])->toBe($this->documento->huella)
            ->and($datos['mensajes'][0]['destino'])->toBe('ana@example.com')
            ->and(Storage::disk('local')->get($archivo->ruta))->not->toContain('ana@example.com');

        $this->get(route('archivos.ver', $archivo))->assertOk()->assertHeader('Content-Disposition', 'attachment; filename="archivo-'.$archivo->id.'"');

        $this->travel(8)->days();
        $this->artisan('alojaflow:limpiar')->assertSuccessful();
        expect(Archivo::query()->whereKey($archivo->id)->exists())->toBeFalse();
    });

    it('anonimiza lo personal, borra los archivos y conserva los importes', function () {
        ($this->actuarComoAdmin)();
        $copias = Archivo::query()->where('clase', ClaseArchivo::DocumentoIdentidad->value)->pluck('ruta');

        Livewire::test(ViewHuesped::class, ['record' => $this->huesped->getRouteKey()])
            ->callAction('anonimizar', ['confirmacion' => 'otra cosa'])->assertHasActionErrors(['confirmacion']);
        expect($this->huesped->refresh()->nombre)->toBe('Ana');

        Livewire::test(ViewHuesped::class, ['record' => $this->huesped->getRouteKey()])
            ->callAction('anonimizar', ['confirmacion' => __('llegada.privacidad.palabra')])->assertHasNoActionErrors();

        $huesped = $this->huesped->refresh();
        expect($huesped->nombre)->toBe(__('llegada.anonimizado', ['id' => $huesped->id]))
            ->and($huesped->apellido1)->toBeNull()
            ->and($huesped->contactos()->count())->toBe(0)
            ->and(RegistroLlegada::query()->with('personas')->first()->personas[0]->documento_numero)->toBeNull()
            ->and(RegistroLlegada::query()->with('personas')->first()->personas[0]->correo)->toBeNull()
            ->and(Aceptacion::query()->first()->ip)->toBeNull()
            ->and(Envio::query()->first()->destino)->toBeNull()->and(Envio::query()->first()->cuerpo)->toBeNull();
        foreach ($copias as $ruta) {
            Storage::disk('local')->assertMissing($ruta);
        }
        $reserva = $this->reserva->refresh();
        expect($reserva->total)->toBe(100000)->and($reserva->comentario)->toBeNull();
    });

    it('en una ficha de la principal solo borra lo propio y lo dice', function () {
        DB::table('huespedes')->where('id', $this->huesped->id)->update(['sincronizada' => true, 'origen' => OrigenFila::HotelDruid->value]);

        expect(app(GestorPrivacidad::class)->anonimizar($this->huesped->refresh()))->toBeFalse();
        expect($this->huesped->refresh()->nombre)->toBe('Ana')
            ->and($this->huesped->contactos()->count())->toBe(1)
            ->and(RegistroLlegada::query()->with('personas')->first()->personas[0]->documento_numero)->toBeNull();
    });

    it('los plazos borran fotos y registros vencidos, y anonimizan las aceptaciones viejas', function () {
        $privacidad = app(GestorPrivacidad::class);
        expect($privacidad->aplicarPlazos()['archivos'])->toBe(0);

        $this->travel(5 + 31)->days();
        $resultado = $privacidad->aplicarPlazos();
        expect($resultado['archivos'])->toBe(1)
            ->and(RegistroLlegada::query()->count())->toBe(1);

        $this->travel(366)->days();
        expect($privacidad->aplicarPlazos()['registros'])->toBe(1)
            ->and(RegistroLlegada::query()->count())->toBe(0)
            ->and(Aceptacion::query()->first()->firmante_nombre)->toBe('Ana Ejemplo');

        $this->travel(5)->years();
        expect($privacidad->aplicarPlazos()['aceptaciones'])->toBe(1);
        $aceptacion = Aceptacion::query()->first();
        expect($aceptacion->ip)->toBeNull()->and($aceptacion->firmante_nombre)->not->toContain('Ana')
            ->and($aceptacion->huella_documento)->toBe($this->documento->huella)
            ->and(Archivo::query()->where('clase', ClaseArchivo::CopiaAceptacion->value)->count())->toBe(0);
    });
});
