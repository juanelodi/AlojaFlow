<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\MejoraContinua;

use App\Copias\CopiaExterna;
use App\Enums\EstadoEjecucion;
use App\Enums\EstadoEnvio;
use App\Enums\EstadoFuenteIcal;
use App\Enums\EstadoReserva;
use App\Enums\ModoPropiedad;
use App\Models\Actualizacion;
use App\Models\BloqueoExterno;
use App\Models\EjecucionSincronizacion;
use App\Models\Envio;
use App\Models\FuenteIcal;
use App\Models\Grupo;
use App\Models\PagoEnLinea;
use App\Models\Principal;
use App\Models\Propiedad;
use App\Models\Reserva;
use App\Models\Unidad;
use App\Models\User;
use App\Models\Webhook;
use App\Modulos\Modulo;
use App\Modulos\Modulos;
use App\Motor\Pasarelas;
use App\Programador\TareasPorVisita;
use App\Support\Ajustes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * El latido diario (D-150): cómo está la instalación y cómo se usa, en números. Nada de huéspedes, ni
 * importes, ni nombres. Es exactamente lo que se ve en «Ver lo que se envía». Cada sección se arma por su
 * lado: si una consulta falla, esa sección no va y las demás sí.
 */
final class ArmadorLatido
{
    public const AJUSTE_SERVIDOR_WEB = 'mejora.servidor_web';

    /** @var list<int>|null */
    private ?array $propiedades = null;

    /**
     * @return array<string, mixed>
     */
    public function armar(): array
    {
        $latido = [
            'esquema' => 1,
            'version' => (string) config('alojaflow.version'),
            'generado_en' => now('UTC')->toIso8601String(),
            'entorno' => $this->seccion(fn (): array => $this->entorno()),
            'uso' => $this->seccion(fn (): array => $this->uso()),
            'salud' => $this->seccion(fn (): array => $this->salud()),
        ];
        $alojada = Permiso::alojada();
        if ($alojada !== null) {
            $latido['alojada'] = $alojada;
        }
        $identificacion = Permiso::identificacion();
        if ($identificacion !== null) {
            $latido['identificacion'] = $identificacion;
        }

        return $latido;
    }

    /**
     * @param  callable(): array<string, mixed>  $armar
     * @return array<string, mixed>
     */
    private function seccion(callable $armar): array
    {
        try {
            return $armar();
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @template T
     *
     * @param  callable(): T  $consulta
     * @return T|null
     */
    private function intentar(callable $consulta): mixed
    {
        try {
            return $consulta();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function entorno(): array
    {
        $extensiones = [];
        foreach (['intl', 'zip', 'gd', 'sodium', 'curl', 'exif', 'mbstring', 'fileinfo', 'openssl', 'pdo_mysql'] as $extension) {
            $extensiones[$extension] = extension_loaded($extension);
        }
        $version = (string) (DB::selectOne('SELECT VERSION() AS v')->v ?? '');
        $latidoCron = TareasPorVisita::ultimoLatido();
        $libre = function_exists('disk_free_space') ? @disk_free_space(base_path()) : false;
        // Lo que un hosting puede negar va aparte: si falla, solo falta ese dato.
        $tamanoBase = $this->intentar(fn (): float => (float) DB::table('information_schema.tables')->where('table_schema', DB::getDatabaseName())
            ->sum(DB::raw('data_length + index_length')));

        return [
            'php' => PHP_VERSION,
            'extensiones' => $extensiones,
            'limites' => [
                'memory_limit' => (string) ini_get('memory_limit'),
                'max_execution_time' => (int) ini_get('max_execution_time'),
                'upload_max_filesize' => (string) ini_get('upload_max_filesize'),
                'post_max_size' => (string) ini_get('post_max_size'),
            ],
            'base_datos' => [
                'motor' => str_contains(strtolower($version), 'mariadb') ? 'mariadb' : 'mysql',
                'version' => preg_match('/^\d+(?:\.\d+){0,2}/', $version, $coincide) === 1 ? $coincide[0] : '',
            ],
            'sistema' => PHP_OS_FAMILY,
            'servidor_web' => (string) Ajustes::obtener(self::AJUSTE_SERVIDOR_WEB, ''),
            'https' => str_starts_with((string) config('app.url'), 'https://'),
            'zona_horaria' => (string) config('app.timezone'),
            'idioma' => (string) config('app.locale'),
            'instalada_con' => is_dir(base_path('.git')) ? 'git' : 'paquete',
            'programador' => [
                'modo' => TareasPorVisita::cronActivo() ? 'cron' : 'visitas',
                'ultima_ejecucion_hace_min' => $latidoCron === null ? null : (int) $latidoCron->diffInMinutes(now(), true),
            ],
            'cola' => [
                'pendientes' => $this->intentar(fn (): int => DB::table('jobs')->count()),
                'fallidas' => $this->intentar(fn (): int => DB::table('failed_jobs')->count()),
            ],
            'disco_libre_mb' => $libre === false ? null : (int) floor($libre / 1048576),
            'tamano_base_mb' => $tamanoBase === null ? null : (int) round($tamanoBase / 1048576),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function uso(): array
    {
        $ids = $this->propiedades();
        $propiedades = Propiedad::query()->whereIn('id', $ids)->get();
        $paises = [];
        foreach ($propiedades as $propiedad) {
            $paises[$propiedad->paquete_pais->value] = ($paises[$propiedad->paquete_pais->value] ?? 0) + 1;
        }
        $desde = now()->subDays(30);
        $motor = array_map(fn (string $idioma): string => __('motor.origen', [], $idioma), (array) config('alojaflow.idiomas'));
        $reservas = Reserva::query()->whereIn('propiedad_id', $ids)->where('creado_en', '>=', $desde);
        $pasarelas = [];
        foreach ($propiedades->where('modo', ModoPropiedad::Nativa) as $propiedad) {
            foreach (array_keys((new Pasarelas($propiedad))->disponibles()) as $pasarela) {
                $pasarelas[$pasarela] = ($pasarelas[$pasarela] ?? 0) + 1;
            }
        }

        return [
            'propiedades' => [
                'total' => count($ids),
                'independientes' => $propiedades->where('modo', ModoPropiedad::Nativa)->count(),
                'subordinadas' => $propiedades->where('modo', ModoPropiedad::Subordinada)->count(),
                'principal' => Principal::query()->whereIn('propiedad_id', $ids)->get()
                    ->countBy(fn (Principal $principal): string => $principal->tipo->value)->all(),
                'paises' => $paises,
                'demo' => Propiedad::query()->where('es_demo', true)->count(),
            ],
            'unidades' => ['total' => Unidad::query()->whereIn('propiedad_id', $ids)->count()],
            'usuarios' => [
                'activos' => User::query()->where('activo', true)->count(),
                'activos_30d' => User::query()->where('activo', true)->where('ultimo_acceso_en', '>=', $desde)->count(),
                'con_dos_pasos' => User::query()->where('activo', true)->whereNotNull('app_authentication_secret')->count(),
                'idiomas' => User::query()->where('activo', true)->get(['idioma'])->countBy('idioma')->all(),
            ],
            'modulos' => array_map(fn (Modulo $modulo): string => $modulo->value, Modulos::prendidos()),
            // Creadas en los últimos 30 días, por origen y en número exacto (decisión del 2026-11-08).
            'reservas_30d' => [
                'panel' => (clone $reservas)->where('sincronizada', false)->where(fn ($q) => $q->whereNull('origen_texto')->orWhereNotIn('origen_texto', $motor))->count(),
                'motor' => (clone $reservas)->where('sincronizada', false)->whereIn('origen_texto', $motor)->count(),
                'sincronizadas' => (clone $reservas)->where('sincronizada', true)->count(),
                'canceladas' => (clone $reservas)->where('estado', EstadoReserva::Cancelada->value)->count(),
                'ical' => BloqueoExterno::query()->whereIn('propiedad_id', $ids)->where('primera_vez_en', '>=', $desde)->count(),
            ],
            'integraciones' => [
                'pasarelas' => $pasarelas,
                'correo_smtp' => filled(Ajustes::obtener('correo.host')),
                'fuentes_ical' => FuenteIcal::query()->whereIn('propiedad_id', $ids)->count(),
                'webhooks' => Webhook::query()->whereIn('propiedad_id', $ids)->count(),
            ],
            'pantallas_30d' => DB::table('mejora_pantallas')->where('fecha', '>=', $desde->toDateString())
                ->groupBy('pantalla')->selectRaw('pantalla, sum(visitas) as total')->orderByDesc('total')->limit(150)
                ->pluck('total', 'pantalla')->map(fn (mixed $total): int => (int) $total)->all(),
            'funciones_30d' => [
                'grupos' => Grupo::query()->whereIn('propiedad_id', $ids)->where('creado_en', '>=', $desde)->count(),
                'pagos_en_linea' => PagoEnLinea::query()->whereIn('propiedad_id', $ids)->where('creado_en', '>=', $desde)->count(),
                'mensajes_enviados' => Envio::query()->whereIn('propiedad_id', $ids)->where('estado', EstadoEnvio::Enviado->value)->where('creado_en', '>=', $desde)->count(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function salud(): array
    {
        $copia = (array) Ajustes::obtener('copias.ultima', []);
        $sincronizaciones = EjecucionSincronizacion::query()->where('iniciada_en', '>=', now()->subDays(7));
        $ultimaBuena = EjecucionSincronizacion::query()->where('estado', EstadoEjecucion::Correcta->value)->max('terminada_en');
        $duraciones = EjecucionSincronizacion::query()->where('estado', EstadoEjecucion::Correcta->value)->whereNotNull('terminada_en')
            ->latest('id')->limit(20)->get(['iniciada_en', 'terminada_en'])
            ->map(fn (EjecucionSincronizacion $e): int => (int) $e->iniciada_en->diffInSeconds($e->terminada_en, true));
        $actualizacion = Actualizacion::query()->latest('id')->first();

        return [
            'copias' => [
                'ultima_hace_h' => isset($copia['en']) ? (int) Carbon::parse((string) $copia['en'])->diffInHours(now(), true) : null,
                'origen' => isset($copia['origen']) ? (string) $copia['origen'] : null,
                // Solo si está configurada y si la última salió: nunca a dónde.
                'externa' => app(CopiaExterna::class)->configurada() ? ((app(CopiaExterna::class)->ultima()['ok'] ?? null) === true ? 'ok' : 'falla') : null,
            ],
            'sincronizacion' => [
                'principales' => Principal::query()->whereIn('propiedad_id', $this->propiedades())->count(),
                'ultima_ok_hace_min' => $ultimaBuena === null ? null : (int) Carbon::parse((string) $ultimaBuena)->diffInMinutes(now(), true),
                'fallos_7d' => (clone $sincronizaciones)->where('estado', EstadoEjecucion::Fallida->value)->get(['codigo_error'])
                    ->countBy(fn (EjecucionSincronizacion $e): string => $e->codigo_error ?? 'otro')->all(),
                'duracion_s' => $duraciones->isEmpty() ? null : (int) round((float) $duraciones->avg()),
            ],
            'actualizacion' => $actualizacion === null ? null : [
                'de' => $actualizacion->version_anterior,
                'a' => $actualizacion->version_nueva,
                'estado' => $actualizacion->estado->value,
                'en' => $actualizacion->terminada_en?->toDateString(),
            ],
            'ical' => ['fuentes_con_error' => FuenteIcal::query()->whereIn('propiedad_id', $this->propiedades())->where('estado', EstadoFuenteIcal::Error->value)->count()],
            'correos' => ['fallidos_7d' => Envio::query()->whereIn('propiedad_id', $this->propiedades())->where('estado', EstadoEnvio::Fallido->value)->where('creado_en', '>=', now()->subDays(7))->count()],
            'errores_sin_enviar' => (int) DB::table('mejora_errores')->sum('veces'),
        ];
    }

    /**
     * Las propiedades que cuentan: todas menos la de demostración.
     *
     * @return list<int>
     */
    private function propiedades(): array
    {
        return $this->propiedades ??= Propiedad::query()->where('es_demo', false)->pluck('id')->map(fn (mixed $id): int => (int) $id)->values()->all();
    }
}
