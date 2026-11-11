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
use Illuminate\Http\Request;
use SensitiveParameter;

/**
 * El TPV Virtual de Redsys por redirección, con firma HMAC_SHA256_V1 (D-102). Bizum es el mismo TPV con otro método de pago. Programado según su manual de integración;
 * un cobro completo se hizo en su entorno de pruebas, con el comercio y la tarjeta de pruebas que publican; con un comercio de verdad, nunca.
 */
final class ProveedorRedsys implements PagoConFormulario, ProveedorPago
{
    private const PRUEBAS = 'https://sis-t.redsys.es:25443/sis/realizarPago';

    private const REAL = 'https://sis.redsys.es/sis/realizarPago';

    private const VERSION_FIRMA = 'HMAC_SHA256_V1';

    /** ISO 4217 numérico. Con Redsys solo se ofrece en euros: es lo que cobra un comercio español. */
    private const MONEDAS = ['EUR' => '978'];

    private const IDIOMAS = ['es' => '001', 'en' => '002', 'fr' => '004', 'de' => '005', 'it' => '007', 'pt' => '009'];

    /** Autorizada, de 0000 a 0099 (manual, anexo 8.2). */
    private const ULTIMA_RESPUESTA_BUENA = 99;

    public function __construct(
        private readonly string $comercio,
        private readonly string $terminal,
        #[SensitiveParameter] private readonly string $clave,
        private readonly bool $pruebas,
        private readonly bool $bizum = false,
    ) {
    }

    public function nombre(): string
    {
        return $this->bizum ? Pasarelas::BIZUM : Pasarelas::REDSYS;
    }

    public function iniciar(Reserva $reserva, int $importe, string $concepto, string $urlExito, string $urlCancelar, string $urlAviso, string $referenciaInterna): InicioPago
    {
        $moneda = self::MONEDAS[strtoupper($reserva->moneda)] ?? null;
        if ($moneda === null) {
            throw new ErrorPago(__('motor.error.pasarela'));
        }
        $parametros = array_filter([
            'DS_MERCHANT_AMOUNT' => (string) $importe,
            'DS_MERCHANT_CURRENCY' => $moneda,
            'DS_MERCHANT_MERCHANTCODE' => $this->comercio,
            'DS_MERCHANT_TERMINAL' => $this->terminal,
            'DS_MERCHANT_TRANSACTIONTYPE' => '0',
            'DS_MERCHANT_MERCHANTURL' => $urlAviso,
            'DS_MERCHANT_URLOK' => $urlExito,
            'DS_MERCHANT_URLKO' => $urlCancelar,
            'DS_MERCHANT_MERCHANTDATA' => $referenciaInterna,
            'DS_MERCHANT_PRODUCTDESCRIPTION' => mb_substr($concepto, 0, 125),
            'DS_MERCHANT_MERCHANTNAME' => mb_substr($reserva->propiedad->nombre, 0, 25),
            'DS_MERCHANT_TITULAR' => $reserva->titular === null ? null : mb_substr($reserva->titular->nombreCompleto(), 0, 60),
            'DS_MERCHANT_CONSUMERLANGUAGE' => self::IDIOMAS[app()->getLocale()] ?? null,
            'DS_MERCHANT_PAYMETHODS' => $this->bizum ? 'z' : null,
        ], fn (?string $valor): bool => filled($valor));

        return new InicioPago(
            $referenciaInterna,
            route('motor.ir', ['codigo' => $reserva->propiedad->codigo, 'pasarela' => $this->nombre(), 'referencia' => $referenciaInterna, 'idioma' => app()->getLocale()]),
            ['formulario' => $parametros],
        );
    }

    /**
     * Un número de pedido nuevo cada vez: Redsys no deja repetirlo, y quien vuelve de una tarjeta rechazada tiene que poder intentarlo otra vez. El cobro se reconoce
     * por los datos del comercio, que van firmados.
     */
    public function formulario(array $parametros): array
    {
        $orden = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT).strtoupper(bin2hex(random_bytes(4)));
        $datos = base64_encode((string) json_encode(['DS_MERCHANT_ORDER' => $orden] + $parametros, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return [
            'accion' => $this->pruebas ? self::PRUEBAS : self::REAL,
            'campos' => [
                'Ds_SignatureVersion' => self::VERSION_FIRMA,
                'Ds_MerchantParameters' => $datos,
                'Ds_Signature' => $this->firma($datos, $orden),
            ],
        ];
    }

    public function consultarRetorno(Request $request, string $referencia): ?ResultadoPago
    {
        // Solo manda el resultado al volver si el comercio lo tiene activado en su panel; si no, llega por el aviso.
        if (! $request->has('Ds_MerchantParameters')) {
            return null;
        }
        try {
            return $this->leer($request);
        } catch (ErrorPago) {
            return null;
        }
    }

    public function procesarAviso(Request $request): ?ResultadoPago
    {
        return $this->leer($request);
    }

    private function leer(Request $request): ?ResultadoPago
    {
        $datos = (string) $request->input('Ds_MerchantParameters');
        $firma = (string) $request->input('Ds_Signature');
        /** @var array<string, mixed> $crudo */
        $crudo = (array) json_decode((string) base64_decode(strtr($datos, '-_', '+/')), true);
        $parametros = array_change_key_case($crudo, CASE_LOWER);
        $orden = (string) ($parametros['ds_order'] ?? '');
        if ($datos === '' || $orden === '' || ! hash_equals($this->firma($datos, $orden), strtr($firma, '-_', '+/'))) {
            throw new ErrorPago('Firma de Redsys no válida.');
        }
        $referencia = urldecode((string) ($parametros['ds_merchantdata'] ?? ''));
        if ($referencia === '') {
            return null;
        }
        $respuesta = (string) ($parametros['ds_response'] ?? '');

        return new ResultadoPago(
            'interna:'.$referencia,
            ctype_digit($respuesta) && (int) $respuesta <= self::ULTIMA_RESPUESTA_BUENA,
            (int) ($parametros['ds_amount'] ?? 0),
            (string) (array_flip(self::MONEDAS)[(string) ($parametros['ds_currency'] ?? '')] ?? ''),
            filled($parametros['ds_authorisationcode'] ?? null) ? trim((string) $parametros['ds_authorisationcode']) : $orden,
            metodo: $this->bizum ? MetodoPago::Otro : MetodoPago::Tarjeta,
        );
    }

    /**
     * La clave del comercio cifra el número de pedido con 3DES, y lo que sale firma los datos: hay una clave por operación.
     */
    private function firma(string $datos, string $orden): string
    {
        $relleno = str_pad($orden, (int) ceil(strlen($orden) / 8) * 8, "\0");
        $clave = (string) openssl_encrypt($relleno, 'des-ede3-cbc', (string) base64_decode($this->clave), OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, str_repeat("\0", 8));

        return base64_encode(hash_hmac('sha256', $datos, $clave, true));
    }
}
