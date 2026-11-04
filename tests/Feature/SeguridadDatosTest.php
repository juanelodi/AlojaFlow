<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

use App\Archivos\GestorArchivos;
use App\Comunicacion\AjustesCorreo;
use App\Comunicacion\Mensajero;
use App\Copias\GestorCopias;
use App\Enums\ClaseArchivo;
use App\Enums\EstadoEjecucion;
use App\Enums\EstadoRegistroLlegada;
use App\Enums\EstadoReserva;
use App\Enums\ModoPropiedad;
use App\Enums\OrigenEjecucion;
use App\Filament\AvatarLocal;
use App\Filament\Pages\IniciarSesion;
use App\Filament\Pages\PedirRecuperacion;
use App\Filament\Pages\Resumen;
use App\Filament\Resources\Propiedades\Pages\CreatePropiedad;
use App\Filament\Resources\Propiedades\Pages\EditPropiedad;
use App\Models\Aceptacion;
use App\Models\Aviso;
use App\Models\DocumentoLegal;
use App\Models\EjecucionSincronizacion;
use App\Models\Envio;
use App\Models\Huesped;
use App\Models\NotaReserva;
use App\Models\Principal;
use App\Models\Propiedad;
use App\Models\ReceptorFiscal;
use App\Models\RegistroAuditoria;
use App\Models\RegistroLlegada;
use App\Models\Unidad;
use App\Models\User;
use App\Models\Webhook;
use App\Paises\CatalogoPaises;
use App\Privacidad\GestorPrivacidad;
use App\Propiedades\ErrorIndependizar;
use App\Propiedades\Independizador;
use App\Reservas\GestorReservas;
use App\Seguridad\Acceso;
use App\Sincronizacion\HotelDruid\CargadorEspejo;
use App\Support\Ajustes;
use Database\Seeders\RolesYPermisosSeeder;
use Filament\Auth\Notifications\ResetPassword;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesYPermisosSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Ajustes::olvidar();
    Storage::fake('local');
    $this->propiedad = Propiedad::query()->create(['nombre' => 'Posada', 'codigo' => 'posada', 'modo' => ModoPropiedad::Nativa, 'zona_horaria' => 'America/Mexico_City', 'moneda' => 'MXN', 'idioma' => 'es']);
    $this->unidad = Unidad::query()->create(['propiedad_id' => $this->propiedad->id, 'codigo' => '101', 'nombre' => '101', 'capacidad' => 3]);
    $this->huesped = Huesped::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Ana', 'apellido1' => 'Ejemplo', 'documento_numero' => 'INE111']);
    $this->acompanante = Huesped::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Luis', 'apellido1' => 'Pareja', 'documento_numero' => 'INE222']);
    $hoy = $this->propiedad->hoy();
    $this->reserva = app(GestorReservas::class)->crear($this->propiedad, [
        'unidad_id' => $this->unidad->id, 'titular_id' => $this->huesped->id, 'llegada' => $hoy->addDays(3)->format('Y-m-d'), 'salida' => $hoy->addDays(5)->format('Y-m-d'),
        'personas' => 2, 'estado' => EstadoReserva::Confirmada->value, 'total' => 100000, 'cargos' => [],
    ]);
    $this->reserva->huespedes()->syncWithoutDetaching([$this->acompanante->id]);
    $this->registro = RegistroLlegada::query()->create(['reserva_id' => $this->reserva->id, 'propiedad_id' => $this->propiedad->id, 'estado' => EstadoRegistroLlegada::Pendiente, 'idioma' => 'es']);
    $this->titular = $this->registro->personas()->create(['orden' => 0, 'es_titular' => true, 'nombre' => 'Ana', 'apellido1' => 'Ejemplo', 'documento_numero' => 'INE111', 'correo' => 'ana@example.com']);
    $this->segunda = $this->registro->personas()->create(['orden' => 1, 'es_titular' => false, 'nombre' => 'Luis', 'apellido1' => 'Pareja', 'documento_numero' => 'INE222']);
});

afterEach(fn () => Ajustes::olvidar());

describe('privacidad', function () {
    it('la exportación de quien viajó acompañado no incluye a los demás', function () {
        $datos = fn (Huesped $h): array => json_decode(app(GestorArchivos::class)->contenido(app(GestorPrivacidad::class)->exportar($h)), true);

        $deAna = $datos($this->huesped)['registros_llegada'][0]['personas'];
        expect($deAna)->toHaveCount(1)->and($deAna[0]['documento_numero'])->toBe('INE111');

        $deLuis = $datos($this->acompanante)['registros_llegada'][0]['personas'];
        expect($deLuis)->toHaveCount(1)->and($deLuis[0]['documento_numero'])->toBe('INE222');
    });

    it('anonimizar borra también el PDF de la aceptación y lo que queda fuera de la ficha', function () {
        $documento = DocumentoLegal::query()->create(['propiedad_id' => $this->propiedad->id, 'tipo' => 'reglamento', 'idioma' => 'es', 'version' => 1, 'titulo' => 'Reglamento', 'cuerpo' => 'Texto', 'huella' => str_repeat('a', 64), 'publicado_en' => now('UTC')]);
        $copia = app(GestorArchivos::class)->guardar('pdf con nombre', 'aceptacion.pdf', 'application/pdf', ClaseArchivo::CopiaAceptacion, null, $this->propiedad->id, cifrar: true);
        $aceptacion = Aceptacion::query()->create(['reserva_id' => $this->reserva->id, 'propiedad_id' => $this->propiedad->id, 'documento_legal_id' => $documento->id, 'firmante_nombre' => 'Ana Ejemplo', 'ip' => '203.0.113.5', 'huella_documento' => $documento->huella, 'aceptada_en' => now('UTC'), 'verificacion' => 'casilla', 'copia_archivo_id' => $copia->id, 'conservar_hasta' => now()->addYears(5)]);
        NotaReserva::query()->create(['reserva_id' => $this->reserva->id, 'texto' => 'Ana viene con su mamá']);
        ReceptorFiscal::query()->create(['propiedad_id' => $this->propiedad->id, 'huesped_id' => $this->huesped->id, 'nombre' => 'Ana Ejemplo', 'identificador_fiscal' => 'XAXX010101000']);
        $aviso = Envio::query()->create(['propiedad_id' => $this->propiedad->id, 'reserva_id' => $this->reserva->id, 'canal' => 'correo', 'destino' => 'dueno@example.com', 'asunto' => 'Nueva reserva', 'cuerpo' => 'Ana Ejemplo, ana@example.com', 'estado' => 'manual', 'clave_idempotencia' => 'motor:reserva:1']);

        app(GestorPrivacidad::class)->anonimizar($this->huesped);

        expect($aceptacion->refresh()->copia_archivo_id)->toBeNull()
            ->and(Storage::disk('local')->exists($copia->ruta))->toBeFalse()
            ->and($aviso->refresh()->cuerpo)->toBeNull()
            ->and(NotaReserva::query()->count())->toBe(0)
            ->and(ReceptorFiscal::query()->count())->toBe(0);
    });

    it('anonimizar a quien viajó de acompañante limpia sus datos y deja los del titular', function () {
        app(GestorPrivacidad::class)->anonimizar($this->acompanante);

        expect($this->segunda->refresh()->documento_numero)->toBeNull()
            ->and($this->segunda->nombre)->toBe(__('llegada.anonimizado', ['id' => $this->acompanante->id]))
            ->and($this->titular->refresh()->documento_numero)->toBe('INE111');
    });

    it('el plazo borra el PDF de una aceptación aunque ya no tenga dirección', function () {
        $copia = app(GestorArchivos::class)->guardar('pdf', 'aceptacion.pdf', 'application/pdf', ClaseArchivo::CopiaAceptacion, null, $this->propiedad->id);
        $documento = DocumentoLegal::query()->create(['propiedad_id' => $this->propiedad->id, 'tipo' => 'reglamento', 'idioma' => 'es', 'version' => 1, 'titulo' => 'Reglamento', 'cuerpo' => 'Texto', 'huella' => str_repeat('a', 64), 'publicado_en' => now('UTC')]);
        Aceptacion::query()->create(['reserva_id' => $this->reserva->id, 'propiedad_id' => $this->propiedad->id, 'documento_legal_id' => $documento->id, 'firmante_nombre' => 'Ana', 'ip' => null, 'huella_documento' => $documento->huella, 'aceptada_en' => now('UTC'), 'verificacion' => 'casilla', 'copia_archivo_id' => $copia->id, 'conservar_hasta' => now()->subDay()]);

        app(GestorPrivacidad::class)->aplicarPlazos();

        expect(Storage::disk('local')->exists($copia->ruta))->toBeFalse();
    });

    it('el registro de auditoría se purga pasado el plazo', function () {
        RegistroAuditoria::anotar('prueba.vieja');
        $this->travel(366)->days();
        RegistroAuditoria::anotar('prueba.nueva');

        $this->artisan('alojaflow:limpiar')->assertSuccessful();

        expect(RegistroAuditoria::query()->pluck('accion')->all())->toBe(['prueba.nueva']);
    });
});

describe('espejo de la principal', function () {
    it('guarda los datos cifrados y sin las preferencias de otros usuarios', function () {
        $principal = Principal::query()->forceCreate(['propiedad_id' => $this->propiedad->id, 'url_base' => 'https://hd.example.test/', 'usuario' => 'a', 'contrasena' => 'b']);
        $ejecucion = EjecucionSincronizacion::query()->create(['principal_id' => $principal->id, 'origen' => OrigenEjecucion::Manual, 'estado' => EstadoEjecucion::EnCurso, 'iniciada_en' => now()]);
        $cargador = new CargadorEspejo($principal->id, $ejecucion->id, 10);

        $cargador->agregar('clienti', null, ['cognome' => 'Pérez', 'documento' => 'X1234567']);
        $cargador->agregar('personalizza', null, ['idutente' => '1', 'clave' => 'tema']);
        $cargador->agregar('personalizza', null, ['idutente' => '7', 'clave' => 'de otro']);
        $cargador->vaciar();

        $filas = DB::table('hd_filas')->orderBy('id')->get();
        expect($filas)->toHaveCount(2)
            ->and($filas[0]->datos)->not->toContain('X1234567')
            ->and(json_decode(Crypt::decryptString($filas[0]->datos), true)['documento'])->toBe('X1234567');
    });

    it('una propiedad que ya sincronizó se puede independizar; con una sincronización en curso, no', function () {
        $this->mock(GestorCopias::class)->shouldReceive('crear')->andReturn([]);
        $propiedad = Propiedad::query()->create(['nombre' => 'Sub', 'codigo' => 'sub', 'modo' => ModoPropiedad::Subordinada, 'zona_horaria' => 'UTC', 'moneda' => 'MXN']);
        $principal = Principal::query()->forceCreate(['propiedad_id' => $propiedad->id, 'url_base' => 'https://hd.example.test/', 'usuario' => 'a', 'contrasena' => 'b']);
        $vigente = EjecucionSincronizacion::query()->create(['principal_id' => $principal->id, 'origen' => OrigenEjecucion::Manual, 'estado' => EstadoEjecucion::Correcta, 'iniciada_en' => now()]);
        $principal->forceFill(['ejecucion_vigente_id' => $vigente->id])->save();
        $enCurso = EjecucionSincronizacion::query()->create(['principal_id' => $principal->id, 'origen' => OrigenEjecucion::Manual, 'estado' => EstadoEjecucion::EnCurso, 'iniciada_en' => now()]);

        expect(fn () => app(Independizador::class)->independizar($propiedad->refresh()))->toThrow(ErrorIndependizar::class, __('ui.independizar.error_sincronizando'));

        // Una que quedó colgada hace rato ya no estorba.
        $this->travel(31)->minutes();
        app(Independizador::class)->independizar($propiedad->refresh());

        expect($propiedad->refresh()->modo)->toBe(ModoPropiedad::Nativa);
    });
});

describe('avisos a otros sistemas', function () {
    it('la dirección se guarda cifrada y el historial solo muestra el sitio', function () {
        $webhook = Webhook::query()->create(['propiedad_id' => $this->propiedad->id, 'url' => 'https://hooks.example.com/services/T000/B000/secreto', 'secreto' => str_repeat('x', 40), 'eventos' => ['reserva_nueva'], 'activo' => true]);

        expect(DB::table('webhooks')->where('id', $webhook->id)->value('url'))->not->toContain('secreto')
            ->and($webhook->refresh()->url)->toContain('secreto');
    });
});

describe('mensajes', function () {
    it('un idioma raro en la ficha no bloquea los mensajes', function () {
        $this->huesped->forceFill(['idioma' => 'a/b'])->save();

        expect(Mensajero::idiomaDe($this->reserva->refresh()))->toBe('es');

        $this->artisan('alojaflow:mensajes')->assertSuccessful();
    });

    it('el aviso del motor no se manda para la propiedad de demostración', function () {
        $this->propiedad->forceFill(['es_demo' => true])->save();

        app(Mensajero::class)->avisarNuevaReserva($this->reserva->refresh(), 'dueno@example.com');

        expect(Envio::query()->where('destino', 'dueno@example.com')->count())->toBe(0);
    });
});

describe('correo', function () {
    it('exige TLS cuando se pide y usa smtps con SSL', function () {
        Ajustes::guardar('correo.host', 'smtp.example.com');
        Ajustes::guardar('correo.cifrado', 'tls');
        app(AjustesCorreo::class)->mailer();
        expect(config('mail.mailers.'.AjustesCorreo::MAILER))->toMatchArray(['scheme' => 'smtp', 'require_tls' => true, 'auto_tls' => true]);

        Ajustes::guardar('correo.cifrado', 'ssl');
        Ajustes::olvidar();
        app(AjustesCorreo::class)->mailer();
        expect(config('mail.mailers.'.AjustesCorreo::MAILER))->toMatchArray(['scheme' => 'smtps', 'require_tls' => false]);
    });
});

describe('recuperar la contraseña', function () {
    it('sin correo configurado no hay enlace ni página', function () {
        Livewire::test(IniciarSesion::class)->assertDontSee(__('filament-panels::auth/pages/login.actions.request_password_reset.label'));
        $this->get(Filament::getRequestPasswordResetUrl())->assertNotFound();
    });

    it('con correo configurado manda el enlace, y responde igual si la cuenta no existe', function () {
        Ajustes::guardar('correo.host', 'smtp.example.com');
        Ajustes::olvidar();
        Notification::fake();
        Mail::fake();
        $usuario = User::factory()->create(['activo' => true]);

        $this->get(Filament::getRequestPasswordResetUrl())->assertOk();
        config(['app.url' => 'https://hotel.example']);

        Livewire::test(PedirRecuperacion::class)->set('data.email', $usuario->email)->call('request')->assertNotified(__('passwords.sent'));
        // El enlace sale de la dirección de la instalación, no de la petición.
        expect((string) Notification::sent($usuario, ResetPassword::class)->first()?->url)->toContain('://hotel.example/');

        Livewire::test(PedirRecuperacion::class)->set('data.email', 'nadie@example.com')->call('request')->assertNotified(__('passwords.sent'));
    });
});

describe('pantallas', function () {
    it('las páginas de error van en el idioma del navegador y sin datos internos', function () {
        $this->get('/no-existe', ['Accept-Language' => 'fr'])->assertNotFound()->assertSee('Nous ne trouvons pas cette page');
        $this->get('/no-existe', ['Accept-Language' => 'es-MX'])->assertNotFound()->assertSee('No encontramos esta página')->assertDontSee('Symfony');
    });

    it('el avatar se genera aquí, sin pedirlo a un tercero', function () {
        $usuario = User::factory()->create(['name' => 'Ana <b>Pérez</b>']);
        $imagen = app(AvatarLocal::class)->get($usuario);

        expect($imagen)->toStartWith('data:image/svg+xml;base64,')
            ->and(base64_decode(substr($imagen, 26), true))->toContain('A&lt;')->not->toContain('<b>');
    });

    it('al elegir el país se proponen moneda, zona, idioma y paquete', function () {
        expect(CatalogoPaises::valoresDe('ES'))->toBe(['moneda' => 'EUR', 'zona_horaria' => 'Europe/Madrid', 'idioma' => 'es', 'paquete_pais' => 'espana'])
            ->and(CatalogoPaises::valoresDe('US'))->toMatchArray(['moneda' => 'USD', 'zona_horaria' => 'America/New_York', 'idioma' => 'en', 'paquete_pais' => 'estados_unidos'])
            ->and(CatalogoPaises::valoresDe('BR')['idioma'])->toBe('pt')
            ->and(CatalogoPaises::opciones()['MX'])->toBe('México');

        $this->actingAs(User::factory()->create(['activo' => true, 'es_administrador' => true]));
        $existente = Propiedad::query()->create(['nombre' => 'Vieja', 'codigo' => 'vieja', 'modo' => ModoPropiedad::Nativa, 'pais' => 'MX', 'zona_horaria' => 'America/Mexico_City', 'moneda' => 'MXN', 'idioma' => 'es']);
        Livewire::test(EditPropiedad::class, ['record' => $existente->getKey()])->set('data.pais', 'FR')->assertSet('data.moneda', 'MXN');

        Livewire::test(CreatePropiedad::class)->set('data.nombre', 'Mi Hotel Bonito')->assertSet('data.codigo', 'mi-hotel-bonito')
            ->set('data.pais', 'FR')->assertSet('data.moneda', 'EUR')->assertSet('data.zona_horaria', 'Europe/Paris')->assertSet('data.idioma', 'fr');
    });

    it('un aviso se cierra a mano solo con permiso sobre esa propiedad', function () {
        $aviso = Aviso::abrir($this->propiedad->id, 'pago_duplicado', App\Enums\NivelAviso::Error, ['reserva' => '1']);
        $ajena = Propiedad::query()->create(['nombre' => 'Otra', 'codigo' => 'otra', 'modo' => ModoPropiedad::Nativa, 'zona_horaria' => 'UTC', 'moneda' => 'MXN']);
        $usuario = User::factory()->create(['activo' => true]);
        Acceso::asignarRoles($usuario, [['propiedad_id' => $ajena->id, 'rol' => 'recepcion']]);
        $this->actingAs($usuario);

        Livewire::test(Resumen::class)->call('atenderAviso', $aviso->id);
        expect($aviso->refresh()->resuelto_en)->toBeNull();

        Acceso::asignarRoles($usuario, [['propiedad_id' => $this->propiedad->id, 'rol' => 'recepcion']]);
        Livewire::test(Resumen::class)->call('atenderAviso', $aviso->id);
        expect($aviso->refresh()->resuelto_en)->not->toBeNull();
    });
});
