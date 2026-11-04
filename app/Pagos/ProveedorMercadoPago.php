<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Pagos;

use App\Models\Reserva;
use App\Motor\Pasarelas;
use App\Support\Dinero;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use SensitiveParameter;

/**
 * Checkout Pro de Mercado Pago, por su API REST y sin SDK (D-042). Los avisos solo dicen «mira este
 * pago»: el resultado siempre se consulta a su API. Programado según su documentación; hasta
 * probarlo con llaves de prueba reales solo está comprobado con respuestas simuladas.
 */
final class ProveedorMercadoPago implements ProveedorPago
{
    private const API = 'https://api.mercadopago.com/';

    public function __construct(
        #[SensitiveParameter] private readonly string $token,
        #[SensitiveParameter] private readonly ?string $secretoAvisos = null,
    ) {
    }

    public function nombre(): string
    {
        return Pasarelas::MERCADOPAGO;
    }

    public function iniciar(Reserva $reserva, int $importe, string $concepto, string $urlExito, string $urlCancelar, string $urlAviso, string $referenciaInterna): InicioPago
    {
        $cuerpo = [
            'items' => [[
                'title' => mb_substr($concepto, 0, 200),
                'quantity' => 1,
                'unit_price' => (float) Dinero::aDecimal($importe, $reserva->moneda),
                'currency_id' => $reserva->moneda,
            ]],
            'external_reference' => $referenciaInterna,
            'back_urls' => ['success' => $urlExito, 'pending' => $urlExito, 'failure' => $urlCancelar],
            'notification_url' => $urlAviso,
        ];
        // Mercado Pago rechaza auto_return con direcciones que no sean https.
        if (str_starts_with($urlExito, 'https://')) {
            $cuerpo['auto_return'] = 'approved';
        }

        try {
            $respuesta = $this->cliente()->post('checkout/preferences', $cuerpo);
        } catch (ConnectionException) {
            throw new ErrorPago(__('motor.error.pasarela'));
        }
        if (! $respuesta->successful() || ! is_string($respuesta->json('init_point')) || ! is_string($respuesta->json('id'))) {
            report(new ErrorPago('Mercado Pago rechazó el cobro: '.$respuesta->status().' '.(string) $respuesta->json('message')));

            throw new ErrorPago(__('motor.error.pasarela'));
        }

        return new InicioPago((string) $respuesta->json('id'), (string) $respuesta->json('init_point'));
    }

    public function consultarRetorno(Request $request, string $referencia): ?ResultadoPago
    {
        $idPago = (string) ($request->query('payment_id') ?? $request->query('collection_id') ?? '');

        return $idPago === '' ? null : $this->consultarPago($idPago);
    }

    public function procesarAviso(Request $request): ?ResultadoPago
    {
        $idPago = (string) ($request->input('data.id') ?? $request->query('data.id') ?? $request->query('id') ?? '');
        if (($request->input('type') ?? $request->query('type') ?? $request->query('topic')) !== 'payment' || $idPago === '') {
            return null;
        }
        if (filled($this->secretoAvisos)) {
            $this->comprobarFirma($request, $idPago);
        }

        return $this->consultarPago($idPago);
    }

    private function consultarPago(string $idPago): ?ResultadoPago
    {
        if (preg_match('/^[0-9]+$/', $idPago) !== 1) {
            return null;
        }
        try {
            $respuesta = $this->cliente()->get('v1/payments/'.$idPago);
        } catch (ConnectionException) {
            return null;
        }
        if (! $respuesta->successful()) {
            return null;
        }
        $pago = (array) $respuesta->json();
        $moneda = strtoupper((string) ($pago['currency_id'] ?? ''));

        return new ResultadoPago(
            // El cobro lo dice el propio pago (external_reference es el id de pagos_en_linea), no la
            // dirección: quien abre el retorno no puede aplicar un pago aprobado a otra reserva.
            'interna:'.(string) ($pago['external_reference'] ?? ''),
            ($pago['status'] ?? '') === 'approved',
            Dinero::aUnidadMenor((string) ($pago['transaction_amount'] ?? 0), $moneda === '' ? 'MXN' : $moneda),
            $moneda,
            $idPago,
        );
    }

    private function comprobarFirma(Request $request, string $idPago): void
    {
        $partes = [];
        foreach (explode(',', (string) $request->header('x-signature')) as $parte) {
            [$clave, $valor] = array_pad(explode('=', trim($parte), 2), 2, '');
            $partes[$clave] = $valor;
        }
        $manifiesto = 'id:'.mb_strtolower($idPago).';request-id:'.$request->header('x-request-id').';ts:'.($partes['ts'] ?? '').';';
        if (! hash_equals(hash_hmac('sha256', $manifiesto, (string) $this->secretoAvisos), (string) ($partes['v1'] ?? ''))) {
            throw new ErrorPago('Firma de Mercado Pago no válida.');
        }
    }

    private function cliente(): PendingRequest
    {
        return Http::baseUrl(self::API)->withToken($this->token)->acceptJson()->timeout(20)->connectTimeout(10);
    }
}
