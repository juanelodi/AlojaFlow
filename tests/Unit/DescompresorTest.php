<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

use App\Sincronizacion\HotelDruid\CodigoError;
use App\Sincronizacion\HotelDruid\Descompresor;
use App\Sincronizacion\HotelDruid\ErrorSincronizacion;

it('descomprime una copia gzip', function () {
    $origen = archivoTemporal('copia.gz');
    $destino = archivoTemporal('copia.php');
    file_put_contents($origen, gzencode(str_repeat("<?php exit(); ?>\n", 100)));

    expect((new Descompresor())->descomprimirSiHaceFalta($origen, $destino, 1_000_000))->toBeTrue()
        ->and(file_get_contents($destino))->toBe(str_repeat("<?php exit(); ?>\n", 100));
});

it('deja igual una copia sin comprimir', function () {
    $origen = archivoTemporal('copia.php');
    file_put_contents($origen, "<?php exit(); ?>\n");

    expect((new Descompresor())->descomprimirSiHaceFalta($origen, archivoTemporal('x'), 1_000_000))->toBeFalse();
});

it('corta si lo descomprimido supera el límite', function () {
    $origen = archivoTemporal('bomba.gz');
    file_put_contents($origen, gzencode(str_repeat('a', 5_000_000)));

    try {
        (new Descompresor())->descomprimirSiHaceFalta($origen, archivoTemporal('x'), 1_000_000);
        $codigo = null;
    } catch (ErrorSincronizacion $e) {
        $codigo = $e->codigo;
    }

    expect($codigo)->toBe(CodigoError::TamanoExcedido);
});
