<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Pagos;

use App\Enums\MetodoPago;
use App\Models\Reserva;
use App\Motor\Pasarelas;
use App\Support\Dinero;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use SensitiveParameter;

/**
 * PayPal, con su API de órdenes (Orders v2) y sin SDK (D-159). El huésped aprueba en la página de PayPal y la orden se captura al
 * volver o, si cerró la ventana, con el aviso de que la aprobó. El resultado siempre sale de la API de PayPal: un aviso solo dice
 * «mira esta orden», y si hay identificador de webhook, además se comprueba su firma con la propia API. El cobro se reconoce por la
 * referencia firmada que viaja en custom_id. Programado según su documentación; hasta probarlo con una cuenta de pruebas real solo
 * está comprobado con respuestas simuladas.
 */
final class ProveedorPayPal implements ProveedorPago
{
    private const API = 'https://api-m.paypal.com/';

    private const API_PRUEBAS = 'https://api-m.sandbox.paypal.com/';

    private ?string $ficha = null;

    public function __construct(
        private readonly string $cliente,
        #[SensitiveParameter] private readonly string $secreto,
        private readonly ?string $webhookId = null,
        private readonly bool $pruebas = false,
    ) {
    }

    public function nombre(): string
    {
        return Pasarelas::PAYPAL;
    }

    public function iniciar(Reserva $reserva, int $importe, string $concepto, string $urlExito, string $urlCancelar, string $urlAviso, string $referenciaInterna): InicioPago
    {
        $cuerpo = [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'custom_id' => $referenciaInterna,
                'description' => mb_substr($concepto, 0, 127),
                'amount' => ['currency_code' => $reserva->moneda, 'value' => Dinero::aDecimal($importe, $reserva->moneda)],
            ]],
            'payment_source' => ['paypal' => ['experience_context' => [
                'brand_name' => mb_substr($reserva->propiedad->nombre, 0, 127),
                'shipping_preference' => 'NO_SHIPPING',
                'user_action' => 'PAY_NOW',
                'return_url' => $urlExito,
                'cancel_url' => $urlCancelar,
            ]]],
        ];
        try {
            // La misma referencia no abre dos órdenes si la petición se repite.
            $respuesta = $this->api()->withHeaders(['PayPal-Request-Id' => 'orden-'.$referenciaInterna, 'Prefer' => 'return=representation'])->post('v2/checkout/orders', $cuerpo);
        } catch (ConnectionException) {
            throw new ErrorPago(__('motor.error.pasarela'));
        }
        $url = collect((array) $respuesta->json('links'))->first(fn (mixed $enlace): bool => is_array($enlace) && in_array($enlace['rel'] ?? null, ['payer-action', 'approve'], true))['href'] ?? null;
        if (! $respuesta->successful() || ! is_string($respuesta->json('id')) || ! is_string($url) || ! str_starts_with($url, 'https://')) {
            report(new ErrorPago('PayPal rechazó la orden: '.$respuesta->status().' '.(string) $respuesta->json('name')));

            throw new ErrorPago(__('motor.error.pasarela'));
        }

        return new InicioPago((string) $respuesta->json('id'), $url);
    }

    /**
     * Volvió de aprobar: se captura. La orden es la del cobro guardado, no la que diga la dirección.
     */
    public function consultarRetorno(Request $request, string $referencia): ?ResultadoPago
    {
        return $this->capturar($referencia);
    }

    public function procesarAviso(Request $request): ?ResultadoPago
    {
        $evento = (array) $request->json()->all();
        $tipo = (string) ($evento['event_type'] ?? '');
        if (! in_array($tipo, ['CHECKOUT.ORDER.APPROVED', 'PAYMENT.CAPTURE.COMPLETED', 'PAYMENT.CAPTURE.PENDING', 'PAYMENT.CAPTURE.DENIED'], true)) {
            return null;
        }
        if (filled($this->webhookId)) {
            $this->comprobarFirma($request, $evento);
        }
        $recurso = (array) ($evento['resource'] ?? []);
        // Aprobó y cerró la ventana antes de volver: se captura aquí.
        if ($tipo === 'CHECKOUT.ORDER.APPROVED') {
            return $this->capturar((string) ($recurso['id'] ?? ''));
        }

        return $this->consultar((string) ($recurso['supplementary_data']['related_ids']['order_id'] ?? ''));
    }

    private function capturar(string $orden): ?ResultadoPago
    {
        if (! self::ordenValida($orden)) {
            return null;
        }
        try {
            $respuesta = $this->api()->withHeaders(['PayPal-Request-Id' => 'captura-'.$orden, 'Prefer' => 'return=representation'])
                ->withBody('{}', 'application/json')->post('v2/checkout/orders/'.$orden.'/capture');
        } catch (ConnectionException|ErrorPago) {
            // Sin respuesta o sin token: el huésped vuelve a su reserva y el aviso de PayPal la confirma después.
            return null;
        }

        // Ya capturada (por la otra vía) o todavía sin aprobar: se pregunta cómo está.
        return $respuesta->successful() ? $this->resultado((array) $respuesta->json()) : $this->consultar($orden);
    }

    private function consultar(string $orden): ?ResultadoPago
    {
        if (! self::ordenValida($orden)) {
            return null;
        }
        try {
            $respuesta = $this->api()->get('v2/checkout/orders/'.$orden);
        } catch (ConnectionException|ErrorPago) {
            return null;
        }

        return $respuesta->successful() ? $this->resultado((array) $respuesta->json()) : null;
    }

    /**
     * @param  array<string, mixed>  $orden
     */
    private function resultado(array $orden): ?ResultadoPago
    {
        $unidad = (array) ($orden['purchase_units'][0] ?? []);
        $captura = (array) ($unidad['payments']['captures'][0] ?? []);
        $referencia = (string) ($unidad['custom_id'] ?? $captura['custom_id'] ?? '');
        if ($referencia === '') {
            return null;
        }
        if ($captura === []) {
            // Sin captura no hay pago todavía. Una orden anulada ya no se pagará.
            return new ResultadoPago('interna:'.$referencia, false, 0, '', null, fallido: ($orden['status'] ?? null) === 'VOIDED');
        }
        $moneda = strtoupper((string) ($captura['amount']['currency_code'] ?? ''));
        $estado = (string) ($captura['status'] ?? '');

        return new ResultadoPago(
            'interna:'.$referencia,
            $estado === 'COMPLETED',
            Dinero::aUnidadMenor((string) ($captura['amount']['value'] ?? 0), $moneda === '' ? 'MXN' : $moneda),
            $moneda,
            is_string($captura['id'] ?? null) ? $captura['id'] : null,
            // Un cheque electrónico tarda días: sus noches no se sueltan mientras tanto.
            enEspera: $estado === 'PENDING',
            fallido: $estado === 'DECLINED',
            metodo: MetodoPago::Tarjeta,
        );
    }

    /**
     * La firma del aviso la comprueba la propia API de PayPal con el identificador del webhook.
     *
     * @param  array<string, mixed>  $evento
     */
    private function comprobarFirma(Request $request, array $evento): void
    {
        try {
            $respuesta = $this->api()->post('v1/notifications/verify-webhook-signature', [
                'auth_algo' => (string) $request->header('PAYPAL-AUTH-ALGO'),
                'cert_url' => (string) $request->header('PAYPAL-CERT-URL'),
                'transmission_id' => (string) $request->header('PAYPAL-TRANSMISSION-ID'),
                'transmission_sig' => (string) $request->header('PAYPAL-TRANSMISSION-SIG'),
                'transmission_time' => (string) $request->header('PAYPAL-TRANSMISSION-TIME'),
                'webhook_id' => (string) $this->webhookId,
                'webhook_event' => $evento,
            ]);
        } catch (ConnectionException) {
            throw new ErrorPago('No se pudo comprobar la firma de PayPal.');
        }
        if ($respuesta->json('verification_status') !== 'SUCCESS') {
            throw new ErrorPago('Firma de PayPal no válida.');
        }
    }

    private function api(): PendingRequest
    {
        return Http::baseUrl($this->pruebas ? self::API_PRUEBAS : self::API)->withToken($this->ficha())->acceptJson()->asJson()->timeout(20)->connectTimeout(10);
    }

    /**
     * El token de acceso, una vez por petición.
     */
    private function ficha(): string
    {
        if ($this->ficha !== null) {
            return $this->ficha;
        }
        try {
            $respuesta = Http::baseUrl($this->pruebas ? self::API_PRUEBAS : self::API)->withBasicAuth($this->cliente, $this->secreto)
                ->asForm()->acceptJson()->timeout(20)->connectTimeout(10)->post('v1/oauth2/token', ['grant_type' => 'client_credentials']);
        } catch (ConnectionException) {
            throw new ErrorPago(__('motor.error.pasarela'));
        }

        return $this->ficha = self::fichaDe($respuesta);
    }

    private static function fichaDe(Response $respuesta): string
    {
        $ficha = $respuesta->json('access_token');
        if (! $respuesta->successful() || ! is_string($ficha) || $ficha === '') {
            report(new ErrorPago('PayPal no dio acceso: '.$respuesta->status()));

            throw new ErrorPago(__('motor.error.pasarela'));
        }

        return $ficha;
    }

    private static function ordenValida(string $orden): bool
    {
        return preg_match('/^[A-Z0-9]{5,40}$/', $orden) === 1;
    }
}
