<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Sincronizacion\HotelDruid;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Guarda en el espejo (`hd_filas`) las filas de una copia, por lotes y marcadas con su ejecución (D-027). Cada fila
 * va cifrada con la llave de la aplicación (D-059): trae documentos y domicilios tal como están en la principal.
 */
final class CargadorEspejo
{
    /** Con valores largos, un lote entero puede no caber en un paquete de MySQL en un hosting compartido. */
    private const BYTES_POR_LOTE = 2 * 1024 * 1024;

    /** @var list<array<string, int|string|null>> */
    private array $pendientes = [];

    private int $bytes = 0;

    public function __construct(
        private readonly int $principalId,
        private readonly int $ejecucionId,
        private readonly int $lote,
    ) {
    }

    /**
     * @param  array<string, string|null>  $fila  las columnas de la principal, como texto
     */
    public function agregar(string $tabla, ?int $anio, array $fila): void
    {
        // De los ajustes solo interesan los de la instalación, que son los del usuario 1: los demás son preferencias de cada quien.
        if ($tabla === 'personalizza' && (string) ($fila['idutente'] ?? '') !== '1') {
            return;
        }

        $datos = Crypt::encryptString((string) json_encode($fila, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
        $this->pendientes[] = [
            'principal_id' => $this->principalId,
            'ejecucion_id' => $this->ejecucionId,
            'tabla' => $tabla,
            'anio' => $anio,
            'datos' => $datos,
        ];
        $this->bytes += strlen($datos);
        if (count($this->pendientes) >= max(1, $this->lote) || $this->bytes >= self::BYTES_POR_LOTE) {
            $this->vaciar();
        }
    }

    /**
     * Escribe lo que quede pendiente. Hay que llamarla al terminar de leer la copia.
     */
    public function vaciar(): void
    {
        if ($this->pendientes !== []) {
            DB::table('hd_filas')->insert($this->pendientes);
        }
        $this->pendientes = [];
        $this->bytes = 0;
    }
}
