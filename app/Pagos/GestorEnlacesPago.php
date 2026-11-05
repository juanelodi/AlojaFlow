<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Pagos;

use App\Enums\EstadoReserva;
use App\Enums\TipoEnlace;
use App\Models\EnlacePago;
use App\Models\EnlacePublico;
use App\Models\RegistroAuditoria;
use App\Models\Reserva;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Enlaces de pago por importe libre (D-085). El token va cifrado y se busca por su huella, como los demás enlaces públicos. Vencido, anulado o
 * ya pagado, no cobra: se puede pagar de más por error, y devolverlo es más trabajo que hacer otro enlace.
 */
final class GestorEnlacesPago
{
    public function crear(Reserva $reserva, int $importe, string $concepto, int $diasVigencia, ?int $cajaId): EnlacePago
    {
        return DB::transaction(function () use ($reserva, $importe, $concepto, $diasVigencia, $cajaId): EnlacePago {
            $vence = CarbonImmutable::now('UTC')->addDays(max(1, $diasVigencia));
            $enlace = EnlacePago::query()->create([
                'propiedad_id' => $reserva->propiedad_id,
                'reserva_id' => $reserva->id,
                'concepto' => mb_substr($concepto, 0, 150),
                'importe' => $importe,
                'moneda' => $reserva->moneda,
                'caja_id' => $cajaId,
                'vence_en' => $vence,
                'creado_por' => auth()->id(),
            ]);
            $publico = EnlacePublico::nuevo($reserva->propiedad_id, TipoEnlace::Pago, $enlace, $vence);
            $enlace->update(['enlace_publico_id' => $publico->id]);
            RegistroAuditoria::anotar('enlace_pago.crear', $enlace, ['importe' => $importe, 'concepto' => $enlace->concepto], $reserva->propiedad_id);

            return $enlace;
        });
    }

    public function anular(EnlacePago $enlace): void
    {
        if ($enlace->estado() !== 'vigente') {
            return;
        }
        $enlace->update(['anulado_en' => now('UTC')]);
        $enlace->enlacePublico?->update(['revocado_en' => now('UTC')]);
        RegistroAuditoria::anotar('enlace_pago.anular', $enlace, [], $enlace->propiedad_id);
    }

    public function url(EnlacePago $enlace, ?string $idioma = null): ?string
    {
        $publico = $enlace->enlacePublico;
        if ($publico === null) {
            return null;
        }

        return rtrim((string) config('app.url'), '/').route('pagar.ver', ['token' => $publico->token(), 'idioma' => $idioma], false);
    }

    /**
     * El enlace de ese token, aunque ya no se pueda pagar: quien vuelve de la pasarela después de que venció sí pagó, y a quien lo abre tarde
     * se le explica en lugar de darle un error. Si se puede pagar lo dice su estado.
     */
    public function buscar(string $token): ?EnlacePago
    {
        $publico = EnlacePublico::query()->where('token_hmac', EnlacePublico::huella($token))->where('tipo', TipoEnlace::Pago->value)->first();
        $enlace = $publico === null ? null : EnlacePago::query()->with(['reserva.propiedad'])->where('enlace_publico_id', $publico->id)->first();
        if ($enlace === null || $enlace->reserva->estado === EstadoReserva::Cancelada) {
            return null;
        }

        return $enlace;
    }
}
