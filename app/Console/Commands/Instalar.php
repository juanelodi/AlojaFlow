<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Console\Commands;

use App\Instalacion\Instalador;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Lo que hace el instalador web, sin navegador (D-162): con el .env ya escrito, crea las tablas, los datos base y
 * al super administrador, y deja la marca de instalada. Lo usa AlojaFlow App para cada instancia nueva. El super
 * administrador queda con una contraseña al azar que nadie conoce: entra con el enlace de un solo uso y AlojaFlow
 * le pide crear la suya (D-164). Si ya estaba instalada no toca nada, para que quien lo llama pueda repetir el paso.
 */
class Instalar extends Command
{
    protected $signature = 'alojaflow:instalar
        {--nombre= : Nombre del super administrador}
        {--correo= : Correo del super administrador}
        {--idioma=es : Su idioma}
        {--json : Responder en JSON (para AlojaFlow App)}';

    protected $description = 'Instala AlojaFlow sin navegador: tablas, datos base y super administrador';

    public function handle(Instalador $instalador): int
    {
        if (is_file(storage_path(Instalador::MARCA))) {
            return $this->responder(['instalada' => true, 'ya_estaba' => true], __('consola.instalar.ya_estaba'));
        }
        $idioma = (string) $this->option('idioma');
        if (! in_array($idioma, (array) config('alojaflow.idiomas'), true)) {
            return $this->fallar(__('consola.instalar.idioma', ['idiomas' => implode(', ', (array) config('alojaflow.idiomas'))]));
        }
        $correo = strtolower(trim((string) $this->option('correo')));
        try {
            $instalador->migrar();
            $instalador->crearAdministrador(trim((string) $this->option('nombre')), $correo, Str::password(48), $idioma)
                ->forceFill(['debe_crear_contrasena' => true])->save();
            $instalador->decidirMejoraContinua(false);
            $enlace = $instalador->terminar();
        } catch (ValidationException $e) {
            return $this->fallar(implode(' ', $e->validator->errors()->all()));
        } catch (RuntimeException $e) {
            return $this->fallar($e->getMessage());
        }

        return $this->responder(['instalada' => true, 'ya_estaba' => false, 'enlace' => $enlace], __('consola.instalar.lista', ['correo' => $correo]), $enlace);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function responder(array $datos, string $mensaje, ?string $enlace = null): int
    {
        if ($this->option('json')) {
            $this->line((string) json_encode($datos, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        } else {
            $this->info($mensaje);
            if (filled($enlace)) {
                $this->line(__('consola.instalar.enlace').' '.$enlace);
            }
        }

        return self::SUCCESS;
    }

    private function fallar(string $mensaje): int
    {
        if ($this->option('json')) {
            $this->line((string) json_encode(['instalada' => false, 'error' => $mensaje], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        } else {
            $this->error($mensaje);
        }

        return self::FAILURE;
    }
}
