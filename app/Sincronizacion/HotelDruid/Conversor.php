<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Sincronizacion\HotelDruid;

use App\Support\Dinero;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Convierte valores de la copia (texto) al modelo propio (subordinacion.md C2).
 */
final class Conversor
{
    public function __construct(
        private readonly string $zonaHoraria,
        private readonly string $moneda,
    ) {
    }

    public function texto(?string $valor, int $maximo): ?string
    {
        if ($valor === null) {
            return null;
        }
        $valor = trim($valor);

        return $valor === '' ? null : mb_substr($valor, 0, $maximo);
    }

    public function fecha(?string $valor): ?string
    {
        if ($valor === null || ! preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $valor, $m)) {
            return null;
        }

        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? "$m[1]-$m[2]-$m[3]" : null;
    }

    /**
     * Fecha y hora local de la principal (sin zona) → UTC en formato de base de datos.
     */
    public function fechaHora(?string $valor): ?string
    {
        if ($valor === null || ! preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(:\d{2})?$/', $valor) || str_starts_with($valor, '0000')) {
            return null;
        }

        try {
            $formato = strlen($valor) === 16 ? 'Y-m-d H:i' : 'Y-m-d H:i:s';
            $fecha = CarbonImmutable::createFromFormat($formato, $valor, $this->zonaHoraria);
        } catch (Throwable) {
            return null;
        }

        return $fecha instanceof CarbonImmutable ? $fecha->utc()->format('Y-m-d H:i:s') : null;
    }

    public function importe(?string $valor): int
    {
        return Dinero::aUnidadMenor($valor, $this->moneda);
    }

    public function porcentaje(?string $valor): ?string
    {
        return $valor !== null && is_numeric($valor) ? number_format((float) $valor, 4, '.', '') : null;
    }
}
