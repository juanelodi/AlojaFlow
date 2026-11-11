<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Sincronizacion\HotelDruid;

use Throwable;

/**
 * Decide a qué direcciones se puede conectar el servidor (subordinacion.md A6, SSRF): resuelve el nombre y rechaza lo
 * que no esté en una red pública. Devuelve la IP ya comprobada para fijarla en la conexión, y que no pueda cambiar
 * entre la revisión y la petición.
 */
class ValidadorDestino
{
    private const PASO = 'validar';

    /** Redes que no son de internet: privadas, de bucle, de enlace local, de documentación, reservadas y de difusión. */
    private const REDES_NO_PUBLICAS = [
        '0.0.0.0/8', '10.0.0.0/8', '100.64.0.0/10', '127.0.0.0/8', '169.254.0.0/16', '172.16.0.0/12', '192.0.0.0/24',
        '192.0.2.0/24', '192.88.99.0/24', '192.168.0.0/16', '198.18.0.0/15', '198.51.100.0/24', '203.0.113.0/24',
        '224.0.0.0/4', '240.0.0.0/4',
        '::/128', '::1/128', '64:ff9b:1::/48', '100::/64', '2001::/23', '2001:db8::/32', 'fc00::/7', 'fe80::/10',
        'fec0::/10', 'ff00::/8',
    ];

    /** Prefijos de IPv6 que llevan dentro una dirección IPv4, y en qué byte empieza: se juzga la de dentro. */
    private const IPV4_DENTRO_DE_IPV6 = ['::ffff:0:0/96' => 12, '::/96' => 12, '64:ff9b::/96' => 12, '2002::/16' => 2];

    /**
     * La dirección base de una principal: la carpeta de su instalación.
     *
     * @return array{host: string, puerto: int, ip: string}
     */
    public function validar(string $urlBase, bool $permitirRedLocal): array
    {
        $partes = $this->partes($urlBase);
        if (isset($partes['query']) || isset($partes['fragment'])) {
            throw $this->rechazo('url_invalida');
        }
        if (! str_ends_with($partes['path'] ?? '', '/')) {
            throw $this->rechazo('sin_barra_final');
        }
        if ($partes['scheme'] === 'http' && ! $permitirRedLocal) {
            throw $this->rechazo('solo_https');
        }

        return $this->destino($partes, $permitirRedLocal);
    }

    /**
     * Una dirección que escribió un usuario (un calendario, un aviso a otro sistema): siempre en una red pública.
     *
     * @return array{host: string, puerto: int, ip: string}
     */
    public function validarEnlace(string $url, bool $permitirHttp = false): array
    {
        $partes = $this->partes($url);
        if ($partes['scheme'] === 'http' && ! $permitirHttp) {
            throw $this->rechazo('solo_https');
        }

        return $this->destino($partes, false);
    }

    /**
     * Un servidor dado por su nombre o su IP, sin dirección (el de correo): devuelve la IP pública a la que conectarse.
     */
    public function validarServidor(string $host): string
    {
        $host = strtolower(trim($host));
        if ($host === '' || preg_match('/^[a-z0-9._:\-]+$/', $host) !== 1) {
            throw $this->rechazo('url_invalida');
        }

        return $this->ipPermitida($host, false);
    }

    /**
     * Las direcciones IP de un nombre. Las pruebas la sustituyen para no depender del DNS.
     *
     * @return list<string>
     */
    protected function resolver(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }

        $direcciones = gethostbynamel($host) ?: [];
        try {
            foreach (dns_get_record($host, DNS_AAAA) ?: [] as $registro) {
                if (is_string($registro['ipv6'] ?? null)) {
                    $direcciones[] = $registro['ipv6'];
                }
            }
        } catch (Throwable) {
            // Sin registros AAAA, o el servidor de nombres no contestó: bastan las de IPv4.
        }

        return array_values(array_unique($direcciones));
    }

    /**
     * @return array{scheme: string, host: string, port?: int, path?: string, query?: string, fragment?: string}
     */
    private function partes(string $url): array
    {
        $partes = parse_url(trim($url));
        if (! is_array($partes)) {
            throw $this->rechazo('url_invalida');
        }
        $esquema = strtolower((string) ($partes['scheme'] ?? ''));
        $host = strtolower(trim((string) ($partes['host'] ?? ''), '[]'));
        if (! in_array($esquema, ['http', 'https'], true) || $host === '' || isset($partes['user']) || isset($partes['pass'])) {
            throw $this->rechazo('url_invalida');
        }

        return ['scheme' => $esquema, 'host' => $host] + array_intersect_key($partes, array_flip(['port', 'path', 'query', 'fragment']));
    }

    /**
     * @param  array{scheme: string, host: string, port?: int}  $partes
     * @return array{host: string, puerto: int, ip: string}
     */
    private function destino(array $partes, bool $permitirRedLocal): array
    {
        return [
            'host' => $partes['host'],
            'puerto' => $partes['port'] ?? ($partes['scheme'] === 'https' ? 443 : 80),
            'ip' => $this->ipPermitida($partes['host'], $permitirRedLocal),
        ];
    }

    /**
     * Todas las direcciones del nombre tienen que valer: con una sola interna, quien controle el DNS elegiría esa.
     */
    private function ipPermitida(string $host, bool $permitirRedLocal): string
    {
        $direcciones = array_values(array_filter($this->resolver($host), 'is_string'));
        if ($direcciones === []) {
            // No es una dirección prohibida sino un nombre que hoy no resuelve: se reintenta, sin pausar (A5, SUB-001).
            throw new ErrorSincronizacion(CodigoError::Conexion, self::PASO, __('sincronizacion.destino.sin_resolver', ['host' => $host]));
        }
        foreach ($direcciones as $direccion) {
            if (! $permitirRedLocal && ! $this->esPublica($direccion)) {
                throw $this->rechazo('red_local', ['host' => $host]);
            }
        }
        foreach ($direcciones as $direccion) {
            if (filter_var($direccion, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
                return $direccion;
            }
        }

        return $direcciones[0];
    }

    private function esPublica(string $direccion): bool
    {
        $binaria = filter_var($direccion, FILTER_VALIDATE_IP) === false ? false : inet_pton($direccion);
        if ($binaria === false) {
            return false;
        }
        foreach (self::IPV4_DENTRO_DE_IPV6 as $red => $desde) {
            if (strlen($binaria) === 16 && $this->perteneceA($binaria, $red)) {
                $binaria = substr($binaria, $desde, 4);
                break;
            }
        }
        foreach (self::REDES_NO_PUBLICAS as $red) {
            if ($this->perteneceA($binaria, $red)) {
                return false;
            }
        }

        return true;
    }

    private function perteneceA(string $binaria, string $red): bool
    {
        [$base, $bits] = explode('/', $red);
        $base = (string) inet_pton($base);
        if (strlen($base) !== strlen($binaria)) {
            return false;
        }
        $bits = (int) $bits;
        $enteros = intdiv($bits, 8);
        if (strncmp($binaria, $base, $enteros) !== 0) {
            return false;
        }
        $resto = $bits % 8;
        if ($resto === 0) {
            return true;
        }
        $mascara = 0xFF << (8 - $resto) & 0xFF;

        return (ord($binaria[$enteros]) & $mascara) === (ord($base[$enteros]) & $mascara);
    }

    /**
     * @param  array<string, string>  $datos
     */
    private function rechazo(string $motivo, array $datos = []): ErrorSincronizacion
    {
        return new ErrorSincronizacion(CodigoError::DestinoNoPermitido, self::PASO, __('sincronizacion.destino.'.$motivo, $datos));
    }
}
