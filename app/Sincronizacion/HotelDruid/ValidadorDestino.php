<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Sincronizacion\HotelDruid;

/**
 * Valida la URL de una principal y resuelve su dirección antes de cada petición (SSRF, A6).
 * Sin «red local», solo https:// hacia direcciones públicas.
 */
class ValidadorDestino
{
    /**
     * @return array{host: string, puerto: int, ip: string}
     */
    public function validar(string $urlBase, bool $permitirRedLocal): array
    {
        $partes = parse_url($urlBase);
        $esquema = strtolower((string) ($partes['scheme'] ?? ''));
        $host = (string) ($partes['host'] ?? '');

        if ($host === '' || isset($partes['user']) || isset($partes['pass']) || isset($partes['query']) || isset($partes['fragment'])) {
            throw $this->error(__('sincronizacion.destino.url_invalida'));
        }
        if (! str_ends_with((string) ($partes['path'] ?? ''), '/')) {
            throw $this->error(__('sincronizacion.destino.sin_barra_final'));
        }
        if ($esquema !== 'https' && ! ($esquema === 'http' && $permitirRedLocal)) {
            throw $this->error(__('sincronizacion.destino.solo_https'));
        }

        $puerto = (int) ($partes['port'] ?? ($esquema === 'https' ? 443 : 80));
        $direcciones = $this->resolver(trim($host, '[]'));
        if ($direcciones === []) {
            throw new ErrorSincronizacion(CodigoError::Conexion, 'validar', __('sincronizacion.destino.sin_resolver', ['host' => $host]));
        }

        foreach ($direcciones as $ip) {
            $publica = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
            if (! $publica && ! $permitirRedLocal) {
                throw $this->error(__('sincronizacion.destino.red_local', ['host' => $host]));
            }
        }

        return ['host' => $host, 'puerto' => $puerto, 'ip' => $direcciones[0]];
    }

    /**
     * Una dirección completa que escribió un usuario (aviso, calendario): admite ruta y consulta,
     * pero solo hacia redes públicas. Devuelve lo necesario para fijar la IP en la conexión.
     *
     * @return array{host: string, puerto: int, ip: string}
     */
    public function validarEnlace(string $url, bool $permitirHttp = false): array
    {
        $partes = parse_url($url);
        $esquema = strtolower((string) ($partes['scheme'] ?? ''));
        $host = (string) ($partes['host'] ?? '');
        if ($host === '' || isset($partes['user']) || isset($partes['pass'])) {
            throw $this->error(__('sincronizacion.destino.url_invalida'));
        }
        if ($esquema !== 'https' && ! ($esquema === 'http' && $permitirHttp)) {
            throw $this->error(__('sincronizacion.destino.solo_https'));
        }
        $direcciones = $this->resolver(trim($host, '[]'));
        if ($direcciones === []) {
            throw new ErrorSincronizacion(CodigoError::Conexion, 'validar', __('sincronizacion.destino.sin_resolver', ['host' => $host]));
        }
        foreach ($direcciones as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                throw $this->error(__('sincronizacion.destino.red_local', ['host' => $host]));
            }
        }

        return ['host' => $host, 'puerto' => (int) ($partes['port'] ?? ($esquema === 'https' ? 443 : 80)), 'ip' => $direcciones[0]];
    }

    /**
     * @return list<string>
     */
    protected function resolver(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }

        $ipv4 = gethostbynamel($host);

        return $ipv4 === false ? [] : $ipv4;
    }

    private function error(string $mensaje): ErrorSincronizacion
    {
        return new ErrorSincronizacion(CodigoError::DestinoNoPermitido, 'validar', $mensaje);
    }
}
