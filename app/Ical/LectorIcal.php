<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Ical;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use RuntimeException;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Component\VEvent;
use Sabre\VObject\Reader;
use Throwable;

/**
 * Convierte un calendario iCal en fechas ocupadas. Los portales suelen poner el nombre del huésped
 * en el resumen: solo se guarda si es un texto genérico.
 */
final class LectorIcal
{
    private const RESUMEN_GENERICO = '/^(airbnb|booking(\.com)?|vrbo|expedia|closed|blocked|reserved|not available|unavailable|bloqueado|reservado|no disponible)\b[^@\d]{0,40}$/iu';

    /**
     * @return list<array{uid: string, llegada: string, salida: string, resumen: string|null}>
     */
    public function leer(string $contenido, CarbonImmutable $hoy): array
    {
        if (! str_contains(substr($contenido, 0, 2000), 'BEGIN:VCALENDAR')) {
            throw new RuntimeException(__('ical.error_formato'));
        }
        try {
            $calendario = Reader::read($contenido, Reader::OPTION_FORGIVING);
            if (! $calendario instanceof VCalendar) {
                throw new RuntimeException(__('ical.error_formato'));
            }
            $expandido = $calendario->expand($hoy->subDay()->toDateTimeImmutable(), $hoy->addYears(2)->toDateTimeImmutable());
        } catch (RuntimeException $e) {
            throw $e;
        } catch (Throwable) {
            throw new RuntimeException(__('ical.error_formato'));
        }

        $bloqueos = [];
        foreach ($expandido->select('VEVENT') as $evento) {
            if (! $evento instanceof VEvent || strtoupper($this->texto($evento, 'STATUS')) === 'CANCELLED') {
                continue;
            }
            $fechas = $this->fechas($evento);
            if ($fechas === null || $fechas[1] <= $hoy->subDay()->format('Y-m-d')) {
                continue;
            }
            $resumen = $this->texto($evento, 'SUMMARY');
            $uid = $this->texto($evento, 'UID') ?: md5($fechas[0].$fechas[1]);
            // Cada repetición de un evento recurrente es una ocupación distinta.
            $clave = mb_substr($uid, 0, 240).'#'.$fechas[0];
            $bloqueos[$clave] = [
                'uid' => $clave,
                'llegada' => $fechas[0],
                'salida' => $fechas[1],
                'resumen' => preg_match(self::RESUMEN_GENERICO, $resumen) === 1 ? mb_substr($resumen, 0, 60) : null,
            ];
        }

        return array_values($bloqueos);
    }

    private function texto(VEvent $evento, string $campo): string
    {
        $valores = $evento->select($campo);

        return $valores === [] ? '' : trim((string) reset($valores));
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    private function fechas(VEvent $evento): ?array
    {
        if (! isset($evento->DTSTART)) {
            return null;
        }
        /** @var \Sabre\VObject\Property\ICalendar\DateTime $inicioProp */
        $inicioProp = $evento->DTSTART;
        $inicio = $inicioProp->getDateTime();
        $fin = $this->fin($evento, $inicio);
        $llegada = $inicio->format('Y-m-d');
        $salida = $fin?->format('Y-m-d') ?? $llegada;
        if ($salida <= $llegada) {
            $salida = CarbonImmutable::parse($llegada)->addDay()->format('Y-m-d');
        }

        return [$llegada, $salida];
    }

    private function fin(VEvent $evento, DateTimeInterface $inicio): ?DateTimeInterface
    {
        if (isset($evento->DTEND)) {
            /** @var \Sabre\VObject\Property\ICalendar\DateTime $finProp */
            $finProp = $evento->DTEND;

            return $finProp->getDateTime();
        }
        if (isset($evento->DURATION)) {
            /** @var \Sabre\VObject\Property\ICalendar\Duration $duracion */
            $duracion = $evento->DURATION;

            return CarbonImmutable::instance($inicio)->add($duracion->getDateInterval());
        }

        return null;
    }
}
