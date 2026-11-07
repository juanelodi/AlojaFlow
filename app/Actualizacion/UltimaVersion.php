<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Actualizacion;

use App\Seguridad\ClienteExterno;
use App\Support\Ajustes;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Pregunta cuál es la última versión publicada y recuerda la respuesta (D-140). Es un GET sin
 * parámetros, que no manda ningún dato de la instalación, y lo que responde solo sirve para avisar: lo que
 * se instala lo decide la firma del zip. Si no responde, nada se rompe y se sigue con lo último que se supo.
 */
final class UltimaVersion
{
    public const AJUSTE_AVISAR = 'actualizaciones.avisar';

    public const AJUSTE_CONSULTA = 'actualizaciones.ultima_version';

    private const BYTES_MAXIMOS = 1048576;

    private const NOTAS_MAXIMAS = 20000;

    public function __construct(private readonly ClienteExterno $cliente)
    {
    }

    public function avisoActivo(): bool
    {
        return Ajustes::obtener(self::AJUSTE_AVISAR, true) !== false;
    }

    public function cambiarAviso(bool $activo): void
    {
        Ajustes::guardar(self::AJUSTE_AVISAR, $activo);
    }

    /**
     * @return bool si se obtuvo respuesta; nunca lanza
     */
    public function consultar(): bool
    {
        try {
            $datos = $this->pedir();
            Ajustes::guardar(self::AJUSTE_CONSULTA, $datos + ['consultada_en' => now('UTC')->toIso8601String(), 'fallo' => false]);

            return true;
        } catch (Throwable $e) {
            Log::warning('No se pudo consultar la última versión: '.$e->getMessage());
            try {
                Ajustes::guardar(self::AJUSTE_CONSULTA, ['consultada_en' => now('UTC')->toIso8601String(), 'fallo' => true] + $this->ultimaConsulta());
            } catch (Throwable) {
                // Sin base de datos tampoco hay a quién avisar.
            }

            return false;
        }
    }

    /**
     * La versión publicada, si es más nueva que la instalada.
     *
     * @return array{version: string, notas: string, enlace: ?string}|null
     */
    public function disponible(): ?array
    {
        $consulta = $this->ultimaConsulta();
        $version = $consulta['version'] ?? null;
        if (! is_string($version) || version_compare($version, (string) config('alojaflow.version'), '<=')) {
            return null;
        }
        $enlace = $consulta['enlace'] ?? null;

        return ['version' => $version, 'notas' => (string) ($consulta['notas'] ?? ''), 'enlace' => is_string($enlace) ? $enlace : null];
    }

    /**
     * Lo que se anuncia fuera de Actualizaciones. Con el aviso apagado no se insiste, aunque ya se supiera de una versión.
     *
     * @return array{version: string, notas: string, enlace: ?string}|null
     */
    public function paraAvisar(): ?array
    {
        return $this->avisoActivo() ? $this->disponible() : null;
    }

    /**
     * @return array<string, mixed> lo último que se supo: version, notas, enlace, consultada_en y fallo
     */
    public function ultimaConsulta(): array
    {
        return (array) Ajustes::obtener(self::AJUSTE_CONSULTA, []);
    }

    /**
     * @return array{version: string, notas: string, enlace: ?string}
     *
     * @throws ErrorActualizacion si la respuesta no es la de una versión publicada
     */
    private function pedir(): array
    {
        $respuesta = $this->cliente->descargar((string) config('alojaflow.actualizaciones.url_ultima_version'), false, self::BYTES_MAXIMOS);
        $datos = $respuesta->successful() ? $respuesta->json() : null;
        $etiqueta = is_array($datos) ? ($datos['tag_name'] ?? null) : null;
        if (! is_array($datos) || ! is_string($etiqueta) || ! preg_match('/^v?(\d{1,3}\.\d{1,3}\.\d{1,3})$/', $etiqueta, $coincide)
            || ($datos['draft'] ?? false) === true || ($datos['prerelease'] ?? false) === true) {
            throw new ErrorActualizacion('HTTP '.$respuesta->status());
        }
        $notas = $datos['body'] ?? '';
        $enlace = $datos['html_url'] ?? null;

        return [
            'version' => $coincide[1],
            'notas' => is_string($notas) ? Str::limit(trim($notas), self::NOTAS_MAXIMAS) : '',
            // Va en un href: solo una dirección https de verdad.
            'enlace' => is_string($enlace) && str_starts_with($enlace, 'https://') && filter_var($enlace, FILTER_VALIDATE_URL) !== false ? $enlace : null,
        ];
    }
}
