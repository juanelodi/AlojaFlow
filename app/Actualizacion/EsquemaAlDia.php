<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Actualizacion;

use App\Copias\GestorCopias;
use App\Instalacion\CachesArranque;
use App\Instalacion\Instalador;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Throwable;

/**
 * Si alguien subió los archivos de una versión a mano, la base se pone al día sola (D-089). La marca es una versión en un archivo: mirarla no toca la
 * base, así que revisar en cada petición no cuesta nada. La versión se lee del archivo de configuración y no de config(): en producción la
 * configuración está en caché, y seguiría diciendo la versión anterior.
 */
final class EsquemaAlDia
{
    private const MARCA = 'app/private/esquema-version.txt';

    private const INTENTO = 'app/private/esquema-intento.txt';

    private const SEGUNDOS_ENTRE_INTENTOS = 60;

    public function hayQueRevisar(): bool
    {
        if (! is_file(storage_path(Instalador::MARCA))) {
            return false;
        }

        return trim((string) @file_get_contents(storage_path(self::MARCA))) !== $this->versionDeLosArchivos();
    }

    /**
     * Lo llaman también el instalador y el actualizador, que ya dejaron la base al día.
     */
    public function marcarAlDia(): void
    {
        File::ensureDirectoryExists(dirname(storage_path(self::MARCA)));
        File::put(storage_path(self::MARCA), $this->versionDeLosArchivos());
    }

    /**
     * Las rutas y la configuración en caché son de la versión anterior: con ellas no se atiende nada.
     *
     * @return bool si había, y por tanto esta petición arrancó con ellas
     */
    public function quitarCachesViejas(): bool
    {
        // En una instancia (D-162) la configuración guardada está en su carpeta, no en la del código.
        $configuracion = app()->getCachedConfigPath();
        if (! is_file($configuracion)) {
            return false;
        }
        CachesArranque::quitarActuales();

        // Si no se pudo borrar, redirigir no serviría de nada: volvería a pasar por aquí.
        return ! file_exists($configuracion);
    }

    /**
     * @return list<string>
     */
    public function pendientes(): array
    {
        /** @var Migrator $migrador */
        $migrador = app('migrator');
        if (! $migrador->repositoryExists()) {
            return [];
        }
        $archivos = $migrador->getMigrationFiles([database_path('migrations')]);

        return array_values(array_diff(array_keys($archivos), $migrador->getRepository()->getRan()));
    }

    /**
     * Lo primero que corre schedule:run (D-171), como hace cada petición: si el código cambió sin pasar por el
     * navegador, las tareas que siguen ya encuentran la base al día. Si este proceso arrancó con las cachés de la
     * versión anterior, solo las quita: la ronda siguiente migra.
     */
    public function antesDeLasTareas(): void
    {
        if (! $this->hayQueRevisar() || $this->quitarCachesViejas()) {
            return;
        }
        $this->ponerAlDia();
    }

    /**
     * Otra petición al mismo tiempo espera aquí: no se migra dos veces.
     */
    public function ponerAlDia(): void
    {
        File::ensureDirectoryExists(dirname(storage_path(self::MARCA)));
        $candado = @fopen(storage_path(self::MARCA).'.lock', 'c');
        if ($candado === false) {
            throw new RuntimeException('No se puede escribir en storage/app/private.');
        }
        try {
            flock($candado, LOCK_EX);
            if (! $this->hayQueRevisar()) {
                return;
            }
            if ($this->pendientes() !== []) {
                $this->migrar();
            }
            // También si ya no quedaba nada que migrar: la próxima versión que suban necesita su copia.
            File::delete(storage_path(self::INTENTO));
            $this->marcarAlDia();
            $this->limpiarVistas();
            CachesArranque::rehacer();
        } finally {
            flock($candado, LOCK_UN);
            fclose($candado);
        }
    }

    /**
     * Si falla, cada visita lo intentaría otra vez: la copia llenaría el disco y la migración ocuparía el servidor. La copia se hace una sola vez, y
     * otro intento, un minuto después.
     */
    private function migrar(): void
    {
        $intento = storage_path(self::INTENTO);
        if (is_file($intento)) {
            if (time() - (int) filemtime($intento) < self::SEGUNDOS_ENTRE_INTENTOS) {
                throw new RuntimeException('La migración acaba de fallar: se reintenta en un minuto.');
            }
        } else {
            $this->copiaPrevia();
        }
        File::put($intento, $this->versionDeLosArchivos());
        @set_time_limit(0);
        if (Artisan::call('migrate', ['--force' => true]) !== 0) {
            throw new RuntimeException(Artisan::output());
        }
    }

    /**
     * Sin copia también se migra: quien subió los archivos ya los subió, y sin migrar la aplicación no abre.
     */
    private function copiaPrevia(): void
    {
        try {
            app(GestorCopias::class)->crear('antes-de-migrar');
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Las vistas compiladas de la versión anterior. Si no se pueden borrar, la base ya está al día: no es para enseñar un error.
     */
    private function limpiarVistas(): void
    {
        try {
            Artisan::call('view:clear');
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function versionDeLosArchivos(): string
    {
        $texto = (string) @file_get_contents(config_path('alojaflow.php'));

        return preg_match("/'version'\s*=>\s*'([^']+)'/", $texto, $encontrado) === 1 ? $encontrado[1] : (string) config('alojaflow.version');
    }
}
