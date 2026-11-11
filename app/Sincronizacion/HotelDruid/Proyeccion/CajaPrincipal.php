<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Sincronizacion\HotelDruid\Proyeccion;

use App\Enums\MetodoPago;
use App\Enums\TipoMovimiento;
use App\Sincronizacion\ProyeccionCaja;
use Illuminate\Support\Facades\DB;

/**
 * La caja de la principal (subordinacion.md C6, D-078): sus cajas, los cobros de cada reserva (`soldi`) y el libro de
 * la caja (`costi`). Lo que el libro trae de una reserva ya está entre los cobros: de ahí solo se toma en qué caja
 * entró, y su concepto, que lleva el apellido del huésped, no se guarda.
 */
final class CajaPrincipal
{
    /** En `costi`, lo que viene del cobro de una reserva. */
    private const DE_UNA_RESERVA = 'p';

    private const TIPOS = ['e' => TipoMovimiento::Ingreso, 's' => TipoMovimiento::Gasto];

    /**
     * @param  array<string, int>  $reservas  clave de origen (`AAAA:idprenota`) => reserva_id
     */
    public function __construct(private readonly Contexto $contexto, private readonly array $reservas)
    {
    }

    public function proyectar(): void
    {
        $destino = new ProyeccionCaja($this->contexto->principal, $this->contexto->ahora);
        $cajas = $this->cajas($destino);

        $pagos = [];
        $movimientos = [];
        foreach ($this->contexto->espejo->anios() as $anio) {
            $cobros = $this->cobros($anio);
            $movimientos += $this->libro($anio, $cajas, $cobros);
            foreach ($cobros as $clave => $cobro) {
                $pagos[$clave] = array_diff_key($cobro, ['numero' => true]);
            }
        }
        $destino->pagos($pagos);
        $destino->movimientos($movimientos);
        $this->contexto->resultado->cuentas['pagos'] = count($pagos);
        $this->contexto->resultado->cuentas['movimientos'] = count($movimientos);
    }

    /**
     * @return array<string, int> nombre de la caja en su forma comparable => caja_id
     */
    private function cajas(ProyeccionCaja $destino): array
    {
        $cajas = [];
        $nombres = [];
        foreach ($this->contexto->espejo->filas('casse') as $fila) {
            $nombre = $this->contexto->conversor->texto($fila['nome_cassa'] ?? null, 100);
            $numero = (string) ($fila['idcasse'] ?? '');
            // Sin nombre no hay qué enseñar, y dos con el mismo chocarían: el nombre es único en la propiedad.
            if ($nombre === null || ! ctype_digit($numero) || isset($nombres[Contexto::comparable($nombre)])) {
                continue;
            }
            $nombres[Contexto::comparable($nombre)] = 'casse:'.$numero;
            $cajas['casse:'.$numero] = ['nombre' => $nombre, 'activa' => true];
        }
        $this->soltarNombres($cajas);
        $ids = $destino->cajas($cajas);

        return array_map(fn (string $clave): int => $ids[$clave], $nombres);
    }

    /**
     * El nombre de una caja es único en la propiedad. Si en la principal dos cajas intercambian el suyo, ponerlos uno
     * por uno chocaría: la que va a cambiar de nombre suelta antes el que tenía.
     *
     * @param  array<string, array<string, mixed>>  $cajas  clave de origen => nombre y activa
     */
    private function soltarNombres(array $cajas): void
    {
        $guardadas = DB::table('cajas')->where('principal_id', $this->contexto->principal->id)->whereNotNull('clave_origen')->get(['id', 'clave_origen', 'nombre']);
        foreach ($guardadas as $guardada) {
            $nuevo = $cajas[(string) $guardada->clave_origen]['nombre'] ?? null;
            if (is_string($nuevo) && Contexto::comparable($nuevo) !== Contexto::comparable((string) $guardada->nombre)) {
                DB::table('cajas')->where('id', $guardada->id)->update(['nombre' => mb_substr('~'.$guardada->id.'~'.$guardada->nombre, 0, 100)]);
            }
        }
    }

    /**
     * Los cobros de un año. En `motivazione` va `idcliente;idinizio;idfine;idprenota`; la fila que cada año dedica a
     * las reservas canceladas no es de ninguna, y lo que es de una reserva que no se proyectó tampoco entra.
     *
     * @return array<string, array<string, mixed>> clave de origen => columnas del pago, más el número de reserva
     */
    private function cobros(int $anio): array
    {
        $conversor = $this->contexto->conversor;
        $cobros = [];
        foreach ($this->contexto->espejo->filas('soldi', $anio) as $fila) {
            $partes = explode(';', (string) ($fila['motivazione'] ?? ''));
            $numero = count($partes) === 4 ? trim($partes[3]) : '';
            $reservaId = ctype_digit($numero) ? ($this->reservas[$anio.':'.$numero] ?? null) : null;
            $fecha = $this->dia($fila['data_transazione'] ?? null);
            $id = (string) ($fila['idsoldi'] ?? '');
            $importe = $conversor->importe($fila['saldo_prenota'] ?? null);
            // Una fila sin importe no es un cobro: no se anota.
            if ($reservaId === null || $fecha === null || ! ctype_digit($id) || $importe === 0) {
                continue;
            }
            $metodo = ProyeccionCaja::metodo($fila['metodo_pagamento'] ?? null);
            $cobros['soldi:'.$anio.':'.$id] = [
                'reserva_id' => $reservaId,
                'caja_id' => null,
                'fecha' => $fecha,
                'metodo' => $metodo->value,
                'importe' => $importe,
                'moneda' => $this->contexto->moneda(),
                // El método es texto libre: lo que no se reconoce queda como «otro», con lo que decía.
                'referencia' => $metodo === MetodoPago::Otro ? $conversor->texto($fila['metodo_pagamento'] ?? null, 100) : null,
                'numero' => (int) $numero,
            ];
        }

        return $cobros;
    }

    /**
     * El libro de la caja de un año: devuelve los movimientos que no son de una reserva y, de los que sí, anota en su
     * cobro la caja en que entró. Se casan por reserva, importe y fecha; el que no casa deja su cobro sin caja.
     *
     * @param  array<string, int>  $cajas
     * @param  array<string, array<string, mixed>>  $cobros
     * @return array<string, array<string, mixed>> clave de origen => columnas del movimiento
     */
    private function libro(int $anio, array $cajas, array &$cobros): array
    {
        $conversor = $this->contexto->conversor;
        $movimientos = [];
        $sinCaja = [];
        foreach ($cobros as $clave => $cobro) {
            $sinCaja[$cobro['numero'].'|'.$cobro['importe'].'|'.$cobro['fecha']][] = $clave;
        }
        foreach ($this->contexto->espejo->filas('costi', $anio) as $fila) {
            $cajaId = $cajas[Contexto::comparable((string) ($fila['nome_cassa'] ?? ''))] ?? null;
            $fecha = $this->dia($fila['data_transazione'] ?? null);
            $importe = $conversor->importe($fila['val_costo'] ?? null);

            if (strtolower(trim((string) ($fila['provenienza_costo'] ?? ''))) === self::DE_UNA_RESERVA) {
                // Del concepto solo se lee el número de reserva, que es su primer número.
                if ($cajaId !== null && preg_match('/\d+/', (string) ($fila['nome_costo'] ?? ''), $partes) === 1) {
                    $llave = (int) $partes[0].'|'.$importe.'|'.$fecha;
                    $clave = isset($sinCaja[$llave]) ? array_shift($sinCaja[$llave]) : null;
                    if ($clave !== null) {
                        $cobros[$clave]['caja_id'] = $cajaId;
                    }
                }

                continue;
            }

            $tipo = self::TIPOS[strtolower(trim((string) ($fila['tipo_costo'] ?? '')))] ?? null;
            $id = (string) ($fila['idcosti'] ?? '');
            if ($tipo === null || $fecha === null || ! ctype_digit($id)) {
                continue;
            }
            $metodo = ProyeccionCaja::metodo($fila['metodo_pagamento'] ?? null);
            $movimientos['costi:'.$anio.':'.$id] = [
                'caja_id' => $cajaId,
                'fecha' => $fecha,
                'tipo' => $tipo->value,
                'concepto' => $conversor->texto($fila['nome_costo'] ?? null, 255) ?? '—',
                'metodo' => $metodo->value,
                'importe' => abs($importe),
                'moneda' => $this->contexto->moneda(),
                'referencia' => $metodo === MetodoPago::Otro ? $conversor->texto($fila['metodo_pagamento'] ?? null, 100) : null,
            ];
        }

        return $movimientos;
    }

    /**
     * El día de un movimiento, tal como lo anotó la principal en su hora local.
     */
    private function dia(?string $momento): ?string
    {
        return $this->contexto->conversor->fecha($momento);
    }
}
