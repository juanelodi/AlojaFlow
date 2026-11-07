<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Limpieza;

use App\Enums\EstadoReserva;
use App\Enums\LugarOmision;
use App\Enums\TipoContacto;
use App\Enums\TipoReserva;
use App\Models\Propiedad;
use App\Models\ReglaLimpieza;
use App\Models\Reserva;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * El parte para el equipo: quién sale, quién llega y qué se limpia cada día, listo para copiar o mandar por WhatsApp. La lógica viene del
 * plugin aviso_limpieza de la 1.2.3, reescrita. Los días sin movimiento no salen, y el día de llegada y el de salida no llevan limpieza aparte.
 */
final class AvisoEquipo
{
    public const DIAS_MAXIMOS = 92;

    /** Lo que se puede añadir a cada unidad. */
    public const DETALLES = ['nombre', 'personas', 'origen', 'noches', 'telefono', 'comentario'];

    /**
     * @param  array<string, bool>  $opciones  los DETALLES, y `limpieza`
     * @param  array<string, mixed>  $formato  ver FormatoAviso; sin él, la propiedad usa el suyo
     */
    public function generar(Propiedad $propiedad, CarbonImmutable $desde, int $dias, array $opciones, ?array $formato = null): string
    {
        $formato = $formato === null ? FormatoAviso::de($propiedad->id) : FormatoAviso::limpiar($formato);
        $dias = max(1, min($dias, self::DIAS_MAXIMOS));
        $hasta = $desde->addDays($dias - 1);
        $reservas = $this->reservas($propiedad, $desde, $hasta);
        $reglas = ($opciones['limpieza'] ?? false) ? ReglaLimpieza::query()->whereIn('reserva_id', $reservas->pluck('id'))->get()->keyBy('reserva_id') : collect();

        $bloques = [];
        for ($dia = $desde; $dia->lte($hasta); $dia = $dia->addDay()) {
            // Se comparan como texto: $dia está en la zona de la propiedad, y las fechas de la reserva no tienen zona.
            $fecha = $dia->format('Y-m-d');
            $salen = $reservas->filter(fn (Reserva $r): bool => $r->salida->format('Y-m-d') === $fecha);
            $llegan = $reservas->filter(fn (Reserva $r): bool => $r->llegada->format('Y-m-d') === $fecha);
            $limpian = $reservas->filter(fn (Reserva $r): bool => $r->llegada->format('Y-m-d') < $fecha && $r->salida->format('Y-m-d') > $fecha && ($reglas->get($r->id)?->tocaEl($dia) ?? false));
            if ($salen->isEmpty() && $llegan->isEmpty() && $limpian->isEmpty()) {
                continue;
            }
            $salidas = $salen->isEmpty() ? [] : [$this->renglon($formato, 'salen', $this->lista($salen, $opciones, $formato))];
            $llegadas = $llegan->isEmpty() ? [] : [$this->renglon($formato, 'llegan', $this->lista($llegan, $opciones, $formato))];
            $bloques[] = implode("\n", [
                $this->encabezado($dia, $formato),
                ...($formato['orden'] === 'llegan' ? [...$llegadas, ...$salidas] : [...$salidas, ...$llegadas]),
                // A quien limpia le basta la unidad.
                ...($limpian->isEmpty() ? [] : [$this->renglon($formato, 'limpieza', $this->lista($limpian, [], $formato))]),
            ]);
        }

        return $bloques === [] ? __('equipo.aviso.sin_movimiento') : implode(FormatoAviso::union($formato['separador']), $bloques);
    }

    /**
     * @param  array<string, mixed>  $formato
     */
    private function encabezado(CarbonImmutable $dia, array $formato): string
    {
        return $this->conIcono((string) $formato['icono_dia'], $this->resaltar(FormatoAviso::fecha($dia, (string) $formato['fecha']), $formato));
    }

    /**
     * @param  array<string, mixed>  $formato
     */
    private function renglon(array $formato, string $clave, string $lista): string
    {
        $titulo = (string) $formato['texto_'.$clave] !== '' ? (string) $formato['texto_'.$clave] : __('equipo.aviso.'.$clave);
        $titulo = $this->resaltar($titulo.':', $formato);

        return $this->conIcono((string) $formato['icono_'.$clave], $titulo.($formato['unidades'] === 'renglones' ? "\n" : ' ').$lista);
    }

    private function conIcono(string $icono, string $texto): string
    {
        return $icono === '' ? $texto : $icono.' '.$texto;
    }

    /**
     * Entre asteriscos es negrita en WhatsApp.
     *
     * @param  array<string, mixed>  $formato
     */
    private function resaltar(string $texto, array $formato): string
    {
        return $formato['negritas'] ? '*'.$texto.'*' : $texto;
    }

    /**
     * @return Collection<int, Reserva>
     */
    private function reservas(Propiedad $propiedad, CarbonImmutable $desde, CarbonImmutable $hasta): Collection
    {
        $consulta = Reserva::query()->with(['unidad', 'titular.contactos'])
            ->where('propiedad_id', $propiedad->id)->where('tipo', TipoReserva::Reserva->value)
            ->where('estado', '!=', EstadoReserva::Cancelada->value)->whereNull('eliminada_en_principal_en')
            ->where('salida', '>=', $desde->format('Y-m-d'))->where('llegada', '<=', $hasta->format('Y-m-d'));
        Omisiones::excluir($consulta, 'titular_id', [$propiedad->id], LugarOmision::Aviso);

        // La misma estancia dos veces (una copia en dos años de la principal) no se repite.
        return $consulta->get()->unique(fn (Reserva $r): string => $r->unidad_id.'|'.$r->llegada->format('Y-m-d').'|'.$r->salida->format('Y-m-d'))
            ->sortBy(fn (Reserva $r): string => sprintf('%06d|%s', (int) ($r->unidad->orden ?? 0), $r->unidad->nombre ?? ''))->values();
    }

    /**
     * @param  Collection<int, Reserva>  $reservas
     * @param  array<string, bool>  $opciones
     * @param  array<string, mixed>  $formato
     */
    private function lista(Collection $reservas, array $opciones, array $formato): string
    {
        $unidades = $reservas->map(function (Reserva $r) use ($opciones): string {
            $detalles = $this->detalles($r, $opciones);

            return ($r->unidad->nombre ?? '').($detalles === [] ? '' : ' ('.implode(', ', $detalles).')');
        });

        return $formato['unidades'] === 'renglones' ? $unidades->map(fn (string $unidad): string => '• '.$unidad)->implode("\n") : $unidades->implode(', ');
    }

    /**
     * @param  array<string, bool>  $opciones
     * @return list<string>
     */
    private function detalles(Reserva $r, array $opciones): array
    {
        $detalles = [];
        if ($opciones['nombre'] ?? false) {
            $detalles[] = $r->titular?->nombreCompleto();
        }
        if ($opciones['personas'] ?? false) {
            $detalles[] = trans_choice('equipo.aviso.n_personas', $r->personas);
        }
        if ($opciones['noches'] ?? false) {
            $detalles[] = trans_choice('ui.comun.n_noches', (int) $r->llegada->diffInDays($r->salida));
        }
        if ($opciones['origen'] ?? false) {
            $detalles[] = $r->origen_texto;
        }
        if ($opciones['telefono'] ?? false) {
            $detalles[] = $r->titular?->contactos->firstWhere('tipo', TipoContacto::Telefono)?->valor;
        }
        if ($opciones['comentario'] ?? false) {
            $detalles[] = $r->comentario === null ? null : Str::limit($r->comentario, 120);
        }

        return array_values(array_filter($detalles, fn (?string $d): bool => filled($d)));
    }
}
