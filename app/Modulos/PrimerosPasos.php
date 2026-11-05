<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Modulos;

use App\Demo\ModoDemo;
use App\Enums\ModoPropiedad;
use App\Filament\Pages\CopiasSeguridad;
use App\Filament\Pages\Correo;
use App\Filament\Pages\MotorDeReservas;
use App\Filament\Resources\Propiedades\PropiedadResource;
use App\Filament\Resources\Tarifas\TarifaResource;
use App\Filament\Resources\Unidades\UnidadResource;
use App\Filament\Resources\Usuarios\UsuarioResource;
use App\Models\Propiedad;
use App\Models\Tarifa;
use App\Models\Unidad;
use App\Models\User;
use App\Motor\AjustesMotor;
use App\Programador\TareasPorVisita;
use App\Support\Ajustes;

/**
 * La lista de lo que falta para empezar a usar la instalación. Sale de los datos: nada se marca a mano, y
 * solo aparece lo que corresponde a los módulos prendidos.
 */
final class PrimerosPasos
{
    private const OCULTO = 'primeros_pasos.oculto';

    /**
     * @return list<array{clave: string, hecho: bool, url: string|null, comando: string|null}>
     */
    public static function de(User $usuario): array
    {
        $propiedades = Propiedad::query()->where('es_demo', false);
        $independientes = (clone $propiedades)->where('modo', ModoPropiedad::Nativa->value)->pluck('id');
        $hayIndependiente = $independientes->isNotEmpty();

        $pasos = [self::paso('propiedad', (clone $propiedades)->exists(), PropiedadResource::getUrl('create'))];
        // Las de una subordinada llegan con la copia: no hay nada que capturar.
        if ($hayIndependiente) {
            $pasos[] = self::paso('unidades', Unidad::query()->whereIn('propiedad_id', $independientes)->exists(), UnidadResource::getUrl('create'));
            $pasos[] = self::paso('tarifa', Tarifa::query()->whereIn('propiedad_id', $independientes)->exists(), TarifaResource::getUrl('create'));
        }
        if ($hayIndependiente && Modulos::activo(Modulo::Motor)) {
            $abierto = Propiedad::query()->whereIn('id', $independientes)->get()->contains(fn (Propiedad $p): bool => (new AjustesMotor($p))->activo());
            $pasos[] = self::paso('motor', $abierto, MotorDeReservas::getUrl());
        }
        $pasos[] = self::paso('correo', filled(Ajustes::obtener('correo.host')), Correo::getUrl());
        $pasos[] = self::paso('dos_pasos', filled($usuario->getAppAuthenticationSecret()), filament()->getProfileUrl());
        $pasos[] = self::paso('cron', TareasPorVisita::cronActivo(), null, TareasPorVisita::comandoCron());
        $pasos[] = self::paso('copia', Ajustes::obtener('copias.ultima') !== null, CopiasSeguridad::getUrl());
        $pasos[] = self::paso('equipo', User::query()->count() > 1, UsuarioResource::getUrl('create'));

        return $pasos;
    }

    /**
     * Lo que ve en el inicio quien administra. Vacío si ya terminó o decidió no verlo.
     *
     * @return list<array{clave: string, hecho: bool, url: string|null, comando: string|null}>
     */
    public static function mostrar(?User $usuario): array
    {
        if ($usuario === null || ! $usuario->es_administrador || ModoDemo::activo() || Ajustes::obtener(self::OCULTO) === true) {
            return [];
        }
        $pasos = self::de($usuario);
        // Invitar al equipo es opcional: quien trabaja solo también termina, y ya no se consulta nada de esto en cada visita.
        if (array_filter($pasos, fn (array $paso): bool => ! $paso['hecho'] && $paso['clave'] !== 'equipo') === []) {
            self::ocultar();

            return [];
        }

        return $pasos;
    }

    public static function ocultar(): void
    {
        Ajustes::guardar(self::OCULTO, true);
    }

    /**
     * @return array{clave: string, hecho: bool, url: string|null, comando: string|null}
     */
    private static function paso(string $clave, bool $hecho, ?string $url = null, ?string $comando = null): array
    {
        return ['clave' => $clave, 'hecho' => $hecho, 'url' => $url, 'comando' => $comando];
    }
}
