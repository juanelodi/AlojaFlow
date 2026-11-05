<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Pagos;

use App\Comunicacion\Mensajero;
use App\Enums\MetodoPago;
use App\Models\Reserva;
use App\Motor\Pasarelas;
use Carbon\CarbonImmutable;
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
        /** @var array{oxxo?: bool, oxxo_dias?: int, meses?: bool, spei?: bool} */
        private readonly array $opciones = [],
    ) {
    }

    public function nombre(): string
    {
        return Pasarelas::STRIPE;
    }

    public function iniciar(Reserva $reserva, int $importe, string $concepto, string $urlExito, string $urlCancelar, string $urlAviso, string $referenciaInterna): InicioPago
    {
        $separador = str_contains($urlExito, '?') ? '&' : '?';
        $sesion = [
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
        ] + $this->opcionesDeMexico($reserva);
        try {
            $respuesta = $this->cliente()->asForm()->post('checkout/sessions', $sesion);
            // El cliente guardado es de otra cuenta de Stripe (cambiaron las llaves): se crea otro una vez.
            if (isset($sesion['customer']) && $respuesta->json('error.code') === 'resource_missing' && $respuesta->json('error.param') === 'customer') {
                $reserva->titular?->forceFill(['stripe_cliente' => null])->save();
                $respuesta = $this->cliente()->asForm()->post('checkout/sessions', $this->opcionesDeMexico($reserva) + $sesion);
            }
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
        $sesion = $this->sesion($referencia);

        return $sesion === null ? null : $this->resultado($sesion);
    }

    public function procesarAviso(Request $request): ?ResultadoPago
    {
        if (blank($this->secretoAvisos)) {
            throw new ErrorPago('Falta el secreto de los avisos de Stripe.');
        }
        $this->comprobarFirma($request->getContent(), (string) $request->header('Stripe-Signature'));

        $evento = (array) $request->json()->all();
        $tipo = $evento['type'] ?? '';
        if (! in_array($tipo, ['checkout.session.completed', 'checkout.session.async_payment_succeeded', 'checkout.session.async_payment_failed'], true)) {
            return null;
        }
        $objeto = (array) ($evento['data']['object'] ?? []);
        // El aviso no trae cómo pagó ni la ficha: se pregunta. Si no contesta, basta con lo que trae para no perder un cobro.
        $sesion = isset($objeto['id']) ? ($this->sesion((string) $objeto['id']) ?? $objeto) : $objeto;
        $resultado = $this->resultado($sesion);
        if ($tipo === 'checkout.session.async_payment_failed' && $resultado !== null) {
            return new ResultadoPago($resultado->referencia, false, $resultado->importe, $resultado->moneda, $resultado->idPago, fallido: true);
        }

        return $resultado;
    }

    /**
     * @param  array<string, mixed>  $sesion
     */
    private function resultado(array $sesion): ?ResultadoPago
    {
        if (! isset($sesion['id'])) {
            return null;
        }

        $intento = is_array($sesion['payment_intent'] ?? null) ? $sesion['payment_intent'] : null;
        $pagado = ($sesion['payment_status'] ?? '') === 'paid';
        $accion = (array) ($intento['next_action'] ?? []);
        $vence = $accion['oxxo_display_details']['expires_after'] ?? null;
        $instrucciones = $accion['oxxo_display_details']['hosted_voucher_url'] ?? $accion['display_bank_transfer_instructions']['hosted_instructions_url'] ?? null;

        return new ResultadoPago(
            (string) $sesion['id'],
            $pagado,
            (int) ($sesion['amount_total'] ?? 0),
            strtoupper((string) ($sesion['currency'] ?? '')),
            $intento !== null ? (string) ($intento['id'] ?? '') : (isset($sesion['payment_intent']) ? (string) $sesion['payment_intent'] : null),
            enEspera: ! $pagado && ($sesion['status'] ?? '') === 'complete',
            instrucciones: is_string($instrucciones) && str_starts_with($instrucciones, 'https://') ? $instrucciones : null,
            venceEn: is_numeric($vence) ? (int) $vence : null,
            metodo: match ($intento['payment_method']['type'] ?? null) {
                'card' => MetodoPago::Tarjeta,
                'customer_balance' => MetodoPago::Transferencia,
                'oxxo' => MetodoPago::Otro,
                default => null,
            },
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function sesion(string $id): ?array
    {
        try {
            $respuesta = $this->cliente()->get('checkout/sessions/'.rawurlencode($id), ['expand' => ['payment_intent.payment_method']]);
        } catch (ConnectionException) {
            return null;
        }

        return $respuesta->successful() && is_string($respuesta->json('id')) ? (array) $respuesta->json() : null;
    }

    /**
     * Lo que se ofrece en pesos (D-099). Stripe enseña lo que esté activo en la cuenta: aquí se pone lo que hace falta decirle. Solo hay transferencia
     * si la sesión lleva un cliente, porque la CLABE es de esa persona.
     *
     * @return array<string, mixed>
     */
    private function opcionesDeMexico(Reserva $reserva): array
    {
        if (strtoupper($reserva->moneda) !== 'MXN') {
            return [];
        }
        $opciones = [];
        if ($this->opciones['oxxo'] ?? false) {
            $opciones['oxxo'] = ['expires_after_days' => $this->diasDeLaFicha($reserva)];
        }
        if ($this->opciones['meses'] ?? false) {
            $opciones['card'] = ['installments' => ['enabled' => 'true']];
        }
        $cliente = ($this->opciones['spei'] ?? false) ? $this->clienteDe($reserva) : null;
        if ($cliente !== null) {
            $opciones['customer_balance'] = ['funding_type' => 'bank_transfer', 'bank_transfer' => ['type' => 'mx_bank_transfer']];
        }

        return ($cliente !== null ? ['customer' => $cliente] : []) + ($opciones !== [] ? ['payment_method_options' => $opciones] : []);
    }

    /**
     * Quien llega pasado mañana no tiene cinco días para pagar: la ficha termina, a más tardar, el día que llega.
     */
    private function diasDeLaFicha(Reserva $reserva): int
    {
        $faltan = (int) CarbonImmutable::parse($reserva->propiedad->hoy()->format('Y-m-d'))->diffInDays(CarbonImmutable::parse($reserva->llegada->format('Y-m-d')), false);

        return max(1, min(7, (int) ($this->opciones['oxxo_dias'] ?? 3), $faltan));
    }

    /**
     * Sin cliente no hay transferencia, pero sí tarjeta: un fallo aquí no le quita al huésped la forma de pagar.
     */
    private function clienteDe(Reserva $reserva): ?string
    {
        $huesped = $reserva->titular;
        if ($huesped === null) {
            return null;
        }
        if (filled($huesped->getAttribute('stripe_cliente'))) {
            return (string) $huesped->getAttribute('stripe_cliente');
        }
        try {
            $respuesta = $this->cliente()->asForm()->post('customers', array_filter(['name' => $huesped->nombreCompleto(), 'email' => Mensajero::correoDe($reserva)]));
        } catch (ConnectionException) {
            return null;
        }
        $id = $respuesta->json('id');
        if (! $respuesta->successful() || ! is_string($id)) {
            return null;
        }
        $huesped->forceFill(['stripe_cliente' => $id])->save();

        return $id;
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
