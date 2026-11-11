<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

use App\Actualizacion\Empaquetador;
use App\Enums\EstadoReserva;
use App\Enums\ModoPropiedad;
use App\Http\Controllers\CodigoFuenteController;
use App\Models\BloqueoExterno;
use App\Models\FuenteIcal;
use App\Models\Huesped;
use App\Models\Propiedad;
use App\Models\Unidad;
use App\Models\User;
use App\Reservas\GestorReservas;
use App\Servicios\Calendario;
use App\Support\Formato;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesYPermisosSeeder;

// Lo que mantiene ligero el panel en un teléfono con mala red (docs/rendimiento-movil.md).
beforeEach(function () {
    $this->seed(RolesYPermisosSeeder::class);
    $this->propiedad = Propiedad::query()->create(['nombre' => 'Posada', 'codigo' => 'posada', 'modo' => ModoPropiedad::Nativa, 'zona_horaria' => 'UTC', 'moneda' => 'MXN', 'idioma' => 'es']);
    $this->unidad = Unidad::query()->create(['propiedad_id' => $this->propiedad->id, 'codigo' => '101', 'nombre' => 'Cuarto 101', 'capacidad' => 2]);
    $this->huesped = Huesped::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Ana']);
    $this->reservar = fn (string $llegada, string $salida) => app(GestorReservas::class)->crear($this->propiedad, [
        'unidad_id' => $this->unidad->id, 'titular_id' => $this->huesped->id, 'llegada' => $llegada, 'salida' => $salida,
        'personas' => 1, 'estado' => EstadoReserva::Confirmada->value, 'total' => 1000,
    ]);
    $this->actingAs(User::factory()->create(['es_administrador' => true, 'activo' => true, 'idioma' => 'es']));
});

describe('recursos de todas las pantallas', function () {
    it('no manda echo.js mientras no haya avisos en vivo configurados', function () {
        $this->get('/hoy')->assertOk()->assertDontSee('filament/echo.js', false)->assertSee('filament/app.js', false);
    });

    it('sí lo manda si alguien configura Echo', function () {
        config(['filament.broadcasting.echo' => ['broadcaster' => 'reverb']]);

        $this->get('/hoy')->assertOk()->assertSee('filament/echo.js', false);
    });

    it('la hoja propia lleva su propia versión en la dirección', function () {
        // Se guarda en caché un año: con la versión de Filament, un cambio en esta hoja no le llegaría a nadie.
        $version = (int) @filemtime(public_path('css/alojaflow/alojaflow.css'));

        $this->get('/hoy')->assertOk()->assertSee('css/alojaflow/alojaflow.css?v='.$version, false);
    });

    it('no adelanta la fuente: compite con la hoja de estilos, y sin esa hoja no se ve nada', function () {
        $this->get('/hoy')->assertOk()->assertDontSee('rel="preload"', false)->assertSee("--font-family: 'Inter Variable'", false);
    });

    it('el paquete lleva el JavaScript de Livewire como archivo y el código fuente, su .htaccess', function () {
        expect(Empaquetador::RECURSOS)->toContain('public/vendor/livewire')
            ->and((new ReflectionClassConstant(CodigoFuenteController::class, 'INCLUIR'))->getValue())->toContain('public/.htaccess');
    });
});

describe('contenido antes de que arranque el JavaScript', function () {
    it('en el teléfono se ve de inmediato, salvo los formularios', function () {
        $hoja = (string) file_get_contents(resource_path('css/alojaflow.css'));
        expect($hoja)->toMatch('/@media \(max-width: 63\.99rem\) \{\s*\.fi-main-ctn:not\(:has\(form\)\) \{ display: flex; opacity: 1; \}/');

        $principal = fn (string $ruta): string => (string) strstr((string) $this->get($ruta)->assertOk()->getContent(), 'class="fi-main-ctn"');
        expect($principal('/hoy'))->not->toBe('')->not->toContain('<form')
            ->and($principal('/reservas'))->not->toContain('<form')
            ->and($principal('/reservas/create'))->toContain('<form');
    });

    it('Filament sigue ocultando el contenido como se supone en esa regla', function () {
        // Si cambia en una versión nueva, hay que revisar la regla de alojaflow.css: puede sobrar o dejar de bastar.
        expect((string) file_get_contents(base_path('vendor/filament/filament/dist/theme.css')))->toMatch('/\.fi-main-ctn\{[^}]*opacity:0/');
    });

    it('los filtros traen su fecha en el HTML y no aparecen vacíos', function () {
        $this->get('/hoy?fecha=2030-01-15')->assertOk()->assertSee('value="2030-01-15"', false);
        $this->get('/calendario?mes=2030-01')->assertOk()->assertSee('value="2030-01"', false);
        $this->get('/limpieza?fecha=2030-01-15')->assertOk()->assertSee('value="2030-01-15"', false);
    });

    it('el filtro de propiedad trae la opción elegida', function () {
        $otra = Propiedad::query()->create(['nombre' => 'Lago', 'codigo' => 'lago', 'modo' => ModoPropiedad::Nativa, 'zona_horaria' => 'UTC', 'moneda' => 'MXN', 'idioma' => 'es']);

        $this->get('/hoy?propiedad='.$otra->id)->assertOk()->assertSee('<option value="'.$otra->id.'" selected', false);
    });

    it('lo que llega por la dirección sale escapado en los filtros', function () {
        $intento = '"><script>alert(1)</script>';

        foreach (['/hoy?fecha=', '/calendario?mes=', '/limpieza?fecha='] as $ruta) {
            $this->get($ruta.urlencode($intento))->assertOk()->assertDontSee('<script>alert(1)', false);
        }
    });
});

describe('calendario', function () {
    it('sabe qué días tapa cada barra, también de un portal y si la estancia sigue en el mes siguiente', function () {
        ($this->reservar)('2030-01-10', '2030-01-13');
        ($this->reservar)('2030-01-30', '2030-02-03');
        $fuente = FuenteIcal::query()->create(['propiedad_id' => $this->propiedad->id, 'unidad_id' => $this->unidad->id, 'nombre' => 'Airbnb', 'url' => 'https://portal.example.test/a.ics']);
        BloqueoExterno::query()->create(['fuente_id' => $fuente->id, 'propiedad_id' => $this->propiedad->id, 'unidad_id' => $this->unidad->id, 'uid' => 'x', 'llegada' => '2030-01-20', 'salida' => '2030-01-22', 'primera_vez_en' => now(), 'visto_en' => now()]);

        $tapados = Calendario::mes([$this->propiedad->id], CarbonImmutable::parse('2030-01-01'))['filas'][0]['tapados'];

        expect(array_keys($tapados))->toEqualCanonicalizing([10, 11, 12, 20, 21, 30, 31]);
    });

    it('un día libre lleva a la reserva nueva y uno tapado solo recibe lo que se suelta', function () {
        ($this->reservar)('2030-01-10', '2030-01-13');
        $html = (string) $this->get('/calendario?mes=2030-01')->assertOk()->getContent();

        expect($html)->toContain('data-unidad="'.$this->unidad->id.'"')
            // El día de salida ya está libre.
            ->toContain('/reservas/create?unidad='.$this->unidad->id.'&amp;llegada=2030-01-13&amp;salida=2030-01-14')
            ->toContain('title="'.e(__('ui.calendario.nueva_en', ['unidad' => 'Cuarto 101', 'fecha' => Formato::fechaCorta(CarbonImmutable::parse('2030-01-13'))])).'"')
            ->not->toContain('llegada=2030-01-10&amp;')
            ->not->toContain('llegada=2030-01-12&amp;')
            ->toMatch('/<div class="af-cal-dia" style="grid-column: \d+" data-fecha="2030-01-10"><\/div>/')
            // La barra se puede arrastrar.
            ->toMatch('/draggable="true"\s+data-reserva="\d+"/');
    });

    it('arrastrar se atiende una sola vez y no en cada celda', function () {
        $html = (string) $this->get('/calendario?mes=2030-01')->assertOk()->getContent();

        expect(substr_count($html, 'x-on:drop'))->toBe(1)
            ->and(substr_count($html, 'x-on:dragover'))->toBe(1)
            ->and(substr_count($html, 'x-on:dragstart'))->toBe(1);
    });

    it('una subordinada es solo de consulta', function () {
        $this->propiedad->update(['modo' => ModoPropiedad::Subordinada]);

        expect((string) $this->get('/calendario?mes=2030-01')->assertOk()->getContent())
            ->not->toContain('data-unidad=')
            ->not->toContain('data-fecha=')
            ->not->toContain('/reservas/create?unidad=')
            ->toMatch('/<div class="af-cal-dia" style="grid-column: 2"><\/div>/');
    });

    it('una unidad más pesa poco: entre celda y celda no hay comentarios de Livewire ni espacios', function () {
        $peso = fn (): int => strlen((string) $this->get('/calendario?mes=2030-01')->assertOk()->getContent());
        $antes = $peso();
        Unidad::query()->create(['propiedad_id' => $this->propiedad->id, 'codigo' => '102', 'nombre' => 'Cuarto 102', 'capacidad' => 2]);

        // 31 días con su enlace. Antes eran más de 20 KB por unidad, y un hotel con 40 unidades pasaba de 1.4 MB de HTML.
        expect($peso() - $antes)->toBeLessThan(10 * 1024);
    });
});

describe('public/.htaccess', function () {
    beforeEach(function () {
        // Cada directiva con los módulos que la rodean: fuera de su IfModule, sin ese módulo el sitio entero daría error 500.
        $this->directivas = [];
        $abiertos = [];
        foreach (file(public_path('.htaccess'), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linea) {
            $linea = trim($linea);
            if (str_starts_with($linea, '#')) {
                continue;
            }
            if (preg_match('/^<IfModule (\S+)>$/', $linea, $m)) {
                $abiertos[] = $m[1];
            } elseif ($linea === '</IfModule>') {
                array_pop($abiertos);
            } else {
                $this->directivas[] = ['nombre' => strtok($linea, ' '), 'linea' => $linea, 'modulos' => $abiertos];
            }
        }
    });

    it('cada directiva está protegida por su módulo', function () {
        $requiere = ['Rewrite' => 'mod_rewrite.c', 'Header' => 'mod_headers.c', 'AddOutputFilterByType' => 'mod_filter.c'];
        foreach ($this->directivas as $directiva) {
            foreach ($requiere as $prefijo => $modulo) {
                if (str_starts_with($directiva['nombre'], $prefijo)) {
                    expect($directiva['modulos'])->toContain($modulo);
                }
            }
        }
    });

    it('solo lleva lo que un .htaccess admite', function () {
        // Estas solo valen en la configuración del servidor: en un .htaccess, error 500. Y mod_expires pide otra autorización.
        foreach ($this->directivas as $directiva) {
            expect($directiva['nombre'])->not->toBeIn(['DeflateCompressionLevel', 'DeflateMemLevel', 'DeflateWindowSize', 'BrotliCompressionQuality', 'BrotliCompressionWindow', 'ExpiresActive', 'ExpiresByType', 'ExpiresDefault']);
        }
    });

    it('comprime lo que es texto', function () {
        $filtros = array_values(array_filter($this->directivas, fn (array $d): bool => $d['nombre'] === 'AddOutputFilterByType'));
        $deflate = collect($filtros)->first(fn (array $d): bool => str_contains($d['linea'], 'DEFLATE'));

        expect($deflate)->not->toBeNull()
            ->and($deflate['modulos'])->toContain('mod_deflate.c');
        foreach (['text/html', 'text/css', 'text/javascript', 'application/javascript', 'application/json', 'image/svg+xml'] as $tipo) {
            expect($deflate['linea'])->toContain($tipo);
        }
    });

    it('la caché larga es solo para lo que lleva versión', function () {
        $htaccess = (string) file_get_contents(public_path('.htaccess'));

        expect($htaccess)->toContain('RewriteCond %{QUERY_STRING} (^|&)(v|id)=')
            ->toContain('Header set Cache-Control "public, max-age=31536000, immutable" env=ALOJAFLOW_CON_VERSION')
            // Antes que nada de lo que caiga en index.php: una página jamás debe llevar esa cabecera.
            ->and(strpos($htaccess, 'RewriteRule ^ index.php [L]'))->toBeLessThan(strpos($htaccess, 'E=ALOJAFLOW_CON_VERSION'));
    });
});
