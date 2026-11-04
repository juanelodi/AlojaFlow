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
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use SensitiveParameter;

/**
 * Stripe Checkout, por su API REST y sin SDK (D-042). Programado según su documentación; hasta
 * probarlo con llaves de prueba reales solo está comprobado con respuestas simuladas.
 */
final class ProveedorStripe implements ProveedorPago
{
    private const API = 'https://api.stripe.com/v1/';

    private const TOLERANCIA_SEGUNDOS = 300;

    public function __construct(
        #[SensitiveParameter] private readonly string $secreta,
        #[SensitiveParameter] private readonly ?string $secretoAvisos = null,
    ) {
    }

    public function nombre(): string
    {
        return Pasarelas::STRIPE;
    }

    public function iniciar(Reserva $reserva, int $importe, string $concepto, string $urlExito, string $urlCancelar, string $urlAviso, string $referenciaInterna): InicioPago
    {
        $separador = str_contains($urlExito, '?') ? '&' : '?';
        try {
            $respuesta = $this->cliente()->asForm()->post('checkout/sessions', [
                'mode' => 'payment',
                'success_url' => $urlExito.$separador.'session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => $urlCancelar,
                'client_reference_id' => $referenciaInterna,
                'line_items' => [[
                    'quantity' => 1,
                    'price_data' => [
                        'currency' => strtolower($reserva->moneda),
                        'unit_amount' => $importe,
                        'product_data' => ['name' => mb_substr($concepto, 0, 200)],
                    ],
                ]],
            ]);
        } catch (ConnectionException) {
            throw new ErrorPago(__('motor.error.pasarela'));
        }
        if (! $respuesta->successful() || ! is_string($respuesta->json('url')) || ! is_string($respuesta->json('id'))) {
            report(new ErrorPago('Stripe rechazó el cobro: '.$respuesta->status().' '.(string) $respuesta->json('error.code')));

            throw new ErrorPago(__('motor.error.pasarela'));
        }

        return new InicioPago((string) $respuesta->json('id'), (string) $respuesta->json('url'));
    }

    public function consultarRetorno(Request $request, string $referencia): ?ResultadoPago
    {
        try {
            $respuesta = $this->cliente()->get('checkout/sessions/'.rawurlencode($referencia));
        } catch (ConnectionException) {
            return null;
        }

        return $respuesta->successful() ? $this->resultado((array) $respuesta->json()) : null;
    }

    public function procesarAviso(Request $request): ?ResultadoPago
    {
        if (blank($this->secretoAvisos)) {
            throw new ErrorPago('Falta el secreto de los avisos de Stripe.');
        }
        $this->comprobarFirma($request->getContent(), (string) $request->header('Stripe-Signature'));

        $evento = (array) $request->json()->all();
        if (! in_array($evento['type'] ?? '', ['checkout.session.completed', 'checkout.session.async_payment_succeeded'], true)) {
            return null;
        }

        return $this->resultado((array) ($evento['data']['object'] ?? []));
    }

    /**
     * @param  array<string, mixed>  $sesion
     */
    private function resultado(array $sesion): ?ResultadoPago
    {
        if (! isset($sesion['id'])) {
            return null;
        }

        return new ResultadoPago(
            (string) $sesion['id'],
            ($sesion['payment_status'] ?? '') === 'paid',
            (int) ($sesion['amount_total'] ?? 0),
            strtoupper((string) ($sesion['currency'] ?? '')),
            isset($sesion['payment_intent']) ? (string) $sesion['payment_intent'] : null,
        );
    }

    private function comprobarFirma(string $cuerpo, string $encabezado): void
    {
        $partes = [];
        foreach (explode(',', $encabezado) as $parte) {
            [$clave, $valor] = array_pad(explode('=', trim($parte), 2), 2, '');
            $partes[$clave][] = $valor;
        }
        $marca = (int) ($partes['t'][0] ?? 0);
        if ($marca === 0 || abs(time() - $marca) > self::TOLERANCIA_SEGUNDOS) {
            throw new ErrorPago('Aviso de Stripe vencido.');
        }
        $esperada = hash_hmac('sha256', $marca.'.'.$cuerpo, (string) $this->secretoAvisos);
        foreach ($partes['v1'] ?? [] as $firma) {
            if (hash_equals($esperada, $firma)) {
                return;
            }
        }

        throw new ErrorPago('Firma de Stripe no válida.');
    }

    private function cliente(): PendingRequest
    {
        return Http::baseUrl(self::API)->withToken($this->secreta)->timeout(20)->connectTimeout(10);
    }
}
