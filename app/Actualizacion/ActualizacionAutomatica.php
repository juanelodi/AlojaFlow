<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Actualizacion;

use App\Demo\ModoDemo;
use App\Models\Propiedad;
use App\Models\RegistroAuditoria;
use App\Programador\TareasPorVisita;
use App\Support\Ajustes;
use DateTimeZone;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Actualizaciones sin nadie delante (D-151), cada hora con la tarea programada:
 *
 * - **Automáticas:** si el administrador las prende, una versión nueva se instala a las 3:00, hora de la
 *   instalación. Apagadas al empezar.
 * - **Urgentes:** cuando el equipo de AlojaFlow corrige una vulnerabilidad, ordena desde dev.alojaflow.com
 *   pasar a esa versión, y se instala en la siguiente hora. Prendidas al empezar; se pueden apagar.
 *
 * En los dos casos solo se instala una versión más nueva que la instalada, bajada de donde se bajan siempre
 * (GitHub) y con la firma de AlojaFlow: quien controlara dev.alojaflow.com no podría instalar otra cosa.
 * Consultar la orden urgente no manda ningún dato de la instalación.
 */
class ActualizacionAutomatica
{
    public const AJUSTE_AUTOMATICAS = 'actualizaciones.automaticas';

    public const AJUSTE_URGENTES = 'actualizaciones.urgentes';

    public const AJUSTE_ZONA = 'actualizaciones.zona';

    /** La última orden urgente que se supo: versión, mensaje y cuándo se consultó. */
    public const AJUSTE_ORDEN = 'actualizaciones.orden_urgente';

    /** Cómo salió el último intento: versión, si fue urgente, si salió bien, cuándo y el error. */
    public const AJUSTE_ULTIMA = 'actualizaciones.automatica_ultima';

    public const HORA = 3;

    private const VERSION = '/^\d{1,3}\.\d{1,3}\.\d{1,3}$/';

    /** Una orden urgente que no se pudo volver a consultar en estas horas ya no se instala sola: pudo detenerse. */
    private const HORAS_ORDEN_VIGENTE = 6;

    /** Tras un intento fallido con una versión, no se vuelve a intentar con ella en estas horas. */
    private const HORAS_ENTRE_INTENTOS = 20;

    public function __construct(
        private readonly Actualizador $actualizador,
        private readonly UltimaVersion $ultimaVersion,
        private readonly InstaladorVersion $instalador,
    ) {
    }

    public function automaticasActivas(): bool
    {
        return Ajustes::obtener(self::AJUSTE_AUTOMATICAS) === true;
    }

    public function urgentesActivas(): bool
    {
        return Ajustes::obtener(self::AJUSTE_URGENTES, true) !== false;
    }

    public function guardar(bool $automaticas, bool $urgentes, string $zona): void
    {
        Ajustes::guardar(self::AJUSTE_AUTOMATICAS, $automaticas);
        Ajustes::guardar(self::AJUSTE_URGENTES, $urgentes);
        Ajustes::guardar(self::AJUSTE_ZONA, in_array($zona, DateTimeZone::listIdentifiers(), true) ? $zona : $this->zona());
    }

    /**
     * La hora de la instalación: la que se eligió, y si no, la de su primera propiedad.
     */
    public function zona(): string
    {
        $zona = Ajustes::obtener(self::AJUSTE_ZONA);
        if (is_string($zona) && in_array($zona, DateTimeZone::listIdentifiers(), true)) {
            return $zona;
        }
        $propiedad = Propiedad::query()->where('es_demo', false)->orderBy('id')->value('zona_horaria');

        return is_string($propiedad) && in_array($propiedad, DateTimeZone::listIdentifiers(), true) ? $propiedad : (string) config('app.timezone');
    }

    /**
     * Si sin nadie delante se puede instalar: hace falta la tarea programada (cron), porque instalar tarda
     * más de lo que dura una visita.
     */
    public function puedeInstalarSola(): bool
    {
        return TareasPorVisita::cronActivo() && ! $this->actualizador->bloqueadaPorGit() && ! $this->actualizador->bloqueadaPorInstancia() && ! ModoDemo::activo();
    }

    /**
     * @return array{version: string, mensaje: string}|null la orden urgente, si pide una versión más nueva que la instalada
     */
    public function urgentePendiente(): ?array
    {
        $orden = (array) Ajustes::obtener(self::AJUSTE_ORDEN, []);
        $version = $orden['version'] ?? null;
        $consultada = $orden['consultada_en'] ?? null;
        if (! is_string($version) || preg_match(self::VERSION, $version) !== 1 || ! is_string($consultada)
            || now()->diffInHours($consultada, true) > self::HORAS_ORDEN_VIGENTE
            || version_compare($version, $this->actualizador->versionInstalada(), '<=')) {
            return null;
        }

        return ['version' => $version, 'mensaje' => (string) ($orden['mensaje'] ?? '')];
    }

    /**
     * @return array<string, mixed>
     */
    public function ultimoIntento(): array
    {
        return (array) Ajustes::obtener(self::AJUSTE_ULTIMA, []);
    }

    /**
     * Lo de cada hora. Con $soloConsultar (sin cron) solo se entera de la orden urgente, para avisar en el panel.
     *
     * @return string qué pasó, para la consola
     */
    public function ejecutar(bool $soloConsultar = false): string
    {
        if (ModoDemo::activo()) {
            return 'demo';
        }
        if ($this->urgentesActivas()) {
            $this->consultarUrgente();
        }
        if ($soloConsultar || ! $this->puedeInstalarSola() || $this->actualizador->pendiente() !== null) {
            return 'sin_instalar';
        }

        [$version, $urgente] = $this->objetivo();
        if ($version === null) {
            return 'al_dia';
        }
        $ultimo = $this->ultimoIntento();
        if (($ultimo['version'] ?? null) === $version && ($ultimo['ok'] ?? true) === false
            && isset($ultimo['en']) && now()->diffInHours((string) $ultimo['en'], true) < self::HORAS_ENTRE_INTENTOS) {
            return 'esperando';
        }

        return $this->instalar($version, $urgente);
    }

    /**
     * @return array{0: ?string, 1: bool} la versión a instalar y si es urgente
     */
    private function objetivo(): array
    {
        if ($this->urgentesActivas() && ($urgente = $this->urgentePendiente()) !== null) {
            return [$urgente['version'], true];
        }
        if ($this->automaticasActivas() && (int) now($this->zona())->format('G') === self::HORA) {
            // A esa hora se pregunta de nuevo: lo de la consulta diaria podría tener horas.
            $this->ultimaVersion->consultar();
            $nueva = $this->ultimaVersion->disponible();
            if ($nueva !== null && preg_match(self::VERSION, $nueva['version']) === 1) {
                return [$nueva['version'], false];
            }
        }

        return [null, false];
    }

    private function instalar(string $version, bool $urgente): string
    {
        $anterior = $this->actualizador->versionInstalada();
        try {
            $this->instalador->instalar($version);
        } catch (Throwable $e) {
            Ajustes::guardar(self::AJUSTE_ULTIMA, ['version' => $version, 'urgente' => $urgente, 'ok' => false, 'en' => now('UTC')->toIso8601String(), 'error' => mb_substr($e->getMessage(), 0, 500)]);
            RegistroAuditoria::anotar('actualizacion.automatica', null, ['de' => $anterior, 'a' => $version, 'urgente' => $urgente, 'ok' => false]);
            Log::warning('No se pudo instalar sola la versión '.$version.': '.$e->getMessage());

            return 'fallo';
        }
        // Ya con los archivos nuevos en su sitio: si anotarlo falla, la versión igual quedó instalada.
        try {
            Ajustes::guardar(self::AJUSTE_ULTIMA, ['version' => $version, 'urgente' => $urgente, 'ok' => true, 'en' => now('UTC')->toIso8601String(), 'error' => null]);
            RegistroAuditoria::anotar('actualizacion.automatica', null, ['de' => $anterior, 'a' => $version, 'urgente' => $urgente, 'ok' => true]);
        } catch (Throwable $e) {
            Log::info('La versión '.$version.' se instaló sola, pero no se pudo anotar: '.$e->getMessage());
        }

        return 'instalada';
    }

    /**
     * Pregunta a dev.alojaflow.com si hay una orden urgente. Sin ficha y sin mandar nada de la instalación.
     */
    private function consultarUrgente(): void
    {
        $url = (string) config('alojaflow.actualizaciones.url_urgente');
        if ($url === '' || ! str_starts_with($url, 'https://')) {
            return;
        }
        try {
            $respuesta = Http::acceptJson()->withUserAgent('AlojaFlow (actualizaciones urgentes; +https://alojaflow.com)')
                ->connectTimeout(5)->timeout(15)->withoutRedirecting()->get($url);
        } catch (Throwable) {
            return; // Sin respuesta se conserva lo último que se supo.
        }
        $datos = $respuesta->successful() ? $respuesta->json() : null;
        if (! is_array($datos)) {
            return;
        }
        $version = $datos['version'] ?? null;
        Ajustes::guardar(self::AJUSTE_ORDEN, [
            'version' => is_string($version) && preg_match(self::VERSION, $version) === 1 ? $version : null,
            'mensaje' => is_string($datos['mensaje'] ?? null) ? mb_substr(strip_tags($datos['mensaje']), 0, 500) : '',
            'consultada_en' => now('UTC')->toIso8601String(),
        ]);
    }
}
