<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

use App\Comunicacion\Mensajero;
use App\Comunicacion\VariablesMensaje;
use App\Enums\AccionAutomatizacion;
use App\Enums\DisparadorAutomatizacion;
use App\Enums\EstadoReserva;
use App\Enums\EstadoTarea;
use App\Enums\FrecuenciaLimpieza;
use App\Enums\LugarOmision;
use App\Enums\ModoPropiedad;
use App\Enums\TipoContacto;
use App\Enums\TipoTarea;
use App\Filament\Pages\AvisoEntradasSalidas;
use App\Filament\Pages\Limpieza;
use App\Filament\Resources\Huespedes\Pages\ViewHuesped;
use App\Filament\Resources\ListasComprobacion\ListaComprobacionResource;
use App\Filament\Resources\ListasComprobacion\Pages\CreateListaComprobacion;
use App\Filament\Resources\MantenimientosRecurrentes\MantenimientoRecurrenteResource;
use App\Filament\Resources\Omisiones\OmisionResource;
use App\Filament\Resources\Omisiones\Pages\CreateOmision;
use App\Filament\Resources\Omisiones\Pages\ListOmisiones;
use App\Limpieza\AvisoEquipo;
use App\Limpieza\FormatoAviso;
use App\Limpieza\GestorLimpieza;
use App\Limpieza\Omisiones;
use App\Models\Automatizacion;
use App\Models\CampoHuesped;
use App\Models\ContactoHuesped;
use App\Models\Envio;
use App\Models\EventoReserva;
use App\Models\Huesped;
use App\Models\ListaComprobacion;
use App\Models\MantenimientoRecurrente;
use App\Models\Omision;
use App\Models\Plantilla;
use App\Models\Propiedad;
use App\Models\ReglaLimpieza;
use App\Models\Reserva;
use App\Models\Tarea;
use App\Models\Unidad;
use App\Models\User;
use App\Modulos\Modulo;
use App\Modulos\Modulos;
use App\Reservas\GestorReservas;
use App\Seguridad\Acceso;
use App\Servicios\Indicadores;
use App\Servicios\Informes;
use App\Support\Ajustes;
use App\Support\Contactos;
use App\Support\Formato;
use Database\Seeders\RolesYPermisosSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Str;
use Livewire\Livewire;

afterEach(fn () => Ajustes::olvidar());

beforeEach(function () {
    $this->seed(RolesYPermisosSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('app'));
    $this->propiedad = Propiedad::query()->create(['nombre' => 'Posada', 'codigo' => 'posada', 'modo' => ModoPropiedad::Nativa, 'zona_horaria' => 'America/Mexico_City', 'moneda' => 'MXN', 'pais' => 'MX', 'idioma' => 'es']);
    $this->u1 = Unidad::query()->create(['propiedad_id' => $this->propiedad->id, 'codigo' => 'A', 'nombre' => 'Cabaña A', 'capacidad' => 4, 'orden' => 1]);
    $this->u2 = Unidad::query()->create(['propiedad_id' => $this->propiedad->id, 'codigo' => 'B', 'nombre' => 'Cabaña B', 'capacidad' => 4, 'orden' => 2]);
    $this->ana = Huesped::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Ana', 'apellido1' => 'Uno']);
    ContactoHuesped::query()->create(['huesped_id' => $this->ana->id, 'tipo' => TipoContacto::Telefono, 'valor' => '55 1111 2222', 'valor_normalizado' => '5511112222', 'orden' => 0]);
    $this->luis = Huesped::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Luis', 'apellido1' => 'Dos']);
    $this->hoy = $this->propiedad->hoy();
    $this->reservar = fn (Unidad $unidad, Huesped $huesped, int $llegada, int $salida, array $mas = []): Reserva => app(GestorReservas::class)->crear($this->propiedad, $mas + [
        'unidad_id' => $unidad->id, 'titular_id' => $huesped->id, 'llegada' => $this->hoy->addDays($llegada)->format('Y-m-d'), 'salida' => $this->hoy->addDays($salida)->format('Y-m-d'),
        'personas' => 2, 'estado' => EstadoReserva::Confirmada->value, 'total' => 100000,
    ]);
    $this->super = User::factory()->create(['es_administrador' => true, 'es_superadministrador' => true, 'activo' => true, 'idioma' => 'es']);
    Modulos::marcarPreguntas('preguntas');
});

describe('aviso de entradas y salidas', function () {
    it('dice quién sale, quién llega y qué se limpia, y se salta los días sin movimiento', function () {
        ($this->reservar)($this->u1, $this->ana, 1, 4);
        ($this->reservar)($this->u2, $this->luis, 4, 6);
        $regla = ReglaLimpieza::query()->create(['reserva_id' => Reserva::query()->where('unidad_id', $this->u1->id)->value('id'), 'frecuencia' => FrecuenciaLimpieza::Diaria]);

        $texto = app(AvisoEquipo::class)->generar($this->propiedad, $this->hoy, 7, ['limpieza' => true]);

        // Llega, limpieza en los dos días de en medio, y un día en que uno sale y otro llega.
        expect(substr_count($texto, '🔸'))->toBe(2)
            ->and(substr_count($texto, '🔹'))->toBe(2)
            ->and(substr_count($texto, '✅'))->toBe(2)
            ->and(substr_count($texto, '📌'))->toBe(5)
            ->and($texto)->toContain('Salen: Cabaña A')->toContain('Llegan: Cabaña B');

        // El día de llegada y el de salida no llevan limpieza aparte.
        $dias = explode('────────────────', $texto);
        expect($dias[0])->toContain('🔸')->not->toContain('✅');

        $regla->delete();
    });

    it('sin nada que decir lo dice, y una reserva cancelada no sale', function () {
        $reserva = ($this->reservar)($this->u1, $this->ana, 1, 2);
        app(GestorReservas::class)->cancelar($reserva);

        expect(app(AvisoEquipo::class)->generar($this->propiedad, $this->hoy, 7, []))->toBe(__('equipo.aviso.sin_movimiento'));
    });

    it('añade lo que se marque, y limita el rango', function () {
        ($this->reservar)($this->u1, $this->ana, 1, 3, ['comentario' => 'Cuna extra', 'origen_texto' => 'Airbnb']);

        $texto = app(AvisoEquipo::class)->generar($this->propiedad, $this->hoy, 7, ['nombre' => true, 'personas' => true, 'noches' => true, 'telefono' => true, 'comentario' => true, 'origen' => true]);

        expect($texto)->toContain('Ana Uno')->toContain('2 personas')->toContain('2 noches')->toContain('55 1111 2222')->toContain('Cuna extra');
        // Hasta 92 días: una reserva más allá no sale.
        ($this->reservar)($this->u2, $this->luis, 100, 102);
        expect(app(AvisoEquipo::class)->generar($this->propiedad, $this->hoy, 9999, []))->not->toContain('Cabaña B');
    });

    it('la pantalla la ve el personal, pero limpieza no pide teléfonos ni comentarios', function () {
        $limpieza = User::factory()->create(['activo' => true, 'idioma' => 'es']);
        Acceso::asignarRoles($limpieza, [['propiedad_id' => $this->propiedad->id, 'rol' => 'limpieza']]);
        ($this->reservar)($this->u1, $this->ana, 1, 3, ['comentario' => 'Secreto']);

        $pantalla = Livewire::actingAs($limpieza)->test(AvisoEntradasSalidas::class)
            ->assertSee(__('equipo.aviso.mostrar_limpieza'))
            ->assertDontSee(__('equipo.aviso.comentario'))
            // El navegador manda lo que quiera: no por eso sale.
            ->set('telefono', true)->set('comentario', true);

        expect($pantalla->get('texto'))->not->toContain('55 1111 2222')->not->toContain('Secreto');
        $this->actingAs($limpieza)->get(OmisionResource::getUrl())->assertForbidden();
    });

    it('al cambiar una opción se vuelve a armar, y el texto retocado se respeta hasta entonces', function () {
        ($this->reservar)($this->u1, $this->ana, 1, 3);

        $pantalla = Livewire::actingAs($this->super)->test(AvisoEntradasSalidas::class);
        expect($pantalla->get('texto'))->not->toContain('Ana Uno');

        $pantalla->set('texto', 'mi texto')->assertSet('texto', 'mi texto');
        expect($pantalla->set('nombre', true)->get('texto'))->toContain('Ana Uno');
    });

    it('borrar una fecha o ponerla al revés no truena', function () {
        $pantalla = Livewire::actingAs($this->super)->test(AvisoEntradasSalidas::class);
        $desde = $pantalla->get('desde');

        $pantalla->set('hasta', '')->assertHasNoErrors();
        expect($pantalla->get('hasta'))->toBeGreaterThanOrEqual($desde);

        $pantalla->set('hasta', '2001-01-01')->assertSet('hasta', $desde)->assertHasNoErrors();
    });

    it('avisa si el rango se pasa del máximo, y lo recorta', function () {
        $pantalla = Livewire::actingAs($this->super)->test(AvisoEntradasSalidas::class)
            ->set('desde', '2030-01-01')->set('hasta', '2031-01-01');

        $pantalla->assertSet('excedido', true)->assertSee(__('equipo.aviso.maximo', ['dias' => AvisoEquipo::DIAS_MAXIMOS]));
        expect($pantalla->get('hasta'))->toBe('2030-04-02');
    });

    it('los rangos rápidos cuentan desde la fecha inicial', function () {
        Livewire::actingAs($this->super)->test(AvisoEntradasSalidas::class)
            ->set('desde', '2030-01-10')->call('rango', 7)->assertSet('hasta', '2030-01-16')
            ->call('rango', 1)->assertSet('hasta', '2030-01-10');
    });

    describe('el formato', function () {
        beforeEach(function () {
            ($this->reservar)($this->u1, $this->ana, 1, 3);
        });

        it('por omisión escribe la fecha corta, con el orden de día y mes del idioma', function () {
            $dia = $this->hoy->addDay();
            $texto = app(AvisoEquipo::class)->generar($this->propiedad, $this->hoy, 3, []);

            expect($texto)->toContain('📌 '.Str::ucfirst(Formato::fecha($dia, 'dddd')).' '.$dia->format('d/m/y'));

            app()->setLocale('en');
            expect(FormatoAviso::fecha($dia, 'numerica'))->toBe($dia->format('m/d/y'));
            app()->setLocale('de');
            expect(FormatoAviso::fecha($dia, 'numerica'))->toBe($dia->format('d.m.y'))
                ->and(FormatoAviso::fecha($dia, 'dia_abreviada'))->toEndWith($dia->format('d.m'));
        });

        it('cambia iconos, textos, separador, renglones, orden y negritas', function () {
            ($this->reservar)($this->u2, $this->luis, 3, 5);
            $texto = app(AvisoEquipo::class)->generar($this->propiedad, $this->hoy, 7, [], [
                'fecha' => 'larga', 'icono_dia' => '📅', 'icono_salen' => '', 'icono_llegan' => '🔑', 'separador' => 'blanco',
                'texto_llegan' => 'Entran', 'unidades' => 'renglones', 'orden' => 'llegan', 'negritas' => true,
            ]);

            expect($texto)->toContain('📅 *'.Str::ucfirst(Formato::fecha($this->hoy->addDay(), 'dddd')).' '.Formato::fecha($this->hoy->addDay(), 'LL').'*')
                ->toContain("🔑 *Entran:*\n• Cabaña A")
                ->not->toContain('🔹')->not->toContain('────')
                ->toContain("\n\n");
            // Un día en que uno llega y otro sale: primero las llegadas.
            $dosDias = (string) collect(explode("\n\n", $texto))->first(fn (string $b): bool => str_contains($b, 'Entran') && str_contains($b, __('equipo.aviso.salen')));
            expect(mb_strpos($dosDias, 'Entran'))->toBeLessThan(mb_strpos($dosDias, __('equipo.aviso.salen')));
        });

        it('se guarda por propiedad y se puede volver al original', function () {
            $pantalla = Livewire::actingAs($this->super)->test(AvisoEntradasSalidas::class)
                ->set('formato.icono_dia', '⭐')->set('formato.fecha', 'numerica')->call('guardarFormato');
            expect($pantalla->get('texto'))->toContain('⭐ ');

            $otra = Livewire::actingAs($this->super)->test(AvisoEntradasSalidas::class);
            expect($otra->get('formato')['icono_dia'])->toBe('⭐')->and($otra->get('texto'))->toContain('⭐ ');

            $otra->call('restablecerFormato');
            expect(Livewire::actingAs($this->super)->test(AvisoEntradasSalidas::class)->get('formato')['icono_dia'])->toBe('📌');
        });

        it('limpieza puede probar otro formato, pero no cambiarlo para todos', function () {
            $limpieza = User::query()->create(['name' => 'Li', 'email' => 'limpia@example.com', 'password' => 'una-clave-larga-de-prueba', 'activo' => true]);
            Acceso::asignarRoles($limpieza, [['propiedad_id' => $this->propiedad->id, 'rol' => 'limpieza']]);

            $pantalla = Livewire::actingAs($limpieza)->test(AvisoEntradasSalidas::class)
                ->assertDontSee(__('equipo.aviso.guardar_formato'))
                ->set('formato.icono_dia', '⭐');
            expect($pantalla->get('texto'))->toContain('⭐ ');

            $pantalla->call('guardarFormato')->assertForbidden();
            expect(FormatoAviso::de($this->propiedad->id)['icono_dia'])->toBe('📌');
        });

        it('lo que no está en la lista no pasa, y los textos van en un renglón', function () {
            expect(FormatoAviso::limpiar(['icono_dia' => '<b>', 'fecha' => 'cualquiera', 'separador' => "x\ny", 'texto_salen' => "Sa\nlen"]))
                ->toMatchArray(['icono_dia' => '📌', 'fecha' => 'dia_numerica', 'separador' => 'linea', 'texto_salen' => 'Sa len']);
        });
    });

    it('apagado no abre, y en una instalación que se actualiza llega apagado', function () {
        Modulos::guardar([Modulo::Limpieza]);
        $this->actingAs($this->super)->get(AvisoEntradasSalidas::getUrl())->assertForbidden();

        Modulos::guardar([Modulo::AvisoEquipo]);
        $this->get(AvisoEntradasSalidas::getUrl())->assertOk();
    });
});

describe('clientes omitidos', function () {
    it('un huésped se encuentra por su nombre completo, repartido en varias columnas', function () {
        $marta = Huesped::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Marta', 'apellido1' => 'Solís']);
        $conDos = Huesped::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Marta', 'apellido1' => 'Solís', 'apellido2' => 'Vega']);

        $pantalla = Livewire::actingAs($this->super)->test(CreateOmision::class)->fillForm(['propiedad_id' => $this->propiedad->id]);
        $selector = collect($pantalla->instance()->getSchema('form')->getFlatComponents(withHidden: true))
            ->first(fn (object $campo): bool => method_exists($campo, 'getName') && $campo->getName() === 'huesped_id');

        expect(array_keys($selector->getSearchResults('Marta Solís')))->toEqualCanonicalizing([$marta->id, $conDos->id])
            ->and(array_keys($selector->getSearchResults('  solís   vega ')))->toBe([$conDos->id])
            ->and(array_keys($selector->getSearchResults('Solís Marta')))->toEqualCanonicalizing([$marta->id, $conDos->id])
            ->and($selector->getSearchResults('Marta Pérez'))->toBe([])
            ->and($selector->getSearchResults('%'))->toBe([]);
    });

    describe('quien solo ve lo suyo', function () {
        beforeEach(function () {
            $this->limitado = User::factory()->create(['activo' => true, 'idioma' => 'es']);
            Acceso::asignarRoles($this->limitado, [['propiedad_id' => $this->propiedad->id, 'rol' => 'recepcion', 'solo_propias' => true]]);
            $this->suyo = Huesped::query()->forceCreate(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Marta', 'apellido1' => 'Propia', 'creado_por' => $this->limitado->id]);
            $this->ajeno = Huesped::query()->forceCreate(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Marta', 'apellido1' => 'Ajena', 'creado_por' => $this->super->id]);
        });

        it('solo encuentra a sus huéspedes', function () {
            $selector = fn (array $datos): object => collect(Livewire::actingAs($this->limitado)->test(CreateOmision::class)
                ->fillForm(['propiedad_id' => $this->propiedad->id, ...$datos])->instance()->getSchema('form')->getFlatComponents(withHidden: true))
                ->first(fn (object $campo): bool => method_exists($campo, 'getName') && $campo->getName() === 'huesped_id');

            expect(array_keys($selector([])->getSearchResults('Marta')))->toBe([$this->suyo->id])
                ->and($selector(['huesped_id' => $this->ajeno->id])->getOptionLabel(withDefault: false))->toBeNull()
                ->and($selector(['huesped_id' => $this->suyo->id])->getOptionLabel(withDefault: false))->toBe('Marta Propia');
        });

        it('enviar a mano a un huésped ajeno se rechaza', function () {
            Livewire::actingAs($this->limitado)->test(CreateOmision::class)
                ->fillForm(['propiedad_id' => $this->propiedad->id, 'por' => 'huesped', 'huesped_id' => $this->ajeno->id])
                ->call('create')->assertHasFormErrors(['huesped_id']);
            expect(Omision::query()->count())->toBe(0);

            Livewire::actingAs($this->limitado)->test(CreateOmision::class)
                ->fillForm(['propiedad_id' => $this->propiedad->id, 'por' => 'huesped', 'huesped_id' => $this->suyo->id])
                ->call('create')->assertHasNoFormErrors();
            expect(Omision::query()->pluck('huesped_id')->all())->toBe([$this->suyo->id]);
        });

        it('no ve ni abre lo omitido de otro, y sí lo omitido por tipo', function () {
            $ajena = Omision::query()->create(['propiedad_id' => $this->propiedad->id, 'huesped_id' => $this->ajeno->id]);
            $suya = Omision::query()->create(['propiedad_id' => $this->propiedad->id, 'huesped_id' => $this->suyo->id]);
            $tipo = Omision::query()->create(['propiedad_id' => $this->propiedad->id, 'campo' => 'Tipo de cliente', 'valor' => 'Familia']);

            Livewire::actingAs($this->limitado)->test(ListOmisiones::class)
                ->assertCanSeeTableRecords([$suya, $tipo])->assertCanNotSeeTableRecords([$ajena]);
            $this->actingAs($this->limitado)->get(OmisionResource::getUrl('edit', ['record' => $ajena]))->assertNotFound();

            Livewire::actingAs($this->super)->test(ListOmisiones::class)->assertCanSeeTableRecords([$ajena, $suya, $tipo]);
        });

        it('fusionar no se le ofrece, y a quien no tiene el límite sí', function () {
            Livewire::actingAs($this->limitado)->test(ViewHuesped::class, ['record' => $this->suyo->getKey()])->assertActionHidden('fusionar');
            Livewire::actingAs($this->super)->test(ViewHuesped::class, ['record' => $this->suyo->getKey()])->assertActionVisible('fusionar');
        });
    });

    it('no salen en el aviso, y sus fechas siguen ocupadas', function () {
        $reserva = ($this->reservar)($this->u1, $this->ana, 1, 3);
        Omision::query()->create(['propiedad_id' => $this->propiedad->id, 'huesped_id' => $this->ana->id, 'en_aviso' => true]);

        expect(app(AvisoEquipo::class)->generar($this->propiedad, $this->hoy, 7, []))->toBe(__('equipo.aviso.sin_movimiento'))
            ->and(DB::table('reservas_noches')->where('reserva_id', $reserva->id)->count())->toBe(2);
    });

    it('la casilla de cada lugar es independiente', function () {
        Omision::query()->create(['propiedad_id' => $this->propiedad->id, 'huesped_id' => $this->ana->id, 'en_aviso' => true, 'en_informes' => false, 'en_mensajes' => false]);

        expect(Omisiones::estaOmitido($this->ana->id, $this->propiedad->id, LugarOmision::Aviso))->toBeTrue()
            ->and(Omisiones::estaOmitido($this->ana->id, $this->propiedad->id, LugarOmision::Informes))->toBeFalse()
            ->and(Omisiones::estaOmitido($this->luis->id, $this->propiedad->id, LugarOmision::Aviso))->toBeFalse();
    });

    it('también todos los de un tipo, por un campo de la ficha', function () {
        CampoHuesped::query()->create(['huesped_id' => $this->luis->id, 'campo' => 'Tipo de cliente', 'valor' => 'Familia']);
        Omision::query()->create(['propiedad_id' => $this->propiedad->id, 'campo' => 'Tipo de cliente', 'valor' => 'Familia', 'en_aviso' => true]);

        expect(Omisiones::huespedes([$this->propiedad->id], LugarOmision::Aviso))->toBe([$this->luis->id]);
    });

    it('en informes no cuentan sus noches ni ingresos', function () {
        ($this->reservar)($this->u1, $this->ana, 1, 3);
        ($this->reservar)($this->u2, $this->luis, 1, 3);
        $desde = $this->hoy;
        $hasta = $this->hoy->addDays(10);
        $antes = Informes::generar('unidades', [$this->propiedad->id], $desde, $hasta)['filas'];
        $indicadorAntes = Indicadores::porPropiedad([$this->propiedad->id], $desde, $hasta)[0];
        expect($antes)->toHaveCount(2);

        Omision::query()->create(['propiedad_id' => $this->propiedad->id, 'huesped_id' => $this->ana->id, 'en_aviso' => false, 'en_informes' => true]);

        expect(Informes::generar('unidades', [$this->propiedad->id], $desde, $hasta)['filas'])->toHaveCount(1);
        $indicador = Indicadores::porPropiedad([$this->propiedad->id], $desde, $hasta)[0];
        expect($indicador['reservas'] ?? null)->toBeLessThan($indicadorAntes['reservas'] ?? PHP_INT_MAX);
    });

    it('no le llegan las automatizaciones, pero al resto sí', function () {
        $plantilla = Plantilla::query()->create(['propiedad_id' => $this->propiedad->id, 'codigo' => 'h', 'nombre' => 'H', 'asunto' => ['es' => 'Hola'], 'cuerpo' => ['es' => 'Hola']]);
        Automatizacion::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'R', 'disparador' => DisparadorAutomatizacion::ReservaCreada, 'accion' => AccionAutomatizacion::CorreoHuesped, 'plantilla_id' => $plantilla->id]);
        Omision::query()->create(['propiedad_id' => $this->propiedad->id, 'huesped_id' => $this->ana->id, 'en_aviso' => false, 'en_mensajes' => true]);
        ContactoHuesped::query()->create(['huesped_id' => $this->luis->id, 'tipo' => TipoContacto::Correo, 'valor' => 'luis@example.com', 'valor_normalizado' => 'luis@example.com', 'orden' => 0]);
        ContactoHuesped::query()->create(['huesped_id' => $this->ana->id, 'tipo' => TipoContacto::Correo, 'valor' => 'ana@example.com', 'valor_normalizado' => 'ana@example.com', 'orden' => 1]);

        ($this->reservar)($this->u1, $this->ana, 1, 3);
        ($this->reservar)($this->u2, $this->luis, 1, 3);
        EventoReserva::query()->count();
        app(Mensajero::class)->procesarEventos();

        $destinos = Envio::query()->pluck('destino')->all();
        expect($destinos)->toContain('luis@example.com')->not->toContain('ana@example.com');
    });

    it('se dan de alta en su pantalla, con casillas', function () {
        Livewire::actingAs($this->super)->test(CreateOmision::class)
            ->fillForm(['propiedad_id' => $this->propiedad->id, 'por' => 'huesped', 'huesped_id' => $this->ana->id, 'en_aviso' => true, 'en_informes' => true, 'en_mensajes' => false, 'motivo' => 'Familia'])
            ->call('create')->assertHasNoFormErrors();

        $omision = Omision::query()->firstOrFail();
        expect($omision->huesped_id)->toBe($this->ana->id)
            ->and($omision->en_informes)->toBeTrue()
            ->and($omision->en_mensajes)->toBeFalse();
        $this->actingAs($this->super)->get(OmisionResource::getUrl())->assertOk()->assertSee('Ana Uno');
    });

    it('el mismo cliente dos veces se avisa en el formulario', function () {
        Omision::query()->create(['propiedad_id' => $this->propiedad->id, 'huesped_id' => $this->ana->id]);

        Livewire::actingAs($this->super)->test(CreateOmision::class)
            ->fillForm(['propiedad_id' => $this->propiedad->id, 'por' => 'huesped', 'huesped_id' => $this->ana->id])
            ->call('create')->assertHasFormErrors(['huesped_id']);
    });

    it('no se puede omitir a un huésped de otra propiedad', function () {
        $otra = Propiedad::query()->create(['nombre' => 'Otra', 'codigo' => 'otra', 'modo' => ModoPropiedad::Nativa, 'zona_horaria' => 'UTC', 'moneda' => 'MXN', 'pais' => 'MX', 'idioma' => 'es']);
        $ajeno = Huesped::query()->create(['propiedad_id' => $otra->id, 'nombre' => 'Ajeno']);

        Livewire::actingAs($this->super)->test(CreateOmision::class)
            ->fillForm(['propiedad_id' => $this->propiedad->id, 'por' => 'huesped', 'huesped_id' => $ajeno->id])
            ->call('create')->assertHasFormErrors(['huesped_id']);
        expect(Omision::query()->count())->toBe(0);
    });
});

describe('días de limpieza', function () {
    it('una regla de días sueltos solo toca esos días, sin contar llegada ni salida', function () {
        $reserva = ($this->reservar)($this->u1, $this->ana, 1, 6);
        $elegido = $this->hoy->addDays(3);
        ReglaLimpieza::query()->create(['reserva_id' => $reserva->id, 'frecuencia' => FrecuenciaLimpieza::Fechas, 'fechas' => [$elegido->format('Y-m-d')]]);

        app(GestorLimpieza::class)->generarTareas($this->propiedad);

        expect(Tarea::query()->where('tipo', TipoTarea::LimpiezaEstancia->value)->pluck('fecha')->map->format('Y-m-d')->all())->toBe([$elegido->format('Y-m-d')])
            ->and(substr_count(app(AvisoEquipo::class)->generar($this->propiedad, $this->hoy, 7, ['limpieza' => true]), '✅'))->toBe(1);
    });
});

describe('WhatsApp', function () {
    it('pone el prefijo del país si no lo trae, y respeta el que trae', function () {
        expect(Contactos::internacional('55 1111 2222', 'MX'))->toBe('+525511112222')
            ->and(Contactos::internacional('044 55 1111 2222', 'MX'))->toBe('+525511112222')
            ->and(Contactos::internacional('525511112222', 'MX'))->toBe('+525511112222')
            ->and(Contactos::internacional('+34 600 111 222', 'MX'))->toBe('+34600111222')
            ->and(Contactos::internacional('0034600111222', 'MX'))->toBe('+34600111222')
            ->and(Contactos::internacional('600111222', 'ES'))->toBe('+34600111222')
            ->and(Contactos::internacional('2025550123', 'US'))->toBe('+12025550123')
            ->and(Contactos::internacional('123', 'MX'))->toBeNull()
            ->and(Contactos::internacional('5511112222', null))->toBeNull()
            ->and(Contactos::internacional(null, 'MX'))->toBeNull();
    });

    it('el enlace de la ficha y el del mensaje usan el país de la propiedad', function () {
        $contacto = ContactoHuesped::query()->where('huesped_id', $this->ana->id)->firstOrFail();

        expect($contacto->enlaceWhatsapp('MX'))->toBe('https://wa.me/525511112222')
            ->and($contacto->enlaceWhatsapp())->toBeNull()
            ->and(VariablesMensaje::enlaceWhatsapp('55 1111 2222', 'Hola', 'MX'))->toBe('https://wa.me/525511112222?text=Hola');
    });
});

describe('listas de comprobación', function () {
    it('cada tarea guarda su copia, y las casillas se marcan en la pantalla de limpieza', function () {
        ListaComprobacion::query()->create(['propiedad_id' => $this->propiedad->id, 'tipo_tarea' => TipoTarea::LimpiezaSalida, 'nombre' => 'Salida', 'puntos' => ['Sábanas', 'Baño']]);
        ($this->reservar)($this->u1, $this->ana, 1, 2);
        app(GestorLimpieza::class)->generarTareas($this->propiedad);
        $tarea = Tarea::query()->where('tipo', TipoTarea::LimpiezaSalida->value)->firstOrFail();

        expect($tarea->puntos)->toEqual([['texto' => 'Sábanas', 'hecho' => false], ['texto' => 'Baño', 'hecho' => false]]);

        // Cambiar la lista no altera lo ya creado.
        ListaComprobacion::query()->update(['puntos' => ['Otra cosa']]);
        app(GestorLimpieza::class)->generarTareas($this->propiedad);
        expect($tarea->refresh()->puntos[0]['texto'])->toBe('Sábanas');

        Livewire::actingAs($this->super)->test(Limpieza::class)->set('fecha', $tarea->fecha->format('Y-m-d'))->assertSee('Sábanas')->call('marcarPunto', $tarea->id, 1);
        expect($tarea->refresh()->puntos[1]['hecho'])->toBeTrue();
    });

    it('se crea desde su pantalla, y sus puntos llegan a la tarea', function () {
        Livewire::actingAs($this->super)->test(CreateListaComprobacion::class)
            ->fillForm(['propiedad_id' => $this->propiedad->id, 'tipo_tarea' => TipoTarea::LimpiezaSalida->value, 'nombre' => 'Salida', 'puntos' => [['punto' => 'Sábanas'], ['punto' => 'Baño']], 'activa' => true])
            ->call('create')->assertHasNoFormErrors();
        ($this->reservar)($this->u1, $this->ana, 1, 2);

        app(GestorLimpieza::class)->generarTareas($this->propiedad);

        expect(array_column(Tarea::query()->where('tipo', TipoTarea::LimpiezaSalida->value)->firstOrFail()->puntos, 'texto'))->toBe(['Sábanas', 'Baño']);
    });

    it('sus pantallas son del módulo de limpieza y de quien ajusta', function () {
        $this->actingAs($this->super);
        $this->get(ListaComprobacionResource::getUrl())->assertOk();
        $this->get(MantenimientoRecurrenteResource::getUrl())->assertOk();
        $this->get(ListaComprobacionResource::getUrl('create'))->assertOk();

        Modulos::guardar([]);
        $this->get(ListaComprobacionResource::getUrl())->assertForbidden();

        $recepcion = User::factory()->create(['activo' => true, 'idioma' => 'es']);
        Acceso::asignarRoles($recepcion, [['propiedad_id' => $this->propiedad->id, 'rol' => 'recepcion']]);
        Modulos::guardar(Modulo::cases());
        $this->actingAs($recepcion)->get(ListaComprobacionResource::getUrl())->assertForbidden();
    });
});

describe('mantenimiento recurrente', function () {
    it('crea su tarea cuando se acerca, una sola vez, y avanza a la siguiente', function () {
        $trabajo = MantenimientoRecurrente::query()->create(['propiedad_id' => $this->propiedad->id, 'unidad_id' => $this->u1->id, 'titulo' => 'Cambiar filtros', 'cada_dias' => 30, 'proxima_fecha' => $this->hoy->addDays(2)->format('Y-m-d')]);
        $gestor = app(GestorLimpieza::class);

        $gestor->generarTareas($this->propiedad);
        $gestor->generarTareas($this->propiedad);

        $tareas = Tarea::query()->where('titulo', 'Cambiar filtros')->get();
        expect($tareas)->toHaveCount(1)
            ->and($tareas[0]->tipo)->toBe(TipoTarea::Mantenimiento)
            ->and($tareas[0]->estado)->toBe(EstadoTarea::Pendiente)
            ->and($trabajo->refresh()->proxima_fecha->format('Y-m-d'))->toBe($this->hoy->addDays(32)->format('Y-m-d'));
    });

    it('si la fecha ya pasó no acumula las atrasadas, y uno apagado no genera', function () {
        $trabajo = MantenimientoRecurrente::query()->create(['propiedad_id' => $this->propiedad->id, 'titulo' => 'Fumigar', 'cada_dias' => 7, 'proxima_fecha' => $this->hoy->subDays(70)->format('Y-m-d')]);
        MantenimientoRecurrente::query()->create(['propiedad_id' => $this->propiedad->id, 'titulo' => 'Apagado', 'cada_dias' => 7, 'proxima_fecha' => $this->hoy->format('Y-m-d'), 'activo' => false]);

        app(GestorLimpieza::class)->generarTareas($this->propiedad);

        expect(Tarea::query()->where('titulo', 'Fumigar')->count())->toBeLessThanOrEqual(2)
            ->and(Tarea::query()->where('titulo', 'Fumigar')->min('fecha'))->toBeGreaterThanOrEqual($this->hoy->format('Y-m-d'))
            ->and(Tarea::query()->where('titulo', 'Apagado')->count())->toBe(0)
            ->and($trabajo->refresh()->proxima_fecha->gt($this->hoy))->toBeTrue();
    });

    it('una tarea futura no se cancela al volver a generar', function () {
        MantenimientoRecurrente::query()->create(['propiedad_id' => $this->propiedad->id, 'titulo' => 'Filtros', 'cada_dias' => 60, 'proxima_fecha' => $this->hoy->addDays(3)->format('Y-m-d')]);
        $gestor = app(GestorLimpieza::class);

        $gestor->generarTareas($this->propiedad);
        $gestor->generarTareas($this->propiedad);

        expect(Tarea::query()->where('titulo', 'Filtros')->value('estado'))->toBe(EstadoTarea::Pendiente);
    });
});
