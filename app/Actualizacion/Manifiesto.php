<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Actualizacion;

use InvalidArgumentException;

/**
 * El manifiesto de un paquete: versión, requisitos y el SHA-256 de cada archivo. Es lo que se
 * firma; el paquete vale si la firma vale y cada archivo coincide con su hash.
 */
final class Manifiesto
{
    public const PRODUCTO = 'AlojaFlow';

    /**
     * @param  array<string, string>  $archivos  ruta relativa => sha256
     * @param  list<string>  $extensiones
     */
    public function __construct(
        public readonly string $version,
        public readonly string $fecha,
        public readonly string $phpMinimo,
        public readonly array $extensiones,
        public readonly array $archivos,
    ) {
    }

    public static function desdeJson(string $json): self
    {
        $datos = json_decode($json, true);
        if (! is_array($datos) || ($datos['producto'] ?? null) !== self::PRODUCTO) {
            throw new InvalidArgumentException('no es un manifiesto de AlojaFlow');
        }

        $version = (string) ($datos['version'] ?? '');
        if (! self::versionValida($version)) {
            throw new InvalidArgumentException('versión no válida');
        }

        $archivos = $datos['archivos'] ?? null;
        if (! is_array($archivos) || $archivos === []) {
            throw new InvalidArgumentException('el manifiesto no trae archivos');
        }
        $limpios = [];
        foreach ($archivos as $ruta => $hash) {
            if (! is_string($ruta) || ! is_string($hash) || ! preg_match('/^[0-9a-f]{64}$/', $hash) || ! Rutas::valida($ruta)
                || in_array($ruta, [Revisor::ARCHIVO_MANIFIESTO, Revisor::ARCHIVO_FIRMA], true)) {
                throw new InvalidArgumentException('ruta o hash no válidos: '.(is_string($ruta) ? $ruta : '?'));
            }
            // Las rutas protegidas (el esqueleto de storage/ para una instalación nueva) pueden venir
            // en el paquete, pero al actualizar se omiten: nunca se escriben (Rutas::protegida).
            $limpios[$ruta] = $hash;
        }

        $extensiones = array_values(array_filter((array) ($datos['extensiones'] ?? []), 'is_string'));

        return new self($version, (string) ($datos['fecha'] ?? ''), (string) ($datos['php_minimo'] ?? '8.3.0'), $extensiones, $limpios);
    }

    /**
     * JSON canónico (claves ordenadas): son los bytes que se firman.
     */
    public function aJson(): string
    {
        $archivos = $this->archivos;
        ksort($archivos, SORT_STRING);

        return json_encode([
            'producto' => self::PRODUCTO,
            'version' => $this->version,
            'fecha' => $this->fecha,
            'php_minimo' => $this->phpMinimo,
            'extensiones' => $this->extensiones,
            'archivos' => $archivos,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n";
    }

    /**
     * Versiones x.y.z con números (D-030: 1.0.1, 1.0.2 … 1.0.99, 1.2.0…).
     */
    public static function versionValida(string $version): bool
    {
        return preg_match('/^\d{1,3}\.\d{1,3}\.\d{1,3}$/', $version) === 1;
    }

    public static function carpetaBase(string $version): string
    {
        return 'alojaflow-'.$version;
    }
}
