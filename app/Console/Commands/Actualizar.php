<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actualizacion\Actualizador;
use App\Actualizacion\ErrorActualizacion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * Lo mismo que Sistema → Actualizaciones, desde la consola: revisa el zip, aplica los archivos y
 * termina en otro proceso, ya con el código nuevo (instalacion-actualizaciones.md).
 */
class Actualizar extends Command
{
    protected $signature = 'alojaflow:actualizar
        {paquete : Ruta del zip de la versión, o nombre de un paquete ya subido}
        {--reinstalar : Instalar aunque sea la versión instalada}
        {--force : Sin pedir confirmación}';

    protected $description = 'Actualiza AlojaFlow con el zip firmado de una versión';

    public function handle(Actualizador $actualizador): int
    {
        $reinstalar = (bool) $this->option('reinstalar');
        try {
            $nombre = $this->paqueteEnEntrada($actualizador, (string) $this->argument('paquete'));
            $revision = $actualizador->revisar($nombre, $reinstalar);
        } catch (ErrorActualizacion $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        foreach ($revision->avisos as $aviso) {
            $this->warn($aviso);
        }
        if (! $revision->valida()) {
            foreach ($revision->errores as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }
        $this->table(
            [__('actualizaciones.de_a'), __('actualizaciones.nuevos'), __('actualizaciones.cambiados'), __('actualizaciones.borrar'), __('actualizaciones.iguales')],
            [[$actualizador->versionInstalada().' → '.$revision->version, count($revision->nuevos), count($revision->cambiados), count($revision->borrar), $revision->iguales]],
        );
        if (! $this->option('force') && ! $this->confirm(__('actualizaciones.confirmar_titulo'), true)) {
            return self::FAILURE;
        }

        try {
            $actualizacion = $actualizador->preparar($nombre, $reinstalar, null);
            $this->line(__('actualizaciones.consola.aplicando'));
            $actualizador->aplicar($actualizacion);
        } catch (ErrorActualizacion $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        // La segunda fase, en otro proceso: así ya corre con el código nuevo.
        $this->line(__('actualizaciones.consola.terminando'));
        $proceso = Process::path($actualizador->raiz())->timeout(3600)->run(
            [PHP_BINARY, 'artisan', 'alojaflow:finalizar-actualizacion', '--actualizacion='.$actualizacion->id],
            fn (string $tipo, string $salida) => $this->output->write($salida),
        );

        return $proceso->successful() ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Un zip de cualquier carpeta se copia antes a la de entrada, como si se hubiera subido.
     */
    private function paqueteEnEntrada(Actualizador $actualizador, string $paquete): string
    {
        if (! is_file($paquete)) {
            return $paquete; // nombre de un paquete ya subido; lo comprueba rutaPaquete()
        }
        $nombre = Actualizador::nombreSeguro(basename($paquete));
        $destino = $actualizador->carpetaEntrada().'/'.$nombre;
        if (realpath($paquete) !== realpath($destino)) {
            File::ensureDirectoryExists($actualizador->carpetaEntrada(), 0700);
            File::copy($paquete, $destino);
        }

        return $nombre;
    }
}
