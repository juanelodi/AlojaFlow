<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Paises;

use Illuminate\Support\Str;

/**
 * Los municipios de España con su código del INE, que SES.Hospedajes pide y nadie se sabe de memoria: se busca por lo que ya escribió el huésped, su ciudad y su
 * código postal, cuyos dos primeros dígitos son la provincia. Si no hay una sola respuesta, no se adivina: lo resuelve el alojamiento.
 *
 * Fuente: Instituto Nacional de Estadística, relación de municipios y códigos a 1 de enero de 2026 (resources/datos/municipios-ine.json).
 */
final class CatalogoMunicipios
{
    /** @var array<string, string>|null */
    private static ?array $municipios = null;

    /** @var array<string, list<string>>|null nombre normalizado => códigos */
    private static ?array $indice = null;

    public static function nombre(string $codigo): ?string
    {
        return self::municipios()[$codigo] ?? null;
    }

    public static function codigo(?string $ciudad, ?string $codigoPostal): ?string
    {
        $nombre = self::normalizar((string) $ciudad);
        if ($nombre === '') {
            return null;
        }
        $posibles = self::indice()[$nombre] ?? [];
        $provincia = preg_match('/^[0-9]{5}$/', trim((string) $codigoPostal)) === 1 ? substr(trim((string) $codigoPostal), 0, 2) : null;
        if ($provincia !== null) {
            $posibles = array_values(array_filter($posibles, fn (string $codigo): bool => str_starts_with($codigo, $provincia)));
        }

        return count($posibles) === 1 ? $posibles[0] : null;
    }

    /**
     * @return array<string, string>
     */
    private static function municipios(): array
    {
        if (self::$municipios === null) {
            /** @var array<string, string> $datos */
            $datos = (array) json_decode((string) file_get_contents(resource_path('datos/municipios-ine.json')), true);
            self::$municipios = $datos;
        }

        return self::$municipios;
    }

    /**
     * @return array<string, list<string>>
     */
    private static function indice(): array
    {
        if (self::$indice === null) {
            self::$indice = [];
            foreach (self::municipios() as $clave => $nombre) {
                // PHP vuelve número la clave «28079», pero no «01001».
                $codigo = (string) $clave;
                foreach (self::formas($nombre) as $forma) {
                    if (! in_array($codigo, self::$indice[$forma] ?? [], true)) {
                        self::$indice[$forma][] = $codigo;
                    }
                }
            }
        }

        return self::$indice;
    }

    /**
     * El INE escribe «Coruña, A» y «Donostia/San Sebastián»; la gente, «A Coruña», «La Coruña» o «San Sebastián».
     *
     * @return list<string>
     */
    private static function formas(string $nombre): array
    {
        $formas = [];
        foreach (explode('/', $nombre) as $parte) {
            $parte = trim($parte);
            if (preg_match('/^(.+),\s*(.+)$/u', $parte, $trozos) === 1) {
                $formas[] = self::normalizar($trozos[2].' '.$trozos[1]);
                $formas[] = self::normalizar($trozos[1]);
            } else {
                $formas[] = self::normalizar($parte);
            }
        }

        return array_values(array_unique(array_filter($formas)));
    }

    private static function normalizar(string $texto): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', strtolower(Str::ascii($texto))));
    }
}
