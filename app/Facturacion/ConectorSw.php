<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Facturacion;

use App\Enums\ModoFiscal;
use App\Models\EntidadFiscal;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use SensitiveParameter;

/**
 * SW Sapien, por su API y sin SDK (D-098). Se usa su servicio de emisión: el certificado de sello digital vive en la cuenta del alojamiento en SW, y aquí no
 * se guarda ni pasa por aquí. Programado según su documentación y su SDK público: hasta probarlo con una cuenta real solo está comprobado con respuestas
 * simuladas.
 */
final class ConectorSw implements ConectorPac
{
    private const SERVICIOS = [ModoFiscal::Pruebas->value => 'https://services.test.sw.com.mx', ModoFiscal::Produccion->value => 'https://services.sw.com.mx'];

    private const API = [ModoFiscal::Pruebas->value => 'https://api.test.sw.com.mx', ModoFiscal::Produccion->value => 'https://api.sw.com.mx'];

    public function __construct(#[SensitiveParameter] private readonly string $token, private readonly ModoFiscal $modo)
    {
    }

    public function timbrar(string $xml, EntidadFiscal $entidad): array
    {
        $respuesta = $this->enviar(fn () => $this->cliente(self::SERVICIOS)->attach('xml', $xml, 'xml', ['Content-Type' => 'text/xml'])->post('/cfdi33/issue/v4'));
        $cfdi = $respuesta->json('data.cfdi');
        $uuid = $respuesta->json('data.uuid');
        if (! is_string($cfdi) || $cfdi === '' || ! is_string($uuid) || $uuid === '') {
            throw new ErrorFacturacion(__('dinero.pac.respuesta_rara'));
        }
        $qr = $respuesta->json('data.qrCode');

        return ['xml' => $cfdi, 'uuid' => $uuid, 'qr' => is_string($qr) ? $qr : null];
    }

    public function cancelar(string $uuid, string $motivo, EntidadFiscal $entidad, ?string $folioSustitucion = null): array
    {
        $ruta = '/cfdi33/cancel/'.rawurlencode($entidad->identificador_fiscal).'/'.rawurlencode($uuid).'/'.rawurlencode($motivo);
        if (filled($folioSustitucion)) {
            $ruta .= '/'.rawurlencode((string) $folioSustitucion);
        }
        $respuesta = $this->enviar(fn () => $this->cliente(self::SERVICIOS)->withBody('', 'application/json')->post($ruta));
        $acuse = $respuesta->json('data.acuse');
        $folios = $respuesta->json('data.uuid');
        $codigo = is_array($folios) ? reset($folios) : null;

        return ['acuse' => is_string($acuse) ? $acuse : null, 'codigo' => is_scalar($codigo) ? (string) $codigo : null];
    }

    /**
     * Para el botón de probar: la cuenta existe, el token sirve y cuántos timbres le quedan.
     *
     * @return int|null los timbres, si la respuesta los dice
     */
    public function probar(): ?int
    {
        $respuesta = $this->enviar(fn () => $this->cliente(self::API)->get('/management/v2/api/users/balance'));
        $timbres = $respuesta->json('data.stampsBalance');

        return is_numeric($timbres) ? (int) $timbres : null;
    }

    /**
     * @param  array<string, string>  $direcciones
     */
    private function cliente(array $direcciones): PendingRequest
    {
        return Http::baseUrl($direcciones[$this->modo->value])->withToken($this->token)->acceptJson()->timeout(60)->connectTimeout(15);
    }

    private function enviar(callable $peticion): Response
    {
        try {
            /** @var Response $respuesta */
            $respuesta = $peticion();
        } catch (ConnectionException) {
            throw new ErrorFacturacion(__('dinero.pac.sin_conexion'));
        }
        if (! $respuesta->successful() || $respuesta->json('status') === 'error') {
            $detalle = trim((string) $respuesta->json('message').' '.(string) $respuesta->json('messageDetail'));

            throw new ErrorFacturacion(__('dinero.pac.rechazado', ['detalle' => $detalle === '' ? (string) $respuesta->status() : mb_substr($detalle, 0, 500)]));
        }

        return $respuesta;
    }
}
