<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Sincronizacion\HotelDruid\Proyeccion;

use App\Sincronizacion\HotelDruid\Conversor;
use App\Sincronizacion\HotelDruid\Espejo;

/**
 * Los periodos de un año de la principal. Sus reservas no guardan fechas sino el periodo en que empiezan y el
 * periodo en que acaban (subordinacion.md C2): la llegada es el inicio del primero y la salida, el fin del último.
 */
final class CalendarioPeriodos
{
    /**
     * @param  array<int, array{0: string|null, 1: string|null}>  $periodos  identificador => [inicio, fin]
     */
    private function __construct(private readonly array $periodos)
    {
    }

    public static function delAnio(Espejo $espejo, int $anio, Conversor $conversor): self
    {
        $periodos = [];
        foreach ($espejo->filas('periodi', $anio) as $fila) {
            if (ctype_digit((string) ($fila['idperiodi'] ?? ''))) {
                $periodos[(int) $fila['idperiodi']] = [$conversor->fecha($fila['datainizio'] ?? null), $conversor->fecha($fila['datafine'] ?? null)];
            }
        }

        return new self($periodos);
    }

    /**
     * Llegada y salida de una estancia que va del periodo $primero al periodo $ultimo, o nulo si no forman una estancia.
     *
     * @return array{0: string, 1: string}|null
     */
    public function estancia(?string $primero, ?string $ultimo): ?array
    {
        $llegada = ctype_digit((string) $primero) ? ($this->periodos[(int) $primero][0] ?? null) : null;
        $salida = ctype_digit((string) $ultimo) ? ($this->periodos[(int) $ultimo][1] ?? null) : null;

        return $llegada === null || $salida === null || $salida <= $llegada ? null : [$llegada, $salida];
    }
}
