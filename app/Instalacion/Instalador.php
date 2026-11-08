<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Instalacion;

use App\Actualizacion\EsquemaAlDia;
use App\Demo\GeneradorDemo;
use App\MejoraContinua\Permiso;
use App\Models\User;
use App\Programador\TareasPorVisita;
use App\Support\Ajustes;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use SensitiveParameter;

/**
 * Lo que el instalador web (instalador/instalar.php) hace dentro de la aplicación, una vez que los
 * archivos y el .env ya están en su sitio.
 */
class Instalador
{
    /** Marca de instalación terminada: el instalador no vuelve a actuar sobre esta carpeta. */
    public const MARCA = 'app/private/instalado.json';

    public function migrar(): void
    {
        foreach ([['migrate', ['--force' => true]], ['db:seed', ['--force' => true]]] as [$comando, $opciones]) {
            if (Artisan::call($comando, $opciones) !== 0) {
                throw new RuntimeException(trim(Artisan::output()));
            }
        }
    }

    public function crearAdministrador(string $nombre, string $correo, #[SensitiveParameter] string $contrasena, string $idioma = 'es'): User
    {
        $validacion = Validator::make(
            ['nombre' => $nombre, 'correo' => $correo, 'contrasena' => $contrasena],
            ['nombre' => ['required', 'max:255'], 'correo' => ['required', 'email', 'max:255'], 'contrasena' => ['required', 'min:12', 'max:200']],
        );
        if ($validacion->fails()) {
            throw new ValidationException($validacion);
        }

        return User::query()->updateOrCreate(['email' => $correo], [
            'name' => $nombre,
            'password' => $contrasena,
            'idioma' => $idioma,
            'activo' => true,
            'es_administrador' => true,
            'es_superadministrador' => true,
        ]);
    }

    /**
     * La casilla de mejora continua del instalador (D-150), que explica qué se manda y viene marcada.
     */
    public function decidirMejoraContinua(bool $permiso): void
    {
        Permiso::guardar($permiso);
    }

    public function crearDemo(): void
    {
        if (GeneradorDemo::existente() === null) {
            app(GeneradorDemo::class)->crear();
        }
    }

    /**
     * @param  string|null  $phpConsola  el PHP de consola que encontró el instalador, para el comando del cron
     * @return string el enlace con el que quien instaló entra sin volver a escribir su contraseña
     */
    public function terminar(?string $phpConsola = null): string
    {
        if (filled($phpConsola)) {
            Ajustes::guardar(TareasPorVisita::AJUSTE_PHP_CONSOLA, $phpConsola);
        }
        File::ensureDirectoryExists(dirname(storage_path(self::MARCA)));
        File::put(storage_path(self::MARCA), (string) json_encode([
            'version' => config('alojaflow.version'),
            'instalado_en' => now('UTC')->toIso8601String(),
        ]));
        app(EsquemaAlDia::class)->marcarAlDia();
        CachesArranque::rehacer();

        $super = User::query()->where('es_superadministrador', true)->where('activo', true)->orderBy('id')->first();

        return $super === null ? '' : EnlaceEntrada::crear($super);
    }
}
