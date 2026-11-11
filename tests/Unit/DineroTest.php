<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

use App\Support\Dinero;

it('conoce los decimales de cada moneda', function () {
    expect(Dinero::decimales('MXN'))->toBe(2)
        ->and(Dinero::decimales('EUR'))->toBe(2)
        ->and(Dinero::decimales('CLP'))->toBe(0);
});

it('convierte a la unidad menor redondeando', function () {
    expect(Dinero::aUnidadMenor('1234.5', 'MXN'))->toBe(123450)
        ->and(Dinero::aUnidadMenor('0.005', 'MXN'))->toBe(1)
        ->and(Dinero::aUnidadMenor('15000', 'CLP'))->toBe(15000)
        ->and(Dinero::aUnidadMenor(null, 'MXN'))->toBe(0)
        ->and(Dinero::aUnidadMenor('abc', 'MXN'))->toBe(0);
});

it('reparte un total sin perder centavos', function () {
    expect(Dinero::repartir(1000, 3))->toBe([334, 333, 333])
        ->and(array_sum(Dinero::repartir(99_999, 7)))->toBe(99_999)
        ->and(Dinero::repartir(-100, 3))->toBe([-34, -33, -33])
        ->and(Dinero::repartir(500, 0))->toBe([]);
});
