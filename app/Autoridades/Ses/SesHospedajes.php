<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Autoridades\Ses;

use App\Autoridades\Comunicacion;
use App\Autoridades\RegistroViajeros;
use App\Autoridades\ResultadoLote;
use App\Enums\MetodoPago;
use App\Models\EnvioAutoridad;
use App\Models\Pago;
use App\Models\Propiedad;
use App\Models\Reserva;
use Illuminate\Support\Carbon;

/**
 * SES.Hospedajes, del Ministerio del Interior de España (RD 933/2021): comunicar cada reserva y cada entrada en un máximo de 24 horas (D-100).
 */
final class SesHospedajes implements RegistroViajeros
{
    /** Estados de un lote en el apartado 3.1.2: 4 en proceso y 5 pendiente no han terminado. */
    private const LOTE_SIN_ERRORES = 1;

    private const LOTE_MAL_FORMADO = 2;

    private const LOTE_ERROR_INESPERADO = 3;

    private const LOTE_CON_ERRORES = 6;

    public function clave(): string
    {
        return 'ses';
    }

    public function nombre(): string
    {
        return 'SES.Hospedajes';
    }

    public function conectadaDesde(Propiedad $propiedad): ?Carbon
    {
        $ajustes = new AjustesSes($propiedad);

        return $ajustes->conectada() ? $ajustes->desde() : null;
    }

    public function armar(Reserva $reserva, string $tipo, array $correcciones): Comunicacion
    {
        $propiedad = $reserva->propiedad;
        $llegada = Carbon::parse($reserva->llegada->format('Y-m-d'));
        $fichas = FichaViajero::deReserva($reserva, $correcciones);
        $parte = $tipo === EnvioAutoridad::PARTE;

        $faltan = [];
        $personas = [];
        foreach ($fichas as $ficha) {
            $deEsta = $ficha->faltan($tipo, $llegada);
            // De una reserva basta el titular: de los demás, «tantos como información se disponga».
            if (! $parte && ! $ficha->titular && $deEsta !== []) {
                continue;
            }
            foreach ($deEsta as $campo) {
                $faltan[] = ['persona' => $ficha->clave, 'campo' => $campo];
            }
            $personas[] = $ficha->paraMensaje($tipo, $llegada);
        }
        if ($fichas === []) {
            $faltan[] = ['persona' => '', 'campo' => 'nombre'];
        }

        $entrada = $parte && $reserva->entrada_registrada_en !== null
            ? $reserva->entrada_registrada_en->copy()->setTimezone($propiedad->zona_horaria)->format('Y-m-d\TH:i:s')
            : $reserva->llegada->format('Y-m-d').'T00:00:00';
        $contratado = $reserva->getAttribute('creado_en_origen') ?? $reserva->creado_en;
        /** @var Pago|null $pago */
        $pago = $reserva->pagos()->where('importe', '>', 0)->orderByDesc('fecha')->orderByDesc('id')->first();
        $contrato = array_filter([
            'referencia' => mb_substr((string) $reserva->numero, 0, 50),
            'fechaContrato' => Carbon::parse($contratado)->setTimezone($propiedad->zona_horaria)->format('Y-m-d'),
            'fechaEntrada' => $entrada,
            'fechaSalida' => $reserva->salida->format('Y-m-d').'T00:00:00',
            'numPersonas' => max(1, $reserva->personas),
            'numHabitaciones' => 1,
            'internet' => (new AjustesSes($propiedad))->internet(),
            'pago' => array_filter([
                'tipoPago' => $this->tipoPago($pago),
                'fechaPago' => $pago?->fecha->format('Y-m-d'),
            ]),
        ], fn (mixed $valor): bool => $valor !== null);

        $huella = hash_hmac('sha256', (string) json_encode([$tipo, $contrato['referencia'], $reserva->llegada->format('Y-m-d'), $reserva->salida->format('Y-m-d'), $contrato['numPersonas'], array_map(
            fn (array $persona): array => [$persona['nombre'] ?? null, $persona['apellido1'] ?? null, $persona['numeroDocumento'] ?? null],
            $personas,
        )]), (string) config('app.key'));

        return new Comunicacion(['contrato' => $contrato, 'personas' => $personas], $faltan, $huella);
    }

    public function enviar(Propiedad $propiedad, string $tipo, array $comunicaciones): string
    {
        $ajustes = new AjustesSes($propiedad);

        return $ajustes->cliente()->alta($tipo, MensajeSes::alta($tipo, (string) $ajustes->establecimiento(), $comunicaciones));
    }

    public function anular(Propiedad $propiedad, array $codigos): string
    {
        return (new AjustesSes($propiedad))->cliente()->anulacion(MensajeSes::anulacion($codigos));
    }

    public function consultar(Propiedad $propiedad, array $lotes): array
    {
        $resultados = [];
        foreach ((new AjustesSes($propiedad))->cliente()->consulta(MensajeSes::consulta($lotes)) as $lote => $resultado) {
            $resultados[$lote] = match ($resultado['estado']) {
                self::LOTE_SIN_ERRORES, self::LOTE_CON_ERRORES => new ResultadoLote(true, null, $resultado['comunicaciones']),
                self::LOTE_MAL_FORMADO, self::LOTE_ERROR_INESPERADO => new ResultadoLote(true, mb_substr($resultado['descripcion'], 0, 300) ?: __('autoridad.error.respuesta_rara')),
                default => new ResultadoLote(false),
            };
        }

        return $resultados;
    }

    /**
     * Sin pago todavía, se paga en destino (TIPO_PAGO). Del medio de pago se manda solo el tipo: los números de tarjeta los tiene la pasarela, no AlojaFlow, y esos
     * campos son opcionales.
     */
    private function tipoPago(?Pago $pago): string
    {
        return match ($pago?->metodo) {
            null => 'DESTI',
            MetodoPago::Efectivo => 'EFECT',
            MetodoPago::Tarjeta => 'TARJT',
            MetodoPago::Transferencia => 'TRANS',
            MetodoPago::Otro => 'OTRO',
        };
    }
}
