<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Instalacion;

use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * En un hosting compartido, armar la configuración, las rutas y los componentes en cada visita es lo
 * que más tarda. Se dejan listos al instalar, al actualizar y al restaurar. Si alguno falla, la
 * aplicación funciona igual, solo que más lenta. Las vistas se compilan solas la primera vez.
 */
final class CachesArranque
{
    private const COMANDOS = ['config:cache', 'route:cache', 'event:cache', 'filament:optimize'];

    private const ARCHIVOS = ['packages.php', 'services.php', 'config.php', 'routes-v7.php', 'events.php'];

    /** En una instancia (D-162), las que escriben en el código que comparten todas: las genera quien instala la versión. */
    private const SOLO_DEL_CODIGO = ['filament:optimize'];

    public static function rehacer(): void
    {
        // En desarrollo y en las pruebas estorban: cada cambio tendría que borrarlas.
        if (! app()->isProduction()) {
            return;
        }
        $comandos = Instancia::activa() ? array_diff(self::COMANDOS, self::SOLO_DEL_CODIGO) : self::COMANDOS;
        self::sinCambiarLaAplicacion(function () use ($comandos): void {
            foreach ($comandos as $comando) {
                try {
                    Artisan::call($comando);
                } catch (Throwable $e) {
                    Log::warning('Cachés de arranque: '.$comando.': '.$e->getMessage());
                }
            }
        });
    }

    /**
     * Las de esta aplicación: en una instancia, solo las suyas (configuración, rutas y eventos); las del código las
     * comparten todas las instancias de la versión.
     */
    public static function quitarActuales(): void
    {
        if (! Instancia::activa()) {
            self::quitar(base_path());

            return;
        }
        $aplicacion = app();
        foreach ([$aplicacion->getCachedConfigPath(), $aplicacion->getCachedRoutesPath(), $aplicacion->getCachedEventsPath()] as $archivo) {
            @unlink($archivo);
        }
    }

    /**
     * Sin bootstrap/cache/*.php el siguiente arranque los rehace con el código que haya.
     */
    public static function quitar(string $raiz): void
    {
        foreach (self::ARCHIVOS as $archivo) {
            @unlink($raiz.'/bootstrap/cache/'.$archivo);
        }
        // Componentes e iconos de la versión anterior: el código nuevo no debe arrancar con ellos.
        File::deleteDirectory($raiz.'/bootstrap/cache/filament');
        @unlink($raiz.'/bootstrap/cache/blade-icons.php');
    }

    /**
     * `config:cache` arma una aplicación nueva para leer la configuración, y esa se queda como la actual: lo que siguiera en la
     * petición usaría otra sesión. Así se perdía el aviso de «AlojaFlow se actualizó», y solo en producción.
     */
    public static function sinCambiarLaAplicacion(callable $trabajo): void
    {
        $aplicacion = app();
        try {
            $trabajo();
        } finally {
            Container::setInstance($aplicacion);
            Facade::clearResolvedInstances();
            Facade::setFacadeApplication($aplicacion);
            Model::setConnectionResolver($aplicacion->make('db'));
            Model::setEventDispatcher($aplicacion->make('events'));
        }
    }
}
