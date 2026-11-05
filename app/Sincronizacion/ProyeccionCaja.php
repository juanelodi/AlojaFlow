<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Sincronizacion;

use App\Enums\MetodoPago;
use App\Models\Principal;
use Illuminate\Support\Facades\DB;

/**
 * Deja las cajas, los pagos y los movimientos de una subordinada igual que en la principal (D-078): el formato de cada principal lo
 * entiende su proyector, y lo de aquí es lo mismo para las dos. Corre dentro de la transacción de la sincronización.
 */
final class ProyeccionCaja
{
    private const LOTE = 200;

    public function __construct(private readonly Principal $principal, private readonly string $ahora)
    {
    }

    /**
     * El método de pago de la principal es texto libre, en cualquier idioma: se reconoce lo evidente y lo demás queda como «otro».
     */
    public static function metodo(?string $texto): MetodoPago
    {
        $texto = mb_strtolower(trim((string) $texto));

        return match (true) {
            $texto === '' => MetodoPago::Otro,
            (bool) preg_match('/efectiv|contant|cash|bar\b|especes|espèces|dinheiro/u', $texto) => MetodoPago::Efectivo,
            (bool) preg_match('/tarjeta|card|carta|carte|karte|cartão|cartao/u', $texto) => MetodoPago::Tarjeta,
            (bool) preg_match('/transfer|bonifico|virement|wire|überweisung|deposito|depósito|spei/u', $texto) => MetodoPago::Transferencia,
            default => MetodoPago::Otro,
        };
    }

    /**
     * @param  array<string, array<string, mixed>>  $cajas  clave de origen => nombre y activa
     * @return array<string, int> clave de origen => id local
     */
    public function cajas(array $cajas): array
    {
        $this->aplicar('cajas', $cajas);

        return DB::table('cajas')->where('principal_id', $this->principal->id)->whereNotNull('clave_origen')->pluck('id', 'clave_origen')
            ->map(fn (mixed $id): int => (int) $id)->all();
    }

    /**
     * @param  array<string, array<string, mixed>>  $pagos
     */
    public function pagos(array $pagos): void
    {
        $this->aplicar('pagos', $pagos);
    }

    /**
     * @param  array<string, array<string, mixed>>  $movimientos
     */
    public function movimientos(array $movimientos): void
    {
        $this->aplicar('movimientos', $movimientos);
    }

    /**
     * Compara con lo que hay: casi siempre no cambió nada, y reescribir miles de filas en cada sincronización no sale gratis.
     *
     * @param  array<string, array<string, mixed>>  $filas  clave de origen => columnas
     */
    private function aplicar(string $tabla, array $filas): void
    {
        $existentes = DB::table($tabla)->where('principal_id', $this->principal->id)->whereNotNull('clave_origen')->get()->keyBy('clave_origen');
        // Primero lo que se fue: una caja puede tomar el nombre de otra que ya no está.
        foreach ($existentes->keys()->diff(array_map('strval', array_keys($filas)))->chunk(self::LOTE) as $lote) {
            DB::table($tabla)->where('principal_id', $this->principal->id)->whereIn('clave_origen', $lote->all())->delete();
        }
        $nuevas = [];
        foreach ($filas as $clave => $columnas) {
            $existente = $existentes->get($clave);
            if ($existente === null) {
                $nuevas[] = $columnas + [
                    'propiedad_id' => $this->principal->propiedad_id,
                    'sincronizada' => true,
                    'principal_id' => $this->principal->id,
                    'clave_origen' => $clave,
                    'creado_en' => $this->ahora,
                    'actualizado_en' => $this->ahora,
                ];

                continue;
            }
            $cambios = [];
            foreach ($columnas as $columna => $valor) {
                if ((string) ($existente->{$columna} ?? '') !== (string) (is_bool($valor) ? (int) $valor : ($valor ?? ''))) {
                    $cambios[$columna] = $valor;
                }
            }
            if ($cambios !== []) {
                DB::table($tabla)->where('id', $existente->id)->update($cambios + ['actualizado_en' => $this->ahora]);
            }
        }
        foreach (array_chunk($nuevas, self::LOTE) as $lote) {
            DB::table($tabla)->insert($lote);
        }
    }
}
