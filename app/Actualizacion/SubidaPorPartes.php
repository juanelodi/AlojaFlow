<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Actualizacion;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;

/**
 * Recibe el zip de una versión en partes de 1 MB (D-072): muchos hostings limitan las subidas a 2 MB, y con
 * partes chicas también se puede reintentar sin empezar de cero. Lo que se arma aquí todavía no vale nada:
 * igual que un zip subido por FTP, lo comprueba Revisor antes de instalarlo.
 */
class SubidaPorPartes
{
    public const TAMANO_PARTE = 1048576;

    /** Una subida a medias de hace más tiempo ya no se va a terminar. */
    private const HORAS_ABANDONO = 24;

    public function __construct(private readonly Actualizador $actualizador)
    {
    }

    /**
     * Anexa una parte y, si es la última, deja el zip en la carpeta de entrada.
     *
     * @return string|null el nombre del paquete, cuando ya está completo
     *
     * @throws ErrorActualizacion
     */
    public function recibir(UploadedFile $parte, string $subida, string $nombre, int $indice, int $total): ?string
    {
        $maximo = (int) config('alojaflow.actualizaciones.tamano_maximo_paquete');
        if (! preg_match('/^[0-9a-f]{32}$/', $subida) || $indice < 0 || $total < 1 || $indice >= $total || $total > (int) ceil($maximo / self::TAMANO_PARTE)) {
            throw new ErrorActualizacion(__('actualizaciones.error.subida_invalida'));
        }
        if (! $parte->isValid() || $parte->getSize() > self::TAMANO_PARTE || ($indice < $total - 1 && $parte->getSize() !== self::TAMANO_PARTE)) {
            throw new ErrorActualizacion(__('actualizaciones.error.subida_parte', ['parte' => $indice + 1]));
        }

        $carpeta = $this->carpeta();
        File::ensureDirectoryExists($carpeta, 0700);
        $this->limpiarAbandonadas($carpeta);
        $parcial = $carpeta.'/'.$subida.'.parcial';

        clearstatcache(true, $parcial);
        $llevamos = is_file($parcial) ? (int) filesize($parcial) : 0;
        $esperado = $indice * self::TAMANO_PARTE;
        // Volver a mandar la parte que ya llegó (se perdió la respuesta) no la duplica.
        if ($llevamos === $esperado + $parte->getSize()) {
            return $indice === $total - 1 ? $this->cerrar($parcial, $nombre) : null;
        }
        if ($llevamos !== $esperado) {
            // Se perdió la respuesta de la última parte y el zip ya está armado.
            $armado = $this->actualizador->carpetaEntrada().'/'.Actualizador::nombreSeguro($nombre);
            if ($indice === $total - 1 && is_file($armado) && (int) filesize($armado) === $esperado + $parte->getSize()) {
                return basename($armado);
            }
            throw new ErrorActualizacion(__('actualizaciones.error.subida_orden'));
        }

        $datos = (string) file_get_contents($parte->getRealPath());
        if (file_put_contents($parcial, $datos, FILE_APPEND | LOCK_EX) !== strlen($datos)) {
            throw new ErrorActualizacion(__('actualizaciones.error.no_escribe', ['ruta' => basename($parcial)]));
        }

        return $indice === $total - 1 ? $this->cerrar($parcial, $nombre) : null;
    }

    private function cerrar(string $parcial, string $nombre): string
    {
        $final = Actualizador::nombreSeguro($nombre);
        $carpeta = $this->actualizador->carpetaEntrada();
        File::ensureDirectoryExists($carpeta, 0700);
        if (! @rename($parcial, $carpeta.'/'.$final)) {
            throw new ErrorActualizacion(__('actualizaciones.error.no_escribe', ['ruta' => $final]));
        }

        return $final;
    }

    private function carpeta(): string
    {
        return dirname($this->actualizador->carpetaEntrada()).'/subidas';
    }

    private function limpiarAbandonadas(string $carpeta): void
    {
        foreach (glob($carpeta.'/*.parcial') ?: [] as $archivo) {
            if ((int) @filemtime($archivo) < time() - self::HORAS_ABANDONO * 3600) {
                @unlink($archivo);
            }
        }
    }
}
