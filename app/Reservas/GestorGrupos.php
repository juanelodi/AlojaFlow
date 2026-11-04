<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Reservas;

use App\Enums\EstadoReserva;
use App\Enums\MetodoPago;
use App\Models\Grupo;
use App\Models\Propiedad;
use App\Models\RegistroAuditoria;
use App\Models\Reserva;
use App\Models\Unidad;
use App\Support\Huellas;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Reservas de varias unidades con un mismo coordinador: se crean todas o ninguna, y un pago del
 * grupo se reparte entre lo que debe cada una.
 */
final class GestorGrupos
{
    public function __construct(private readonly GestorReservas $reservas)
    {
    }

    /**
     * @param  list<int>  $unidades
     */
    public function crear(Propiedad $propiedad, string $nombre, int $coordinadorId, array $unidades, CarbonImmutable $llegada, CarbonImmutable $salida, int $personasPorUnidad, EstadoReserva $estado, ?string $notas = null): Grupo
    {
        if ($unidades === []) {
            throw new ReservaRechazada(__('ui.grupos.error_sin_unidades'), 'unidades');
        }

        return DB::transaction(function () use ($propiedad, $nombre, $coordinadorId, $unidades, $llegada, $salida, $personasPorUnidad, $estado, $notas): Grupo {
            $grupo = Grupo::query()->create([
                'propiedad_id' => $propiedad->id,
                'codigo' => 'GRP-'.Huellas::codigoPublico(6),
                'nombre' => $nombre,
                'coordinador_id' => $coordinadorId,
                'notas' => $notas,
            ]);
            foreach ($unidades as $unidadId) {
                $unidad = Unidad::query()->where('propiedad_id', $propiedad->id)->findOrFail($unidadId);
                $personas = $unidad->capacidad > 0 ? min($unidad->capacidad, $personasPorUnidad) : $personasPorUnidad;
                $presupuesto = Cotizador::presupuesto($unidad, $llegada, $salida, $personas);
                try {
                    $this->reservas->crear($propiedad, [
                        'unidad_id' => $unidad->id,
                        'titular_id' => $coordinadorId,
                        'llegada' => $llegada->format('Y-m-d'),
                        'salida' => $salida->format('Y-m-d'),
                        'personas' => $personas,
                        'estado' => $estado->value,
                        'total' => $presupuesto->total(),
                        'origen_texto' => __('ui.grupos.origen', ['nombre' => $nombre]),
                        'grupo_id' => $grupo->id,
                        'cargos' => [],
                    ]);
                } catch (ReservaRechazada $e) {
                    throw new ReservaRechazada(__('ui.grupos.error_unidad', ['unidad' => $unidad->nombre, 'motivo' => $e->getMessage()]), 'unidades');
                }
            }
            RegistroAuditoria::anotar('grupo.crear', $grupo, ['reservas' => count($unidades)], $propiedad->id);

            return $grupo;
        });
    }

    /**
     * @return array{total: int, pagado: int, pendiente: int, moneda: string}
     */
    public function saldo(Grupo $grupo): array
    {
        $vigentes = $grupo->reservas()->where('estado', '!=', EstadoReserva::Cancelada->value);

        return [
            'total' => (int) (clone $vigentes)->sum('total'),
            'pagado' => (int) (clone $vigentes)->sum('pagado'),
            'pendiente' => (int) (clone $vigentes)->sum('pendiente'),
            'moneda' => $grupo->propiedad->moneda,
        ];
    }

    /**
     * Reparte un cobro entre las reservas con saldo, de la primera a la última; lo que sobre va a
     * la última como saldo a favor.
     */
    public function cobrar(Grupo $grupo, int $importe, MetodoPago $metodo, CarbonImmutable $fecha, ?string $referencia = null): void
    {
        if ($importe <= 0) {
            throw new ReservaRechazada(__('ui.pagos.error_importe'), 'importe');
        }
        DB::transaction(function () use ($grupo, $importe, $metodo, $fecha, $referencia): void {
            $reservas = $grupo->reservas()->where('estado', '!=', EstadoReserva::Cancelada->value)->orderBy('id')->get();
            if ($reservas->isEmpty()) {
                throw new ReservaRechazada(__('ui.grupos.error_sin_reservas'), 'importe');
            }
            $restante = $importe;
            foreach ($reservas as $indice => $reserva) {
                /** @var Reserva $reserva */
                $parte = $indice === $reservas->count() - 1 ? $restante : min($restante, max(0, $reserva->pendiente));
                if ($parte > 0) {
                    $this->reservas->registrarPago($reserva, $parte, $metodo, $fecha, $referencia);
                    $restante -= $parte;
                }
                if ($restante === 0) {
                    break;
                }
            }
        });
    }
}
