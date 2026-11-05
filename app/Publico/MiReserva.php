<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Publico;

use App\Comunicacion\Mensajero;
use App\Dinero\GestorPoliticas;
use App\Enums\EstadoReserva;
use App\Enums\NivelAviso;
use App\Enums\TipoEnlace;
use App\Models\Aviso;
use App\Models\EnlacePublico;
use App\Models\NotaReserva;
use App\Models\RegistroAuditoria;
use App\Models\Reserva;
use App\Reservas\GestorReservas;

/**
 * La página del huésped (D-105). Se entra con un enlace personal, sin cuenta, que deja de valer una semana después de la salida. No muestra documentos ni nada que no sepa
 * ya quien recibió el correo. Pedir un cambio no cambia nada: le llega al alojamiento.
 */
final class MiReserva
{
    private const DIAS_TRAS_SALIDA = 7;

    public function __construct(private readonly GestorReservas $reservas, private readonly GestorPoliticas $politicas, private readonly Mensajero $mensajero)
    {
    }

    public function enlaceDe(Reserva $reserva): EnlacePublico
    {
        $hasta = $reserva->salida->copy()->addDays(self::DIAS_TRAS_SALIDA)->endOfDay();
        $existente = EnlacePublico::query()->where('objeto_type', $reserva->getMorphClass())->where('objeto_id', $reserva->id)
            ->where('tipo', TipoEnlace::MiReserva->value)->whereNull('revocado_en')->latest('id')->first();
        if ($existente === null) {
            return EnlacePublico::nuevo($reserva->propiedad_id, TipoEnlace::MiReserva, $reserva, $hasta);
        }
        // Si le movieron las fechas, el mismo enlace sigue valiendo: es el que ya tiene en su correo.
        if ($existente->expira_en === null || ! $existente->expira_en->equalTo($hasta)) {
            $existente->update(['expira_en' => $hasta]);
        }

        return $existente;
    }

    public function urlDe(Reserva $reserva, ?string $idioma = null): string
    {
        return route('mi-reserva.ver', ['token' => $this->enlaceDe($reserva)->token()] + ($idioma !== null ? ['idioma' => $idioma] : []));
    }

    public function reservaDelToken(string $token): ?Reserva
    {
        $enlace = EnlacePublico::buscar(TipoEnlace::MiReserva, $token);
        $objeto = $enlace !== null && $enlace->vigente() ? $enlace->objeto : null;

        return $objeto instanceof Reserva ? $objeto : null;
    }

    /**
     * Solo lo que no cuesta nada ni hay que devolver: en todo lo demás decide una persona.
     */
    public function puedeCancelarSolo(Reserva $reserva): bool
    {
        $propiedad = $reserva->propiedad;
        if (! (new PaginaPublica($propiedad))->huespedPuedeCancelar() || ! $propiedad->esIndependiente()) {
            return false;
        }
        if ($reserva->estado === EstadoReserva::Cancelada || $reserva->entrada_registrada_en !== null || $reserva->pagado > 0) {
            return false;
        }
        if ($reserva->llegada->format('Y-m-d') <= $propiedad->hoy()->format('Y-m-d')) {
            return false;
        }
        $politica = $this->politicas->aplicable($reserva);

        return $politica === null || $this->politicas->penalizacionCancelacion($reserva, $politica) === 0;
    }

    public function cancelar(Reserva $reserva): bool
    {
        if (! $this->puedeCancelarSolo($reserva)) {
            return false;
        }
        $this->reservas->cancelar($reserva);
        RegistroAuditoria::anotar('reserva.cancelada_por_huesped', $reserva, ['numero' => $reserva->numero], $reserva->propiedad_id);
        $this->pedir($reserva, __('portal.nota_cancelo', [], $reserva->propiedad->idioma));

        return true;
    }

    /**
     * Le llega al alojamiento como nota de la reserva, aviso en el inicio y correo.
     */
    public function pedir(Reserva $reserva, string $mensaje): void
    {
        $propiedad = $reserva->propiedad;
        $texto = mb_substr(trim($mensaje), 0, 2000);
        NotaReserva::query()->create(['reserva_id' => $reserva->id, 'texto' => __('portal.nota_huesped', ['mensaje' => $texto], $propiedad->idioma)]);
        Aviso::abrir($propiedad->id, 'huesped_pide', NivelAviso::Informacion);
        $this->mensajero->avisarSolicitud($reserva, $texto, $propiedad->correo);
    }
}
