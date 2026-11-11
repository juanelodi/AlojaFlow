<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Reservas;

use App\Enums\EstadoReserva;
use App\Enums\TipoReserva;
use App\Models\CodigoAcceso;
use App\Models\RegistroAuditoria;
use App\Models\Reserva;
use App\Models\Unidad;

/**
 * Códigos de acceso (D-156): el de la reserva o, si no tiene, el fijo de su unidad. El huésped lo ve desde el día antes de llegar
 * hasta el de salida, y va en los mensajes con {codigo_acceso}. La auditoría dice que cambió, nunca cuál es.
 */
final class CodigosAcceso
{
    public const LARGO_MAXIMO = 30;

    public static function de(Reserva $reserva): ?string
    {
        return self::propio($reserva) ?? self::deUnidad($reserva->unidad_id);
    }

    public static function propio(Reserva $reserva): ?string
    {
        return CodigoAcceso::query()->where('reserva_id', $reserva->id)->first()?->codigo;
    }

    public static function deUnidad(?int $unidadId): ?string
    {
        return $unidadId === null ? null : CodigoAcceso::query()->where('unidad_id', $unidadId)->first()?->codigo;
    }

    /**
     * Vacío lo quita, y entonces vale el de la unidad.
     */
    public static function guardar(Reserva $reserva, ?string $codigo): void
    {
        if (self::escribir(['reserva_id' => $reserva->id], $codigo)) {
            RegistroAuditoria::anotar('reserva.codigo_acceso', $reserva, ['accion' => self::limpio($codigo) === null ? 'quitar' : 'guardar'], $reserva->propiedad_id);
        }
    }

    public static function guardarDeUnidad(Unidad $unidad, ?string $codigo): void
    {
        if (self::escribir(['unidad_id' => $unidad->id], $codigo)) {
            RegistroAuditoria::anotar('unidad.codigo_acceso', $unidad, ['accion' => self::limpio($codigo) === null ? 'quitar' : 'guardar'], $unidad->propiedad_id);
        }
    }

    /**
     * Seis cifras al azar, de un generador seguro.
     */
    public static function generar(int $cifras = 6): string
    {
        $codigo = '';
        for ($i = 0; $i < $cifras; $i++) {
            $codigo .= (string) random_int(0, 9);
        }

        return $codigo;
    }

    /**
     * Desde el día antes de la llegada hasta el de salida, en la fecha de la propiedad. Una reserva cancelada no lo ve.
     */
    public static function visibleParaHuesped(Reserva $reserva): bool
    {
        if ($reserva->tipo !== TipoReserva::Reserva || $reserva->estado === EstadoReserva::Cancelada || $reserva->salidaEn() !== null) {
            return false;
        }
        $hoy = $reserva->propiedad->hoy()->format('Y-m-d');

        return $hoy >= $reserva->llegada->subDay()->format('Y-m-d') && $hoy <= $reserva->salida->format('Y-m-d');
    }

    /**
     * @param  array{reserva_id: int}|array{unidad_id: int}  $de
     * @return bool si cambió algo
     */
    private static function escribir(array $de, ?string $codigo): bool
    {
        $codigo = self::limpio($codigo);
        $actual = CodigoAcceso::query()->where($de)->first();
        if ($codigo === null) {
            return (bool) $actual?->delete();
        }
        if ($actual?->codigo === $codigo) {
            return false;
        }
        CodigoAcceso::query()->updateOrCreate($de, ['codigo' => $codigo, 'creado_por' => auth()->id()]);

        return true;
    }

    private static function limpio(?string $codigo): ?string
    {
        $codigo = trim((string) preg_replace('/\s+/u', ' ', (string) $codigo));

        return $codigo === '' ? null : mb_substr($codigo, 0, self::LARGO_MAXIMO);
    }
}
