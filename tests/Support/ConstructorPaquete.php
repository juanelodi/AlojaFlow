<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace Tests\Support;

use App\Actualizacion\Empaquetador;
use App\Actualizacion\Firma;
use App\Actualizacion\Manifiesto;
use Illuminate\Support\Facades\File;
use RuntimeException;
use ZipArchive;

/**
 * Paquetes de actualización de prueba, firmados con una clave de prueba y construidos con el
 * mismo Empaquetador::comprimir() que los paquetes reales.
 */
final class ConstructorPaquete
{
    /** @var array{publica: string, secreta: string} */
    public readonly array $claves;

    public function __construct(private readonly string $carpetaTrabajo)
    {
        $this->claves = Firma::generarClaves();
    }

    /**
     * @param  array<string, string>  $archivos  ruta => contenido
     */
    public function construir(string $version, array $archivos, string $zip): string
    {
        $imagen = $this->carpetaTrabajo.'/imagen-'.bin2hex(random_bytes(4));
        foreach ($archivos as $ruta => $contenido) {
            File::ensureDirectoryExists(dirname($imagen.'/'.$ruta));
            File::put($imagen.'/'.$ruta, $contenido);
        }
        File::ensureDirectoryExists(dirname($zip));
        (new Empaquetador())->comprimir($imagen, $version, $this->claves['secreta'], $zip);
        File::deleteDirectory($imagen);

        return $zip;
    }

    /**
     * Instala un paquete como se hace desde cero: se descomprime y su carpeta es la instalación.
     */
    public static function instalar(string $zip, string $version, string $raiz): void
    {
        $destino = dirname($raiz).'/descomprimido-'.bin2hex(random_bytes(4));
        $archivo = new ZipArchive();
        if ($archivo->open($zip) !== true || ! $archivo->extractTo($destino)) {
            throw new RuntimeException("No se pudo descomprimir $zip");
        }
        $archivo->close();
        File::copyDirectory($destino.'/'.Manifiesto::carpetaBase($version), $raiz);
        File::deleteDirectory($destino);
    }

    /**
     * Cambia o añade una entrada del zip sin volver a firmarlo (paquete alterado).
     */
    public static function alterar(string $zip, string $entrada, string $contenido): void
    {
        $archivo = new ZipArchive();
        if ($archivo->open($zip) !== true) {
            throw new RuntimeException("No se pudo abrir $zip");
        }
        $archivo->addFromString($entrada, $contenido);
        $archivo->close();
    }
}
