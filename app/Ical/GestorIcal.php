<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Ical;

use App\Enums\EstadoFuenteIcal;
use App\Enums\EstadoReserva;
use App\Enums\NivelAviso;
use App\Enums\TipoChoque;
use App\Enums\TipoEnlace;
use App\Enums\TipoReserva;
use App\Models\Aviso;
use App\Models\BloqueoExterno;
use App\Models\CalendarioExportado;
use App\Models\Choque;
use App\Models\EnlacePublico;
use App\Models\FuenteIcal;
use App\Models\Propiedad;
use App\Models\Reserva;
use App\Models\Unidad;
use App\Seguridad\ClienteExterno;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Component\VEvent;
use Throwable;

/**
 * Calendarios iCal (M7): lo que ven los portales, lo que traen y los choques entre ambos.
 * Los portales nunca reciben datos personales: solo fechas ocupadas.
 */
final class GestorIcal
{
    private const BYTES_MAXIMOS = 5 * 1024 * 1024;

    /** Fallos seguidos antes de avisar: un portal puede fallar un rato sin que importe. */
    private const MINUTOS_PARA_AVISAR = 180;

    public function __construct(private readonly ClienteExterno $cliente, private readonly LectorIcal $lector)
    {
    }

    public function calendarioDe(Unidad $unidad): CalendarioExportado
    {
        return CalendarioExportado::query()->with('enlace')->firstOrCreate(
            ['unidad_id' => $unidad->id],
            ['enlace_publico_id' => EnlacePublico::nuevo($unidad->propiedad_id, TipoEnlace::Ical, $unidad)->id],
        );
    }

    /**
     * Da un enlace nuevo a todas las unidades: los anteriores dejan de funcionar.
     */
    public function regenerarEnlaces(Propiedad $propiedad): void
    {
        DB::transaction(function () use ($propiedad): void {
            $calendarios = CalendarioExportado::query()->with('enlace')->whereIn('unidad_id', Unidad::query()->where('propiedad_id', $propiedad->id)->select('id'))->get();
            foreach ($calendarios as $calendario) {
                $calendario->enlace->update(['revocado_en' => now('UTC')]);
                $calendario->update(['enlace_publico_id' => EnlacePublico::nuevo($propiedad->id, TipoEnlace::Ical, $calendario->unidad)->id]);
            }
        });
    }

    public function exportar(CalendarioExportado $calendario): string
    {
        $unidad = $calendario->unidad;
        $hoy = $unidad->propiedad->hoy()->format('Y-m-d');
        $ical = new VCalendar(['PRODID' => '-//AlojaFlow//AlojaFlow '.config('alojaflow.version').'//ES', 'CALSCALE' => 'GREGORIAN']);
        $anfitrion = (string) parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'alojaflow';

        $reservas = Reserva::query()->where('unidad_id', $unidad->id)->where('estado', '!=', EstadoReserva::Cancelada->value)->whereNull('eliminada_en_principal_en')
            ->where('salida', '>=', $hoy)
            ->when(! $calendario->incluir_bloqueos, fn ($q) => $q->where('tipo', TipoReserva::Reserva->value))
            ->orderBy('llegada')->get(['id', 'llegada', 'salida', 'actualizado_en']);
        foreach ($reservas as $reserva) {
            $this->anadirEvento($ical, $this->uid('reserva-'.$reserva->id, $anfitrion), $reserva->llegada, $reserva->salida);
        }
        // Lo que se ocupó en otro portal también debe verse cerrado en este.
        if ($calendario->incluir_bloqueos) {
            $externos = BloqueoExterno::query()->where('unidad_id', $unidad->id)->whereNull('eliminado_en')->where('salida', '>=', $hoy)->orderBy('llegada')->get(['id', 'llegada', 'salida']);
            foreach ($externos as $externo) {
                $this->anadirEvento($ical, $this->uid('externo-'.$externo->id, $anfitrion), $externo->llegada, $externo->salida);
            }
        }

        return $ical->serialize();
    }

    /**
     * Lee un calendario externo. Si falla, los bloqueos que había se conservan.
     */
    public function leerFuente(FuenteIcal $fuente): bool
    {
        $fuente->ultimo_intento_en = now('UTC');
        try {
            $respuesta = $this->cliente->descargar($fuente->url, permitirHttp: true);
            if (! $respuesta->successful()) {
                throw new RuntimeException('HTTP '.$respuesta->status());
            }
            $cuerpo = $respuesta->body();
            if (strlen($cuerpo) > self::BYTES_MAXIMOS) {
                throw new RuntimeException(__('ical.error_tamano'));
            }
            $bloqueos = $this->lector->leer($cuerpo, $fuente->propiedad->hoy());
        } catch (Throwable $e) {
            $this->anotarFallo($fuente, $e);

            return false;
        }

        DB::transaction(function () use ($fuente, $bloqueos): void {
            $ahora = now('UTC');
            foreach ($bloqueos as $bloqueo) {
                BloqueoExterno::query()->updateOrCreate(
                    ['fuente_id' => $fuente->id, 'uid' => $bloqueo['uid']],
                    ['propiedad_id' => $fuente->propiedad_id, 'unidad_id' => $fuente->unidad_id, 'llegada' => $bloqueo['llegada'], 'salida' => $bloqueo['salida'], 'resumen' => $bloqueo['resumen'], 'visto_en' => $ahora, 'eliminado_en' => null, 'primera_vez_en' => BloqueoExterno::query()->where('fuente_id', $fuente->id)->where('uid', $bloqueo['uid'])->value('primera_vez_en') ?? $ahora],
                );
            }
            BloqueoExterno::query()->where('fuente_id', $fuente->id)->whereNull('eliminado_en')->whereNotIn('uid', array_column($bloqueos, 'uid'))
                ->update(['eliminado_en' => $ahora]);

            $fuente->estado = EstadoFuenteIcal::Correcta;
            $fuente->ultimo_exito_en = $ahora;
            $fuente->ultimo_error = null;
            $fuente->save();
        });
        Aviso::resolver($fuente->propiedad_id, 'ical_falla');

        return true;
    }

    /**
     * Las fuentes a las que les toca leerse, y la revisión de choques de sus propiedades.
     *
     * @return int calendarios leídos
     */
    public function leerPendientes(): int
    {
        $leidas = 0;
        foreach (FuenteIcal::query()->with('propiedad')->where('activa', true)->get() as $fuente) {
            if ($fuente->ultimo_intento_en !== null && $fuente->ultimo_intento_en->gt(now()->subMinutes($fuente->frecuencia_minutos))) {
                continue;
            }
            $this->leerFuente($fuente);
            $leidas++;
        }
        foreach (Propiedad::query()->where('activa', true)->get() as $propiedad) {
            $this->revisarChoques($propiedad);
        }

        return $leidas;
    }

    /**
     * @return int choques abiertos
     */
    public function revisarChoques(Propiedad $propiedad): int
    {
        $hoy = $propiedad->hoy()->format('Y-m-d');
        $vigentes = array_merge($this->choquesConBloqueos($propiedad, $hoy), $this->choquesEntreReservas($propiedad, $hoy));

        foreach ($vigentes as $clave => $datos) {
            // Con clave ya existente sigue abierto, o alguien lo dio por resuelto: no se reabre.
            if (! Choque::query()->where('clave_unica', $clave)->exists()) {
                Choque::query()->create($datos + ['propiedad_id' => $propiedad->id, 'clave_unica' => $clave, 'detectado_en' => now('UTC')]);
            }
        }
        // Lo que ya no coincide se resuelve solo; sin clave, puede volver a aparecer.
        Choque::query()->where('propiedad_id', $propiedad->id)->whereNotNull('clave_unica')
            ->whereNotIn('clave_unica', array_keys($vigentes) ?: [''])
            ->update(['clave_unica' => null, 'resuelto_en' => DB::raw("COALESCE(resuelto_en, '".now('UTC')->format('Y-m-d H:i:s')."')")]);

        $abiertos = Choque::query()->where('propiedad_id', $propiedad->id)->whereNull('resuelto_en')->count();
        if ($abiertos > 0) {
            Aviso::abrir($propiedad->id, 'choques', NivelAviso::Error);
        } else {
            Aviso::resolver($propiedad->id, 'choques');
        }

        return $abiertos;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function choquesConBloqueos(Propiedad $propiedad, string $hoy): array
    {
        $filas = DB::table('bloqueos_externos as b')
            ->join('reservas as r', function ($j): void {
                $j->on('r.unidad_id', '=', 'b.unidad_id')->whereColumn('b.llegada', '<', 'r.salida')->whereColumn('b.salida', '>', 'r.llegada');
            })
            ->where('b.propiedad_id', $propiedad->id)->whereNull('b.eliminado_en')
            ->where('r.estado', '!=', EstadoReserva::Cancelada->value)->whereNull('r.eliminada_en_principal_en')
            ->where('r.salida', '>=', $hoy)->where('b.salida', '>=', $hoy)
            // El bloqueo que la principal ya creó por ese evento no es un choque (§4.9).
            ->whereRaw("NOT (r.tipo = 'bloqueo' AND r.llegada = b.llegada AND r.salida = b.salida)")
            ->get(['b.id as bloqueo', 'r.id as reserva', 'b.unidad_id', 'b.llegada as b_llegada', 'b.salida as b_salida', 'r.llegada as r_llegada', 'r.salida as r_salida']);

        $vigentes = [];
        foreach ($filas as $fila) {
            $vigentes['b:'.$fila->bloqueo.':r:'.$fila->reserva] = [
                'unidad_id' => $fila->unidad_id,
                'desde' => max((string) $fila->b_llegada, (string) $fila->r_llegada),
                'hasta' => min((string) $fila->b_salida, (string) $fila->r_salida),
                'tipo' => TipoChoque::BloqueoReserva,
                'bloqueo_externo_id' => $fila->bloqueo,
                'reserva_id' => $fila->reserva,
            ];
        }

        return $vigentes;
    }

    /**
     * Una independiente no puede tenerlos (lo impide la base de datos); una subordinada sí: los
     * trae de la principal.
     *
     * @return array<string, array<string, mixed>>
     */
    private function choquesEntreReservas(Propiedad $propiedad, string $hoy): array
    {
        $filas = DB::table('reservas as a')
            ->join('reservas as b', function ($j): void {
                $j->on('b.unidad_id', '=', 'a.unidad_id')->whereColumn('a.id', '<', 'b.id')->whereColumn('a.llegada', '<', 'b.salida')->whereColumn('a.salida', '>', 'b.llegada');
            })
            ->where('a.propiedad_id', $propiedad->id)
            ->where('a.estado', '!=', EstadoReserva::Cancelada->value)->where('b.estado', '!=', EstadoReserva::Cancelada->value)
            ->whereNull('a.eliminada_en_principal_en')->whereNull('b.eliminada_en_principal_en')
            ->where('a.salida', '>=', $hoy)->where('b.salida', '>=', $hoy)
            ->get(['a.id as a_id', 'b.id as b_id', 'a.unidad_id', 'a.llegada as a_llegada', 'a.salida as a_salida', 'b.llegada as b_llegada', 'b.salida as b_salida']);

        $vigentes = [];
        foreach ($filas as $fila) {
            $vigentes['r:'.$fila->a_id.':r:'.$fila->b_id] = [
                'unidad_id' => $fila->unidad_id,
                'desde' => max((string) $fila->a_llegada, (string) $fila->b_llegada),
                'hasta' => min((string) $fila->a_salida, (string) $fila->b_salida),
                'tipo' => TipoChoque::ReservaReserva,
                'reserva_id' => $fila->a_id,
                'reserva2_id' => $fila->b_id,
            ];
        }

        return $vigentes;
    }

    /**
     * Estable para el portal, sin dejar ver cuántas reservas hay.
     */
    private function uid(string $interno, string $anfitrion): string
    {
        return substr(hash_hmac('sha256', $interno, (string) config('app.key')), 0, 32).'@'.$anfitrion;
    }

    private function anadirEvento(VCalendar $ical, string $uid, CarbonImmutable $llegada, CarbonImmutable $salida): void
    {
        /** @var VEvent $evento */
        $evento = $ical->add('VEVENT', ['UID' => $uid, 'SUMMARY' => __('ical.ocupado'), 'DTSTAMP' => gmdate('Ymd\THis\Z')]);
        $evento->add('DTSTART', $llegada->format('Ymd'), ['VALUE' => 'DATE']);
        $evento->add('DTEND', $salida->format('Ymd'), ['VALUE' => 'DATE']);
    }

    private function anotarFallo(FuenteIcal $fuente, Throwable $e): void
    {
        $fuente->estado = EstadoFuenteIcal::Error;
        // Sin la dirección: lleva el token secreto del portal.
        $fuente->ultimo_error = mb_substr(str_replace($fuente->url, '[…]', $e->getMessage()), 0, 500);
        $fuente->save();
        if ($fuente->ultimo_exito_en === null || $fuente->ultimo_exito_en->lt(now()->subMinutes(self::MINUTOS_PARA_AVISAR))) {
            Aviso::abrir($fuente->propiedad_id, 'ical_falla', NivelAviso::Advertencia, ['fuente' => $fuente->nombre]);
        }
    }
}
