<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Modulos;

use App\Demo\ModoDemo;
use App\Enums\EstadoEnvio;
use App\Enums\ModoPropiedad;
use App\Models\Aviso;
use App\Models\CuotaReserva;
use App\Models\Envio;
use App\Models\FuenteIcal;
use App\Models\Grupo;
use App\Models\Lectura;
use App\Models\Propiedad;
use App\Models\Webhook;
use App\Sincronizacion\HotelDruid\Sincronizador;
use App\Support\Ajustes;

/**
 * Qué módulos están prendidos en esta instalación (D-069). Apagar uno no borra nada: sale del menú, sus
 * páginas no abren y sus tareas se detienen. Mientras nadie decide, todo está prendido.
 */
final class Modulos
{
    private const ACTIVOS = 'modulos.activos';

    private const CONOCIDOS = 'modulos.conocidos';

    private const PREGUNTAS = 'modulos.preguntas';

    public static function activo(Modulo $modulo): bool
    {
        // La demostración pública enseña todo, sin importar lo que alguien haya tocado.
        if (ModoDemo::activo()) {
            return true;
        }
        $activos = Ajustes::obtener(self::ACTIVOS);

        return ! is_array($activos) || in_array($modulo->value, $activos, true);
    }

    /**
     * Lo que no pertenece a ningún módulo siempre está disponible.
     */
    public static function disponible(?Modulo $modulo): bool
    {
        return $modulo === null || self::activo($modulo);
    }

    /**
     * @return list<Modulo>
     */
    public static function prendidos(): array
    {
        return array_values(array_filter(Modulo::cases(), fn (Modulo $m): bool => self::activo($m)));
    }

    /**
     * Guarda la lista completa de lo que queda prendido. Con sus dependencias: prender Pagos prende el Motor.
     *
     * @param  list<Modulo>  $modulos
     */
    public static function guardar(array $modulos): void
    {
        $antes = self::prendidos();
        $despues = self::conDependencias($modulos);

        Ajustes::guardar(self::ACTIVOS, array_map(fn (Modulo $m): string => $m->value, $despues));
        self::marcarConocidos();

        // Lo que estaba por salir de un módulo que se apaga no debe salir meses después, al volver a prenderlo.
        foreach ($antes as $modulo) {
            if (! in_array($modulo, $despues, true)) {
                self::alApagar($modulo);
            }
        }
    }

    /**
     * @param  list<Modulo>  $modulos
     * @return list<Modulo>
     */
    public static function conDependencias(array $modulos): array
    {
        $resultado = [];
        foreach (Modulo::cases() as $modulo) {
            $loNecesitan = array_filter($modulo->dependientes(), fn (Modulo $d): bool => in_array($d, $modulos, true));
            if (in_array($modulo, $modulos, true) || $loNecesitan !== []) {
                $resultado[] = $modulo;
            }
        }

        return $resultado;
    }

    /**
     * Al marcar una casilla: prender uno prende lo que necesita, y apagar uno apaga lo que depende de él. Mejor verlo en
     * pantalla que después.
     *
     * @param  array<string, bool>  $elegidos
     * @return array<string, bool>
     */
    public static function ajustarElegidos(array $elegidos, string $clave): array
    {
        $modulo = Modulo::tryFrom($clave);
        if ($modulo === null) {
            unset($elegidos[$clave]);

            return $elegidos;
        }
        $prendido = (bool) ($elegidos[$clave] ?? false);
        foreach ($prendido ? $modulo->requiere() : $modulo->dependientes() as $relacionado) {
            $elegidos[$relacionado->value] = $prendido;
        }

        return $elegidos;
    }

    /**
     * Módulos que llegaron con una actualización y nadie ha visto: llegan apagados y se avisa.
     *
     * @return list<Modulo>
     */
    public static function nuevos(): array
    {
        $conocidos = Ajustes::obtener(self::CONOCIDOS);
        if (! is_array($conocidos)) {
            return [];
        }

        return array_values(array_filter(Modulo::cases(), fn (Modulo $m): bool => ! in_array($m->value, $conocidos, true)));
    }

    public static function marcarConocidos(): void
    {
        Ajustes::guardar(self::CONOCIDOS, array_map(fn (Modulo $m): string => $m->value, Modulo::cases()));
    }

    /**
     * Si todavía hay que hacerle las preguntas a quien instaló.
     */
    public static function faltanPreguntas(): bool
    {
        return ! ModoDemo::activo() && Ajustes::obtener(self::PREGUNTAS) === null;
    }

    public static function marcarPreguntas(string $como): void
    {
        Ajustes::guardar(self::PREGUNTAS, ['como' => $como, 'en' => now('UTC')->toIso8601String()]);
    }

    /**
     * Cuánto se usa hoy un módulo, para que nadie apague algo sin saberlo.
     */
    public static function enUso(Modulo $modulo): int
    {
        return match ($modulo) {
            Modulo::VariasPropiedades => max(0, Propiedad::query()->where('es_demo', false)->count() - 1),
            Modulo::Subordinacion => Propiedad::query()->where('modo', ModoPropiedad::Subordinada->value)->count(),
            Modulo::Ical => FuenteIcal::query()->where('activa', true)->count(),
            Modulo::Grupos => Grupo::query()->count(),
            Modulo::EstanciasLargas => CuotaReserva::query()->count(),
            Modulo::Camping => Lectura::query()->count(),
            Modulo::Integraciones => Webhook::query()->where('activo', true)->count(),
            default => 0,
        };
    }

    private static function alApagar(Modulo $modulo): void
    {
        $avisos = match ($modulo) {
            Modulo::Ical => ['ical_falla'],
            Modulo::Subordinacion => [Sincronizador::AVISO_FALLO, Sincronizador::AVISO_SIN_INICIO_SESION, Sincronizador::AVISO_BORRADO],
            default => [],
        };
        if ($avisos !== []) {
            Aviso::query()->whereIn('tipo', $avisos)->whereNull('resuelto_en')->update(['clave_unica' => null, 'resuelto_en' => now()]);
        }

        $pendientes = Envio::query()->where('estado', EstadoEnvio::Pendiente->value);
        match ($modulo) {
            Modulo::Mensajes => $pendientes->whereNotNull('automatizacion_id')->update(['estado' => EstadoEnvio::Omitido->value, 'ultimo_error' => __('modulos.omitido_al_apagar')]),
            Modulo::Integraciones => $pendientes->whereNotNull('webhook_id')->update(['estado' => EstadoEnvio::Omitido->value, 'ultimo_error' => __('modulos.omitido_al_apagar')]),
            default => null,
        };
    }
}
