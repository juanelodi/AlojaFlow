<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Sincronizacion\HotelDruid;

use Illuminate\Support\Facades\DB;

/**
 * Guarda las filas conservadas de la copia en el espejo (hd_filas) por lotes.
 */
final class CargadorEspejo
{
    /** @var list<array{principal_id: int, ejecucion_id: int, tabla: string, anio: int|null, datos: string}> */
    private array $lote = [];

    public function __construct(
        private readonly int $principalId,
        private readonly int $ejecucionId,
        private readonly int $tamanoLote = 500,
    ) {
    }

    /**
     * @param  array<string, string|null>  $fila
     */
    public function agregar(string $tabla, ?int $anio, array $fila): void
    {
        $this->lote[] = [
            'principal_id' => $this->principalId,
            'ejecucion_id' => $this->ejecucionId,
            'tabla' => $tabla,
            'anio' => $anio,
            'datos' => (string) json_encode($fila, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
        ];

        if (count($this->lote) >= $this->tamanoLote) {
            $this->vaciar();
        }
    }

    public function vaciar(): void
    {
        if ($this->lote !== []) {
            DB::table('hd_filas')->insert($this->lote);
            $this->lote = [];
        }
    }
}
