<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Servicios;

use App\Comunicacion\VariablesMensaje;
use App\Enums\EstadoComprobante;
use App\Enums\EstadoGrupo;
use App\Enums\EstadoReserva;
use App\Enums\TipoContacto;
use App\Grupos\CalendarioPagos;
use App\Models\ComprobantePago;
use App\Models\Grupo;
use App\Models\MensajeGrupo;
use App\Support\Formato;

/**
 * Lo que hay que atender de los grupos que todavía no se van: pagos que revisar, cuotas que vencen, listas sin llenar, quién llega esta semana y qué han escrito.
 */
final class SeguimientoGrupos
{
    private const DIAS_AVISO = 7;

    /**
     * @param  list<int>  $propiedades
     * @return array{comprobantes: \Illuminate\Database\Eloquent\Collection<int, ComprobantePago>, cuotas: list<array<string, mixed>>, listas: list<array<string, mixed>>, llegadas: list<array<string, mixed>>, mensajes: \Illuminate\Database\Eloquent\Collection<int, MensajeGrupo>}
     */
    public static function resumen(array $propiedades): array
    {
        $grupos = Grupo::query()->with(['propiedad', 'coordinador.contactos'])->whereIn('propiedad_id', $propiedades)->where('estado', '!=', EstadoGrupo::Cancelado->value)
            ->get()->filter(fn (Grupo $g): bool => (string) $g->ultimaSalida()?->format('Y-m-d') >= $g->propiedad->hoy()->format('Y-m-d'));

        $cuotas = [];
        $listas = [];
        $llegadas = [];
        foreach ($grupos as $grupo) {
            /** @var Grupo $grupo */
            $hoy = $grupo->propiedad->hoy();
            $limite = $hoy->addDays(self::DIAS_AVISO)->format('Y-m-d');
            foreach (CalendarioPagos::de($grupo)['cuotas'] as $fila) {
                if ($fila['estado'] !== 'pagada' && $fila['cuota']->vence_en->format('Y-m-d') <= $limite) {
                    $cuotas[] = ['grupo' => $grupo, 'cuota' => $fila['cuota'], 'pagado' => $fila['pagado'], 'estado' => $fila['estado'], 'whatsapp' => self::recordatorio($grupo, $fila['cuota']->concepto, $fila['cuota']->importe - $fila['pagado'], $fila['cuota']->vence_en)];
                }
            }
            $sinResponsable = $grupo->reservas()->where('estado', '!=', EstadoReserva::Cancelada->value)
                ->where(fn ($q) => $q->whereDoesntHave('habitacionGrupo')->orWhereHas('habitacionGrupo', fn ($h) => $h->whereNull('responsable')))->count();
            if ($sinResponsable > 0) {
                $listas[] = ['grupo' => $grupo, 'faltan' => $sinResponsable];
            }
            $primera = $grupo->primeraLlegada();
            if ($primera !== null && $primera->format('Y-m-d') >= $hoy->format('Y-m-d') && $primera->format('Y-m-d') <= $limite) {
                $llegadas[] = ['grupo' => $grupo, 'llegada' => $primera];
            }
        }
        usort($cuotas, fn (array $a, array $b): int => $a['cuota']->vence_en <=> $b['cuota']->vence_en);
        usort($llegadas, fn (array $a, array $b): int => $a['llegada'] <=> $b['llegada']);

        return [
            'comprobantes' => ComprobantePago::query()->with('grupo')->whereIn('propiedad_id', $propiedades)->whereNotNull('grupo_id')->where('estado', EstadoComprobante::PorConfirmar->value)->orderBy('id')->get(),
            'cuotas' => $cuotas,
            'listas' => $listas,
            'llegadas' => $llegadas,
            'mensajes' => MensajeGrupo::query()->with('grupo')->whereHas('grupo', fn ($q) => $q->whereIn('propiedad_id', $propiedades))->where('del_coordinador', true)->whereNull('leido_en')->orderBy('id')->limit(50)->get(),
        ];
    }

    private static function recordatorio(Grupo $grupo, string $concepto, int $falta, \Carbon\CarbonImmutable $vence): ?string
    {
        $telefono = $grupo->coordinador?->contactos->firstWhere('tipo', TipoContacto::Telefono)?->valor;
        if ($telefono === null) {
            return null;
        }

        return VariablesMensaje::enlaceWhatsapp($telefono, __('grupos.seguimiento.recordatorio', [
            'grupo' => $grupo->nombre,
            'concepto' => $concepto,
            'importe' => Formato::dinero($falta, $grupo->propiedad->moneda),
            'fecha' => Formato::fecha($vence),
        ]), $grupo->propiedad->pais);
    }
}
