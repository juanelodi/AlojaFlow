<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Propietarios;

use App\Enums\EstadoReserva;
use App\Enums\FaseEstadoCuenta;
use App\Enums\TipoReserva;
use App\Models\Propietario;
use App\Models\Reserva;
use Illuminate\Database\Eloquent\Builder;

/**
 * Lo que ve el dueño: su calendario, sus números y sus estados de cuenta (D-116). Nunca quién se queda en su unidad.
 */
final class PortalPropietario
{
    private const DIAS_CALENDARIO = 30;

    private const DIAS_ADELANTE = 90;

    public function __construct(private readonly CalculadorEstado $calculador)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function datos(Propietario $propietario): array
    {
        $hoy = $propietario->propiedad->hoy();
        $unidades = $propietario->unidades()->where('activa', true)->orderBy('orden')->orderBy('nombre')->get();
        $estancias = $this->estancias($propietario)->where('salida', '>=', $hoy->format('Y-m-d'))->where('llegada', '<=', $hoy->addDays(self::DIAS_ADELANTE)->format('Y-m-d'))->orderBy('llegada')->get();
        $mes = $this->calculador->calcular($propietario, $hoy->startOfMonth(), $hoy->endOfMonth()->startOfDay());

        $franjas = [];
        foreach ($unidades as $unidad) {
            $suyas = $estancias->where('unidad_id', $unidad->id);
            $dias = [];
            for ($i = 0; $i < self::DIAS_CALENDARIO; $i++) {
                $dia = $hoy->addDays($i);
                $texto = $dia->format('Y-m-d');
                $estancia = $suyas->first(fn (Reserva $r): bool => $r->llegada->format('Y-m-d') <= $texto && $r->salida->format('Y-m-d') > $texto);
                $dias[] = ['fecha' => $dia, 'estado' => $estancia === null ? 'libre' : $this->tipoDe($estancia)];
            }
            $franjas[] = ['unidad' => $unidad, 'dias' => $dias];
        }

        return [
            'unidades' => $unidades,
            'estancias' => $estancias->map(fn (Reserva $r): array => ['reserva' => $r, 'tipo' => $this->tipoDe($r), 'unidad' => $unidades->firstWhere('id', $r->unidad_id)?->nombre])->all(),
            'franjas' => $franjas,
            'mes' => $mes,
            'ocupacion' => ($mes['detalle']['noches_posibles'] ?? 0) > 0 ? round($mes['detalle']['noches_ocupadas'] / $mes['detalle']['noches_posibles'] * 100) : 0,
            'estados' => $propietario->estadosCuenta()->where('fase', '!=', FaseEstadoCuenta::Borrador->value)->orderByDesc('desde')->limit(36)->get(),
            'hoy' => $hoy,
        ];
    }

    /**
     * Lo que ocupa sus unidades, venga de donde venga.
     *
     * @return Builder<Reserva>
     */
    public function estancias(Propietario $propietario): Builder
    {
        return Reserva::query()->whereIn('unidad_id', $propietario->unidades()->select('id'))->where('estado', '!=', EstadoReserva::Cancelada->value)->whereNull('eliminada_en_principal_en');
    }

    private function tipoDe(Reserva $reserva): string
    {
        return match (true) {
            $reserva->tipo === TipoReserva::Reserva => 'reserva',
            $reserva->propietario_id !== null => 'dueno',
            default => 'bloqueo',
        };
    }
}
