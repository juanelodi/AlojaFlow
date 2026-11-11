<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Seguridad;

use App\Enums\ModoPropiedad;
use App\Models\AccesoUsuario;
use App\Models\Propiedad;
use App\Models\Reserva;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Los límites que se le ponen a un usuario en una propiedad (D-075): solo lo suyo, sin tocar lo ya iniciado y con plazo para cambiar o
 * cancelar. Registrar la entrada o la salida y cobrar no son «modificar»: es el trabajo de recepción. El administrador no tiene límites.
 */
final class Limites
{
    public static function de(?User $usuario, int $propiedadId): ?AccesoUsuario
    {
        if ($usuario === null || $usuario->es_administrador) {
            return null;
        }

        return Acceso::ajustesDe($usuario)[$propiedadId] ?? null;
    }

    /**
     * Las propiedades en que solo ve lo suyo. En una subordinada no aplica: allá nadie crea nada.
     *
     * @return list<int>
     */
    public static function propiedadesSoloPropias(?User $usuario): array
    {
        if ($usuario === null || $usuario->es_administrador) {
            return [];
        }

        return app(MemoriaAcceso::class)->recordar('solo-propias:'.$usuario->id, function () use ($usuario): array {
            $ids = [];
            foreach (Acceso::ajustesDe($usuario) as $propiedadId => $ajuste) {
                if ($ajuste->solo_propias) {
                    $ids[] = $propiedadId;
                }
            }

            return $ids === [] ? [] : array_map('intval', Propiedad::query()->whereIn('id', $ids)->where('modo', ModoPropiedad::Nativa->value)->pluck('id')->all());
        });
    }

    /**
     * @template TModelo of Model
     *
     * @param  Builder<TModelo>  $consulta
     * @return Builder<TModelo>
     */
    public static function reservasVisibles(Builder $consulta, ?User $usuario): Builder
    {
        $propias = self::propiedadesSoloPropias($usuario);
        if ($propias !== [] && $usuario !== null) {
            $consulta->where(fn (Builder $q) => $q->whereNotIn('propiedad_id', $propias)->orWhere('creada_por', $usuario->id));
        }

        return $consulta;
    }

    /**
     * Sus huéspedes: los que dio de alta, y los de sus reservas.
     *
     * @template TModelo of Model
     *
     * @param  Builder<TModelo>  $consulta
     * @return Builder<TModelo>
     */
    public static function huespedesVisibles(Builder $consulta, ?User $usuario): Builder
    {
        $propias = self::propiedadesSoloPropias($usuario);
        if ($propias !== [] && $usuario !== null) {
            $consulta->where(fn (Builder $q) => $q->whereNotIn('propiedad_id', $propias)
                ->orWhere('creado_por', $usuario->id)
                ->orWhereHas('reservasComoTitular', fn (Builder $r) => $r->where('creada_por', $usuario->id)));
        }

        return $consulta;
    }

    public static function puedeVer(?User $usuario, Reserva $reserva): bool
    {
        return ! in_array($reserva->propiedad_id, self::propiedadesSoloPropias($usuario), true) || $reserva->creada_por === $usuario?->id;
    }

    public static function puedeModificar(?User $usuario, Reserva $reserva): bool
    {
        return self::permite($usuario, $reserva, 'horas_modificar');
    }

    public static function puedeCancelar(?User $usuario, Reserva $reserva): bool
    {
        return self::permite($usuario, $reserva, 'horas_cancelar');
    }

    private static function permite(?User $usuario, Reserva $reserva, string $plazo): bool
    {
        $limite = self::de($usuario, $reserva->propiedad_id);
        if ($limite === null) {
            return true;
        }
        if (! self::puedeVer($usuario, $reserva)) {
            return false;
        }
        if ($limite->sin_iniciadas && ($reserva->entrada_registrada_en !== null || $reserva->llegada->format('Y-m-d') <= $reserva->propiedad->hoy()->format('Y-m-d'))) {
            return false;
        }
        $horas = $limite->getAttribute($plazo);

        return $horas === null || $reserva->creado_en === null || $reserva->creado_en->gt(now()->subHours((int) $horas));
    }
}
