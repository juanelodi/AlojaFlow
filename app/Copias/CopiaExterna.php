<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Copias;

use App\Models\RegistroAuditoria;
use App\Seguridad\ClienteExterno;
use App\Support\Ajustes;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\File;
use RuntimeException;
use SensitiveParameter;
use Throwable;

/**
 * Copia fuera del servidor (D-152): si el hosting se pierde, la base y sus copias no se pierden juntas.
 * Cada copia nocturna sale cifrada (CifradoCopia) a un almacenamiento compatible con S3 o a WebDAV. Sin
 * borrar nada allá: se rotan siete diarias (una por día de la semana) y doce mensuales (el día 1).
 */
final class CopiaExterna
{
    public const AJUSTE = 'copias.externa';

    public const AJUSTE_SECRETO = 'copias.externa.secreto';

    public const AJUSTE_CLAVE = 'copias.externa.clave';

    public const AJUSTE_ULTIMA = 'copias.externa.ultima';

    public const TIPOS = ['s3', 'webdav'];

    public function __construct(private readonly ClienteExterno $cliente)
    {
    }

    /**
     * @return array{tipo: string, endpoint: string, region: string, bucket: string, carpeta: string, llave: string, url: string, usuario: string}
     */
    public function configuracion(): array
    {
        $guardada = (array) Ajustes::obtener(self::AJUSTE, []);

        return [
            'tipo' => in_array($guardada['tipo'] ?? '', self::TIPOS, true) ? (string) $guardada['tipo'] : '',
            'endpoint' => (string) ($guardada['endpoint'] ?? ''),
            'region' => (string) ($guardada['region'] ?? 'us-east-1'),
            'bucket' => (string) ($guardada['bucket'] ?? ''),
            'carpeta' => (string) ($guardada['carpeta'] ?? 'alojaflow'),
            'llave' => (string) ($guardada['llave'] ?? ''),
            'url' => (string) ($guardada['url'] ?? ''),
            'usuario' => (string) ($guardada['usuario'] ?? ''),
        ];
    }

    public function configurada(): bool
    {
        return $this->configuracion()['tipo'] !== '' && Ajustes::secreto(self::AJUSTE_SECRETO) !== null && Ajustes::secreto(self::AJUSTE_CLAVE) !== null;
    }

    /**
     * Prueba la conexión subiendo un archivo chico y, si funciona, la guarda. El secreto y la clave de cifrado,
     * cifrados con la llave de la instalación.
     *
     * @param  array<string, mixed>  $datos
     *
     * @throws ErrorCopia si no se pudo subir el archivo de prueba
     */
    public function guardar(array $datos, #[SensitiveParameter] ?string $secreto, #[SensitiveParameter] ?string $clave): void
    {
        $config = [
            'tipo' => in_array($datos['tipo'] ?? '', self::TIPOS, true) ? (string) $datos['tipo'] : '',
            'endpoint' => rtrim(trim((string) ($datos['endpoint'] ?? '')), '/'),
            'region' => trim((string) ($datos['region'] ?? '')) ?: 'us-east-1',
            'bucket' => trim((string) ($datos['bucket'] ?? '')),
            'carpeta' => trim(trim((string) ($datos['carpeta'] ?? 'alojaflow')), '/'),
            'llave' => trim((string) ($datos['llave'] ?? '')),
            'url' => rtrim(trim((string) ($datos['url'] ?? '')), '/'),
            'usuario' => trim((string) ($datos['usuario'] ?? '')),
        ];
        $secreto = filled($secreto) ? (string) $secreto : Ajustes::secreto(self::AJUSTE_SECRETO);
        if ($config['tipo'] === '' || $secreto === null) {
            throw new ErrorCopia(__('copias.externa.error_datos'));
        }
        $temporal = (string) tempnam(sys_get_temp_dir(), 'afprueba');
        try {
            File::put($temporal, 'AlojaFlow: prueba de la copia fuera del servidor, '.now('UTC')->toIso8601String());
            $this->poner($config, $secreto, 'alojaflow-prueba.txt', $temporal);
        } finally {
            File::delete($temporal);
        }
        Ajustes::guardar(self::AJUSTE, $config);
        Ajustes::guardarSecreto(self::AJUSTE_SECRETO, $secreto);
        if (filled($clave)) {
            Ajustes::guardarSecreto(self::AJUSTE_CLAVE, (string) $clave);
        }
        RegistroAuditoria::anotar('copia.externa', null, ['tipo' => $config['tipo'], 'destino' => $this->destino($config)]);
    }

    public function quitar(): void
    {
        foreach ([self::AJUSTE, self::AJUSTE_SECRETO, self::AJUSTE_CLAVE, self::AJUSTE_ULTIMA] as $clave) {
            Ajustes::borrar($clave);
        }
        RegistroAuditoria::anotar('copia.externa_quitar', null);
    }

    /**
     * Cifra la copia y la sube como la del día de la semana y, el día 1, también como la del mes.
     *
     * @return bool si llegó; el error queda anotado para la pantalla y el estado del sistema
     */
    public function subir(string $rutaCopia): bool
    {
        if (! $this->configurada()) {
            return false;
        }
        $config = $this->configuracion();
        $cifrada = $rutaCopia.'.afcopia';
        try {
            CifradoCopia::cifrar($rutaCopia, $cifrada, (string) Ajustes::secreto(self::AJUSTE_CLAVE));
            $secreto = (string) Ajustes::secreto(self::AJUSTE_SECRETO);
            $hoy = now();
            $this->poner($config, $secreto, 'alojaflow-diaria-'.$hoy->isoWeekday().'.afcopia', $cifrada);
            if ($hoy->day === 1) {
                $this->poner($config, $secreto, 'alojaflow-mensual-'.$hoy->format('m').'.afcopia', $cifrada);
            }
            Ajustes::guardar(self::AJUSTE_ULTIMA, ['en' => now('UTC')->toIso8601String(), 'ok' => true, 'error' => null, 'tamano' => (int) filesize($cifrada)]);

            return true;
        } catch (Throwable $e) {
            Ajustes::guardar(self::AJUSTE_ULTIMA, ['en' => now('UTC')->toIso8601String(), 'ok' => false, 'error' => mb_substr($e->getMessage(), 0, 300)]);

            return false;
        } finally {
            File::delete($cifrada);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function ultima(): array
    {
        return (array) Ajustes::obtener(self::AJUSTE_ULTIMA, []);
    }

    /**
     * @param  array{tipo: string, endpoint: string, region: string, bucket: string, carpeta: string, llave: string, url: string, usuario: string}  $config
     */
    public function destino(array $config): string
    {
        return $config['tipo'] === 's3'
            ? $config['endpoint'].'/'.$config['bucket'].($config['carpeta'] !== '' ? '/'.$config['carpeta'] : '')
            : $config['url'].($config['carpeta'] !== '' ? '/'.$config['carpeta'] : '');
    }

    /**
     * @param  array{tipo: string, endpoint: string, region: string, bucket: string, carpeta: string, llave: string, url: string, usuario: string}  $config
     *
     * @throws ErrorCopia
     */
    private function poner(array $config, #[SensitiveParameter] string $secreto, string $nombre, string $ruta): void
    {
        $url = $this->destino($config).'/'.$nombre;
        try {
            $http = $this->cliente->peticion($url)->timeout(900)->connectTimeout(15);
        } catch (RuntimeException $e) {
            throw new ErrorCopia(__('copias.externa.error_destino', ['motivo' => $e->getMessage()]));
        }
        $cuerpo = Utils::streamFor(fopen($ruta, 'rb'));
        try {
            $respuesta = $config['tipo'] === 's3'
                ? $this->ponerS3($http, $config, $secreto, $url, $ruta, $cuerpo)
                : $http->withBasicAuth($config['usuario'], $secreto)->withBody($cuerpo, 'application/octet-stream')->put($url);
        } catch (Throwable $e) {
            throw new ErrorCopia(__('copias.externa.error_destino', ['motivo' => mb_substr($e->getMessage(), 0, 200)]));
        }
        if (! $respuesta->successful()) {
            throw new ErrorCopia(__('copias.externa.error_destino', ['motivo' => 'HTTP '.$respuesta->status()]));
        }
    }

    /**
     * @param  array{tipo: string, endpoint: string, region: string, bucket: string, carpeta: string, llave: string, url: string, usuario: string}  $config
     */
    private function ponerS3(PendingRequest $http, array $config, #[SensitiveParameter] string $secreto, string $url, string $ruta, \Psr\Http\Message\StreamInterface $cuerpo): Response
    {
        $partes = parse_url($url);
        $host = (string) ($partes['host'] ?? '').(isset($partes['port']) ? ':'.$partes['port'] : '');
        $hash = (string) hash_file('sha256', $ruta);
        $cabeceras = [
            'host' => $host,
            'x-amz-content-sha256' => $hash,
            'x-amz-date' => gmdate('Ymd\THis\Z'),
        ];
        $autorizacion = FirmaS3::autorizacion('PUT', (string) ($partes['path'] ?? '/'), [], $cabeceras, $hash, $config['region'], $config['llave'], $secreto);

        return $http->withHeaders([
            'x-amz-content-sha256' => $cabeceras['x-amz-content-sha256'],
            'x-amz-date' => $cabeceras['x-amz-date'],
            'Authorization' => $autorizacion,
        ])->withBody($cuerpo, 'application/octet-stream')->put($url);
    }
}
