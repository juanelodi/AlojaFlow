<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Sincronizacion\HotelDruid;

use App\Support\Dinero;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Convierte los valores de la copia, que llegan todos como texto, a los tipos del modelo propio (subordinacion.md
 * C2): textos recortados, fechas, horas en UTC e importes en la unidad menor de la moneda.
 */
final class Conversor
{
    public function __construct(
        private readonly string $zonaHoraria,
        private readonly string $moneda,
    ) {
    }

    /** Lo que cabe en una columna TEXT, que se mide en bytes y no en caracteres. */
    private const BYTES_DE_UN_TEXTO = 65535;

    /**
     * Sin espacios alrededor, nulo si queda vacío y recortado a lo que cabe en la columna.
     */
    public function texto(?string $valor, int $maximo): ?string
    {
        $texto = trim((string) $valor);
        if ($texto === '') {
            return null;
        }
        $texto = mb_substr($texto, 0, $maximo);

        // Un texto largo con acentos pasa de los bytes de la columna antes que de sus caracteres, y detendría toda la copia.
        return strlen($texto) > self::BYTES_DE_UN_TEXTO ? mb_strcut($texto, 0, self::BYTES_DE_UN_TEXTO, 'UTF-8') : $texto;
    }

    /**
     * Una fecha `AAAA-MM-DD` que exista en el calendario; lo demás es nulo.
     */
    public function fecha(?string $valor): ?string
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', trim((string) $valor), $partes) !== 1 || ! checkdate((int) $partes[2], (int) $partes[3], (int) $partes[1])) {
            return null;
        }

        return $partes[0];
    }

    /**
     * Las fechas con hora de la principal están en su hora local y no dicen la zona: se leen con la de la propiedad y se
     * devuelven en UTC.
     */
    public function fechaHora(?string $valor): ?string
    {
        $texto = trim((string) $valor);
        if ($this->fecha($texto) === null || preg_match('/^\d{4}-\d{2}-\d{2}(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?/', $texto, $partes) !== 1) {
            return null;
        }
        $hora = sprintf('%02d:%02d:%02d', (int) ($partes[1] ?? 0), (int) ($partes[2] ?? 0), (int) ($partes[3] ?? 0));
        try {
            $local = CarbonImmutable::createFromFormat('Y-m-d H:i:s', substr($texto, 0, 10).' '.$hora, $this->zonaHoraria);
        } catch (Throwable) {
            return null;
        }

        return $local === null ? null : $local->utc()->format('Y-m-d H:i:s');
    }

    /**
     * Un decimal de la principal («1234.5») en la unidad menor de la moneda, mitad hacia arriba. Lo que no es un número es cero.
     */
    public function importe(?string $valor): int
    {
        return Dinero::aUnidadMenor(trim((string) $valor), $this->moneda);
    }

    /**
     * Un porcentaje con cuatro decimales, como se guarda; nulo si la principal no trae ninguno.
     */
    public function porcentaje(?string $valor): ?string
    {
        $texto = trim((string) $valor);
        if (! is_numeric($texto)) {
            return null;
        }

        return number_format(min(999.9999, max(0.0, (float) $texto)), 4, '.', '');
    }
}
