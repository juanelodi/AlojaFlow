<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Servicios;

use App\Enums\EstadoReserva;
use App\Enums\TipoReserva;
use App\Models\Huesped;
use App\Models\Reserva;
use App\Models\User;
use App\Seguridad\Limites;
use Illuminate\Support\Collection;

/**
 * Lo que se ve de un huésped de un vistazo: cuántas veces ha venido, cuánto ha pagado y sus reservas por año, también en las que vino de
 * acompañante. La idea de agruparlas por año viene de modifica_cliente.php de HotelDruid, reescrita. Cuenta solo lo que quien mira puede ver.
 */
final class FichaHuesped
{
    /**
     * @return array{cuentas: array{estancias: int, noches: int, total: int, pagado: int, pendiente: int, moneda: ?string, primera: ?Reserva, ultima: ?Reserva, proxima: ?Reserva}, anios: Collection<int, Collection<int, Reserva>>, titular: list<int>}
     */
    public static function de(Huesped $huesped, ?User $usuario): array
    {
        $comoTitular = Limites::reservasVisibles($huesped->reservasComoTitular()->getQuery(), $usuario)->with('unidad')->get();
        $comoAcompanante = Limites::reservasVisibles(Reserva::query()->whereHas('huespedes', fn ($h) => $h->whereKey($huesped->id)), $usuario)->with('unidad')->get();

        /** @var Collection<int, Reserva> $todas */
        $todas = $comoTitular->concat($comoAcompanante)->unique('id')->where('tipo', TipoReserva::Reserva)->sortByDesc(fn (Reserva $r): string => $r->llegada->format('Y-m-d').sprintf('%010d', $r->id))->values();
        $vigentes = $todas->filter(fn (Reserva $r): bool => $r->estado !== EstadoReserva::Cancelada && $r->eliminada_en_principal_en === null);
        // El dinero, solo de lo que pagó como titular: en la de un acompañante, el importe es de otro.
        $suyas = $vigentes->filter(fn (Reserva $r): bool => $r->titular_id === $huesped->id);
        $hoy = $huesped->propiedad->hoy()->format('Y-m-d');

        return [
            'cuentas' => [
                'estancias' => $vigentes->count(),
                'noches' => (int) $vigentes->sum(fn (Reserva $r): int => (int) $r->llegada->diffInDays($r->salida)),
                'total' => (int) $suyas->sum('total'),
                'pagado' => (int) $suyas->sum('pagado'),
                'pendiente' => (int) $suyas->sum('pendiente'),
                'moneda' => $suyas->first()->moneda ?? $huesped->propiedad->moneda,
                'primera' => $vigentes->last(),
                'ultima' => $vigentes->first(fn (Reserva $r): bool => $r->llegada->format('Y-m-d') <= $hoy),
                'proxima' => $vigentes->last(fn (Reserva $r): bool => $r->llegada->format('Y-m-d') > $hoy),
            ],
            'anios' => $todas->groupBy(fn (Reserva $r): int => (int) $r->llegada->format('Y'))->sortKeysDesc(),
            'titular' => $comoTitular->pluck('id')->map(fn (mixed $id): int => (int) $id)->values()->all(),
        ];
    }
}
