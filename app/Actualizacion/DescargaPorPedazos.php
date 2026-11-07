<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Actualizacion;

use App\Seguridad\ClienteExterno;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use LogicException;
use Throwable;

/**
 * Baja el zip de una versión por pedazos de 8 MB, uno por petición (D-140): en un hosting compartido una
 * descarga larga la cortaría el servidor. Lo bajado se queda en disco, así que se reanuda aunque se cierre
 * la página. El zip solo llega a la carpeta de entrada con su firma comprobada, y de ahí sigue el camino de
 * siempre: Revisor, contraseña y Actualizador.
 */
class DescargaPorPedazos
{
    public const TAMANO_PEDAZO = 8388608;

    /** GitHub guarda los archivos de sus versiones aquí, y redirige. */
    private const ARCHIVOS_DE_GITHUB = '.githubusercontent.com';

    private const REDIRECCIONES_MAXIMAS = 5;

    private const SEGUNDOS_POR_PEDAZO = 90;

    /** Si el proceso muere, el candado se suelta pasado este tiempo. */
    private const SEGUNDOS_CANDADO = 150;

    private const DIAS_ABANDONO = 7;

    public function __construct(
        private readonly Actualizador $actualizador,
        private readonly Revisor $revisor,
        private readonly ClienteExterno $cliente,
    ) {
    }

    /**
     * Baja el siguiente pedazo.
     *
     * @return array{llevamos: int, total: int, paquete: ?string} el nombre del paquete, cuando ya está completo
     *
     * @throws ErrorActualizacion
     */
    public function avanzar(string $version): array
    {
        if (! preg_match('/^\d{1,3}\.\d{1,3}\.\d{1,3}$/', $version)) {
            throw new ErrorActualizacion(__('actualizaciones.error.sin_version_nueva'));
        }
        if ($this->actualizador->bloqueadaPorGit()) {
            throw new ErrorActualizacion(__('actualizaciones.error.copia_git'));
        }
        if ($this->actualizador->pendiente() !== null) {
            throw new ErrorActualizacion(__('actualizaciones.error.en_curso'));
        }

        $candado = Cache::lock('alojaflow:descarga-actualizacion', self::SEGUNDOS_CANDADO);
        if (! $candado->get()) {
            throw new ErrorActualizacion(__('actualizaciones.error.descarga_en_curso'));
        }
        try {
            return $this->siguientePedazo($version);
        } finally {
            $candado->release();
        }
    }

    /**
     * Una descarga que nadie terminó ocupa decenas de MB.
     */
    public function limpiarAbandonadas(): void
    {
        foreach (glob($this->carpeta().'/*') ?: [] as $archivo) {
            if ((int) @filemtime($archivo) < time() - self::DIAS_ABANDONO * 86400) {
                @unlink($archivo);
            }
        }
    }

    /**
     * @return array{llevamos: int, total: int, paquete: ?string}
     */
    private function siguientePedazo(string $version): array
    {
        $nombre = 'alojaflow-'.$version.'.zip';
        $final = $this->actualizador->carpetaEntrada().'/'.$nombre;
        if (is_file($final)) {
            // Ya estaba: se bajó antes, o se perdió la respuesta del último pedazo.
            if ($this->revisor->firmadoPorQuienPublica($final, $version, $this->actualizador->clavePublica())) {
                $tamano = (int) filesize($final);

                return ['llevamos' => $tamano, 'total' => $tamano, 'paquete' => $nombre];
            }
            @unlink($final);
        }

        $carpeta = $this->carpeta();
        File::ensureDirectoryExists($carpeta, 0700);
        $parcial = $carpeta.'/'.$nombre.'.parcial';
        $pedazo = $carpeta.'/'.$nombre.'.pedazo';
        foreach (glob($carpeta.'/*') ?: [] as $archivo) {
            if ($archivo !== $parcial) {
                @unlink($archivo); // lo que quedó de una versión anterior
            }
        }
        clearstatcache(true, $parcial);
        $llevamos = is_file($parcial) ? (int) filesize($parcial) : 0;

        try {
            $total = $this->acomodar($this->pedir($version, $llevamos, $pedazo), $llevamos, $parcial, $pedazo);
        } finally {
            @unlink($pedazo);
        }

        clearstatcache(true, $parcial);
        $llevamos = (int) filesize($parcial);
        if ($llevamos < $total) {
            return ['llevamos' => $llevamos, 'total' => $total, 'paquete' => null];
        }

        return ['llevamos' => $llevamos, 'total' => $total, 'paquete' => $this->cerrar($parcial, $final, $version)];
    }

    /**
     * Pide un rango. Cada redirección se comprueba: mismo host, o donde GitHub guarda sus archivos,
     * siempre https y hacia redes públicas.
     */
    private function pedir(string $version, int $desde, string $pedazo): Response
    {
        $url = str_replace(':version', $version, (string) config('alojaflow.actualizaciones.url_paquete'));
        $origen = strtolower((string) parse_url($url, PHP_URL_HOST));
        $maximo = (int) config('alojaflow.actualizaciones.tamano_maximo_paquete');

        for ($i = 0; $i <= self::REDIRECCIONES_MAXIMAS; $i++) {
            $host = strtolower((string) parse_url($url, PHP_URL_HOST));
            if ($host !== $origen && ! ($origen === 'github.com' && str_ends_with($host, self::ARCHIVOS_DE_GITHUB))) {
                throw new ErrorActualizacion(__('actualizaciones.error.descarga_host', ['host' => $host]));
            }
            $excedido = false;
            try {
                $respuesta = $this->cliente->peticion($url)
                    ->timeout(self::SEGUNDOS_POR_PEDAZO)
                    ->withUserAgent('AlojaFlow')
                    ->withHeaders(['Range' => 'bytes='.$desde.'-'.($desde + self::TAMANO_PEDAZO - 1), 'Accept-Encoding' => 'identity'])
                    ->withOptions([
                        'sink' => $pedazo,
                        'progress' => function (int|float $total, int|float $descargado) use ($maximo, &$excedido): void {
                            if ($total > $maximo || $descargado > $maximo) {
                                $excedido = true;
                                throw new LogicException('limite');
                            }
                        },
                    ])->get($url);
            } catch (Throwable $e) {
                if ($excedido) {
                    throw new ErrorActualizacion(__('actualizaciones.error.demasiado_grande'), 0, $e);
                }
                Log::warning('Descarga de la actualización: '.$e->getMessage());
                throw new ErrorActualizacion(__('actualizaciones.error.descarga_red'), 0, $e);
            }
            if (! $respuesta->redirect()) {
                return $respuesta;
            }
            $siguiente = (string) $respuesta->header('Location');
            if ($siguiente === '') {
                return $respuesta;
            }
            $url = str_starts_with($siguiente, '/') ? (string) preg_replace('#^(https?://[^/]+).*$#i', '$1', $url).$siguiente : $siguiente;
        }

        throw new ErrorActualizacion(__('mensajes.error_redirecciones'));
    }

    /**
     * Suma al parcial lo que llegó, solo si es lo que se pidió.
     *
     * @return int cuánto mide el zip completo
     */
    private function acomodar(Response $respuesta, int $llevamos, string $parcial, string $pedazo): int
    {
        $codigo = $respuesta->status();
        clearstatcache(true, $pedazo);
        $recibidos = is_file($pedazo) ? (int) filesize($pedazo) : 0;

        // Un servidor que no entiende de rangos manda todo el archivo: ese ya es el resultado.
        if ($codigo === 200) {
            $this->comprobarTamano($recibidos, 0, $parcial);
            if (! @rename($pedazo, $parcial)) {
                throw new ErrorActualizacion(__('actualizaciones.error.no_escribe', ['ruta' => basename($parcial)]));
            }

            return $recibidos;
        }

        $rango = (string) $respuesta->header('Content-Range');
        if ($codigo === 416) {
            // Ya estaba todo aquí (se perdió la respuesta); si no, el archivo de allá cambió.
            if (preg_match('#^bytes\s+\*/(\d+)$#i', $rango, $m) && (int) $m[1] === $llevamos && $llevamos > 0) {
                return $llevamos;
            }
            @unlink($parcial);
            throw new ErrorActualizacion(__('actualizaciones.error.descarga_rango'));
        }
        if ($codigo !== 206) {
            throw new ErrorActualizacion(__('actualizaciones.error.descarga_http', ['codigo' => $codigo]));
        }

        if (! preg_match('#^bytes\s+(\d+)-(\d+)/(\d+)$#i', $rango, $m) || (int) $m[1] !== $llevamos || (int) $m[2] >= (int) $m[3]) {
            @unlink($parcial);
            throw new ErrorActualizacion(__('actualizaciones.error.descarga_rango'));
        }
        // Llegó menos de lo que dice: el pedazo no vale, y se pide otra vez.
        if ($recibidos !== (int) $m[2] - (int) $m[1] + 1) {
            throw new ErrorActualizacion(__('actualizaciones.error.descarga_red'));
        }
        $total = (int) $m[3];
        $this->comprobarTamano($total, $llevamos, $parcial);
        $this->anexar($pedazo, $parcial, $recibidos);

        return $total;
    }

    private function anexar(string $pedazo, string $parcial, int $recibidos): void
    {
        $entrada = @fopen($pedazo, 'rb');
        $salida = @fopen($parcial, 'ab');
        $copiados = $entrada !== false && $salida !== false ? stream_copy_to_stream($entrada, $salida) : false;
        if ($entrada !== false) {
            fclose($entrada);
        }
        if ($salida !== false) {
            fclose($salida);
        }
        if ($copiados !== $recibidos) {
            // Un parcial a medio escribir ya no sirve para seguir.
            @unlink($parcial);
            throw new ErrorActualizacion(__('actualizaciones.error.no_escribe', ['ruta' => basename($parcial)]));
        }
    }

    private function comprobarTamano(int $total, int $llevamos, string $parcial): void
    {
        if ($total > (int) config('alojaflow.actualizaciones.tamano_maximo_paquete')) {
            @unlink($parcial);
            throw new ErrorActualizacion(__('actualizaciones.error.demasiado_grande'));
        }
        $libre = @disk_free_space(dirname($parcial));
        if (is_float($libre) && $libre < $total - $llevamos) {
            throw new ErrorActualizacion(__('actualizaciones.error.sin_espacio'));
        }
    }

    /**
     * Sin firma válida de esa versión no llega a la lista de paquetes, y otro intento empieza de cero.
     */
    private function cerrar(string $parcial, string $final, string $version): string
    {
        if (! $this->revisor->firmadoPorQuienPublica($parcial, $version, $this->actualizador->clavePublica())) {
            @unlink($parcial);
            throw new ErrorActualizacion(__('actualizaciones.error.firma'));
        }
        File::ensureDirectoryExists(dirname($final), 0700);
        if (! @rename($parcial, $final)) {
            throw new ErrorActualizacion(__('actualizaciones.error.no_escribe', ['ruta' => basename($final)]));
        }

        return basename($final);
    }

    private function carpeta(): string
    {
        return $this->actualizador->carpeta().'/descargas';
    }
}
