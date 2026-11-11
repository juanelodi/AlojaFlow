<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Reservas;

use App\Comunicacion\Mensajero;
use App\Enums\EstadoReserva;
use App\Grupos\CalendarioPagos;
use App\Models\CuotaReserva;
use App\Models\RegistroAuditoria;
use App\Models\Reserva;
use App\Modulos\Modulo;
use App\Modulos\Modulos;
use App\Support\Formato;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * El calendario de cobros de una estancia larga (D-108). Una cuota no se marca como pagada: lo pagado se aplica a las primeras en vencer.
 */
final class Mensualidades
{
    public const MES = 'mes';

    public const SEMANA = 'semana';

    /**
     * Noches que tiene el periodo con que se reparte lo que no lo llena.
     */
    private const NOCHES_DE_REFERENCIA = [self::MES => 30, self::SEMANA => 7];

    /**
     * Parte unas fechas en periodos. Un mes va de un día al mismo día del mes siguiente, tenga las noches que tenga; lo que no llena uno queda al final.
     *
     * @return list<array{desde: CarbonImmutable, hasta: CarbonImmutable, noches: int, completo: bool}>
     */
    public static function periodos(CarbonImmutable $desde, CarbonImmutable $hasta, string $periodo): array
    {
        $lista = [];
        for ($n = 0; ; $n++) {
            $inicio = self::sumar($desde, $periodo, $n);
            if ($inicio->gte($hasta)) {
                return $lista;
            }
            $fin = self::sumar($desde, $periodo, $n + 1);
            $completo = $fin->lte($hasta);
            $fin = $completo ? $fin : $hasta;
            $lista[] = ['desde' => $inicio, 'hasta' => $fin, 'noches' => (int) $inicio->diffInDays($fin), 'completo' => $completo];
        }
    }

    /**
     * @return array{cuotas: list<array{cuota: CuotaReserva, pagado: int, estado: string}>, suma: int, total: int, cuadra: bool, pagado: int}
     */
    public static function de(Reserva $reserva): array
    {
        if (! Modulos::activo(Modulo::EstanciasLargas)) {
            return ['cuotas' => [], 'suma' => 0, 'total' => $reserva->total, 'cuadra' => true, 'pagado' => $reserva->pagado];
        }
        $cuotas = $reserva->relationLoaded('cuotas') ? $reserva->cuotas : $reserva->cuotas()->get();
        $filas = CalendarioPagos::repartir($cuotas, $reserva->pagado, $reserva->propiedad->hoy()->format('Y-m-d'));
        $suma = (int) $cuotas->sum('importe');

        return ['cuotas' => $filas, 'suma' => $suma, 'total' => $reserva->total, 'cuadra' => $filas === [] || $suma === $reserva->total, 'pagado' => $reserva->pagado];
    }

    /**
     * Lo que ya venció sin pagar y, si no hay, el siguiente pago.
     */
    public static function porPagar(Reserva $reserva): int
    {
        if ($reserva->estado === EstadoReserva::Cancelada) {
            return 0;
        }
        $hoy = $reserva->propiedad->hoy()->format('Y-m-d');
        $vencido = 0;
        $siguiente = 0;
        foreach (self::de($reserva)['cuotas'] as $fila) {
            $falta = $fila['cuota']->importe - $fila['pagado'];
            if ($fila['cuota']->vence_en->format('Y-m-d') <= $hoy) {
                $vencido += $falta;
            }
            $siguiente = $siguiente === 0 ? $falta : $siguiente;
        }

        return $vencido > 0 ? $vencido : $siguiente;
    }

    /**
     * El primer pago sin cubrir.
     *
     * @return array{cuota: CuotaReserva, pagado: int, estado: string}|null
     */
    public static function siguiente(Reserva $reserva): ?array
    {
        foreach (self::de($reserva)['cuotas'] as $fila) {
            if ($fila['estado'] !== 'pagada') {
                return $fila;
            }
        }

        return null;
    }

    /**
     * El mensaje de WhatsApp, en el idioma del huésped.
     */
    public static function recordatorio(Reserva $reserva, CuotaReserva $cuota, int $falta): string
    {
        $anterior = app()->getLocale();
        app()->setLocale(Mensajero::idiomaDe($reserva));
        $texto = __('estancias.cuotas.recordatorio', [
            'nombre' => (string) ($reserva->titular->nombre ?? ''),
            'propiedad' => $reserva->propiedad->nombre,
            'importe' => Formato::dinero($falta, $reserva->moneda),
            'periodo' => $cuota->periodo(),
            'fecha' => Formato::fecha($cuota->vence_en),
        ]);
        app()->setLocale($anterior);

        return $texto;
    }

    /**
     * Reparte el total en pagos iguales, y el último cubre lo que no llena un periodo. No se toca lo de un medidor, que se cobra
     * aparte.
     *
     * @return int cuotas creadas
     */
    public function generar(Reserva $reserva, string $periodo, CarbonImmutable $primerVencimiento): int
    {
        return DB::transaction(function () use ($reserva, $periodo, $primerVencimiento): int {
            $actual = Reserva::query()->lockForUpdate()->findOrFail($reserva->id);
            if ($actual->estado === EstadoReserva::Cancelada) {
                throw new ReservaRechazada(__('ui.reservas.error_cancelada'));
            }
            $actual->cuotas()->whereNull('cargo_id')->delete();
            $importe = $actual->total - (int) $actual->cuotas()->sum('importe');
            $filas = $importe > 0 ? $this->plan($actual, $importe, CarbonImmutable::parse($actual->llegada->format('Y-m-d')), $periodo, $primerVencimiento) : [];
            foreach ($filas as $fila) {
                $actual->cuotas()->create($fila);
            }
            RegistroAuditoria::anotar('cuota_reserva.generar', $actual, ['periodo' => $periodo, 'cuotas' => count($filas)], $actual->propiedad_id);

            return count($filas);
        });
    }

    /**
     * @return list<array{concepto: string|null, desde: string|null, hasta: string|null, importe: int, vence_en: string}>
     */
    private function plan(Reserva $reserva, int $importe, CarbonImmutable $inicio, string $periodo, CarbonImmutable $primerVencimiento): array
    {
        $periodos = self::periodos($inicio, CarbonImmutable::parse($reserva->salida->format('Y-m-d')), $periodo);
        // Sin fechas que repartir: queda como saldo.
        if ($periodos === []) {
            return [['concepto' => __('estancias.cuotas.saldo', [], $reserva->propiedad->idioma), 'desde' => null, 'hasta' => null, 'importe' => $importe, 'vence_en' => $primerVencimiento->format('Y-m-d')]];
        }

        $referencia = self::NOCHES_DE_REFERENCIA[$periodo];
        $partes = array_sum(array_map(fn (array $p): float => $p['completo'] ? 1.0 : $p['noches'] / $referencia, $periodos));
        $pago = (int) round($importe / $partes);
        $resta = $importe;
        $filas = [];
        foreach ($periodos as $i => $p) {
            // El último se lleva lo que quede, para que la suma dé justo el total.
            $parte = $i === count($periodos) - 1 ? $resta : min($pago, $resta);
            $resta -= $parte;
            if ($parte > 0) {
                $filas[] = ['concepto' => null, 'desde' => $p['desde']->format('Y-m-d'), 'hasta' => $p['hasta']->format('Y-m-d'), 'importe' => $parte, 'vence_en' => self::sumar($primerVencimiento, $periodo, $i)->format('Y-m-d')];
            }
        }

        return $filas;
    }

    private static function sumar(CarbonImmutable $fecha, string $periodo, int $cuantos): CarbonImmutable
    {
        return $periodo === self::MES ? $fecha->addMonthsNoOverflow($cuantos) : $fecha->addWeeks($cuantos);
    }
}
