<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Pages\Concerns;

use App\Servicios\Operacion;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Pantallas con un periodo de consulta (Indicadores, Informes). Empieza en el mes en curso.
 *
 * @property string $desde
 * @property string $hasta
 */
trait ConRangoDeFechas
{
    private const DIAS_MAXIMOS_RANGO = 731;

    public function mount(): void
    {
        $hoy = Operacion::hoyDe($this->propiedadUnica());
        $this->desde = $this->desde !== '' ? $this->desde : $hoy->startOfMonth()->format('Y-m-d');
        $this->hasta = $this->hasta !== '' ? $this->hasta : $hoy->endOfMonth()->format('Y-m-d');
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}|null
     */
    public function rango(): ?array
    {
        try {
            $desde = CarbonImmutable::createFromFormat('!Y-m-d', $this->desde);
            $hasta = CarbonImmutable::createFromFormat('!Y-m-d', $this->hasta);
        } catch (Throwable) {
            return null;
        }
        if (! $desde || ! $hasta || $hasta->lt($desde) || $desde->diffInDays($hasta) > self::DIAS_MAXIMOS_RANGO) {
            return null;
        }

        return [$desde, $hasta];
    }
}
