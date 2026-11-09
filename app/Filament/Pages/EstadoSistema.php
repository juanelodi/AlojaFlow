<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Comunicacion\AjustesCorreo;
use App\Demo\ModoDemo;
use App\Instalacion\Instancia;
use App\Instalacion\VersionPhp;
use App\Models\User;
use App\Programador\TareasPorVisita;
use App\Seguridad\Acceso;
use App\Support\Ajustes;
use App\Support\Formato;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Number;
use Throwable;

/**
 * El mismo análisis del instalador, ya con el sistema en marcha: para ver de un vistazo qué falta
 * y poder dar soporte. Solo el administrador.
 */
class EstadoSistema extends Page
{
    protected string $view = 'filament.pages.estado-sistema';

    protected static ?string $slug = 'estado-del-sistema';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHeart;

    protected static ?int $navigationSort = 60;

    public static function canAccess(): bool
    {
        return (bool) Acceso::usuarioActual()?->es_administrador && ! ModoDemo::activo();
    }

    public static function getNavigationGroup(): ?string
    {
        return __('ui.nav.sistema');
    }

    public static function getNavigationLabel(): string
    {
        return __('ui.estado.titulo');
    }

    public function getTitle(): string|Htmlable
    {
        return __('ui.estado.titulo');
    }

    public function comandoCron(): string
    {
        return TareasPorVisita::comandoCron();
    }

    /**
     * En el servicio alojado (D-167), el programador, el cron y el PHP de consola los lleva AlojaFlow App.
     */
    public function muestraServidor(): bool
    {
        return ! Instancia::activa();
    }

    public function cronActivo(): bool
    {
        return TareasPorVisita::cronActivo();
    }

    /**
     * @return list<array{titulo: string, valor: string, estado: string, ayuda: string|null}>
     */
    public function filas(): array
    {
        $filas = [];
        $version = (string) config('alojaflow.version');
        $filas[] = $this->fila('version', $version, 'ok');
        $corregida = VersionPhp::falta(PHP_VERSION);
        $filas[] = $corregida === null
            ? $this->fila('php', PHP_VERSION, 'ok')
            : $this->fila('php', PHP_VERSION, 'error', __('ui.estado.php_sin_parche', ['version' => $corregida]));

        try {
            $base = (string) DB::selectOne('SELECT VERSION() AS v')->v;
        } catch (Throwable) {
            $base = '—';
        }
        $filas[] = $this->fila('base', $base, 'ok');

        if ($this->muestraServidor()) {
            $latido = TareasPorVisita::ultimoLatido();
            $filas[] = $this->cronActivo()
                ? $this->fila('programador', __('ui.estado.programador_ok', ['cuando' => $latido?->locale(app()->getLocale())->diffForHumans() ?? '—']), 'ok')
                : $this->fila('programador', __('ui.estado.programador_no'), 'aviso', __('ui.estado.programador_ayuda'));
        }

        $ultima = (array) Ajustes::obtener('copias.ultima', []);
        if (isset($ultima['en'])) {
            $cuando = Carbon::parse((string) $ultima['en']);
            $filas[] = $this->fila('copias', Formato::momento($cuando), $cuando->lt(now()->subDays(7)) ? 'aviso' : 'ok', $cuando->lt(now()->subDays(7)) ? __('ui.estado.copias_vieja') : null);
        } else {
            $filas[] = $this->fila('copias', __('ui.estado.copias_ninguna'), 'aviso', __('ui.estado.copias_vieja'));
        }

        $correo = Ajustes::obtener('correo.host');
        $filas[] = match (true) {
            filled($correo) => $this->fila('correo', (string) $correo, 'ok'),
            app(AjustesCorreo::class)->dePlataforma() => $this->fila('correo', __('ui.estado.correo_plataforma'), 'ok'),
            default => $this->fila('correo', __('ui.estado.correo_no'), 'aviso', __('ui.estado.correo_ayuda')),
        };

        $filas[] = config('app.debug')
            ? $this->fila('depuracion', __('ui.estado.depuracion_si'), 'error', __('ui.estado.depuracion_ayuda'))
            : $this->fila('depuracion', __('ui.estado.depuracion_no'), 'ok');

        $https = str_starts_with((string) request()->getScheme(), 'https');
        $filas[] = $https
            ? $this->fila('https', __('ui.estado.https_si'), 'ok')
            : $this->fila('https', __('ui.estado.https_no'), 'aviso', __('ui.estado.https_ayuda'));

        $sinDosPasos = User::query()->where('es_administrador', true)->where('activo', true)->whereNull('app_authentication_secret')->count();
        $filas[] = $sinDosPasos === 0
            ? $this->fila('dos_pasos', __('ui.estado.dos_pasos_ok'), 'ok')
            : $this->fila('dos_pasos', __('ui.estado.dos_pasos_faltan', ['cuantos' => $sinDosPasos]), 'aviso', __('ui.estado.dos_pasos_ayuda'));

        $libre = function_exists('disk_free_space') ? @disk_free_space(base_path()) : false;
        $filas[] = $libre === false
            ? $this->fila('disco', '—', 'aviso')
            : $this->fila('disco', (string) Number::fileSize($libre, 1), $libre < 300 * 1024 * 1024 ? 'aviso' : 'ok', $libre < 300 * 1024 * 1024 ? __('ui.estado.disco_ayuda') : null);

        $filas[] = $this->fila('memoria', (string) ini_get('memory_limit'), 'ok');
        $filas[] = $this->fila('tiempo', __('ui.estado.tiempo_valor', ['segundos' => (string) ini_get('max_execution_time')]), 'ok');
        $filas[] = $this->fila('subida', ini_get('upload_max_filesize').' / '.ini_get('post_max_size'), 'ok');

        return $filas;
    }

    /**
     * @return array{titulo: string, valor: string, estado: string, ayuda: string|null}
     */
    private function fila(string $clave, string $valor, string $estado, ?string $ayuda = null): array
    {
        return ['titulo' => __('ui.estado.'.$clave), 'valor' => $valor, 'estado' => $estado, 'ayuda' => $ayuda];
    }
}
