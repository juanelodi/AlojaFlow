<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Autoridades;

use App\Models\Propiedad;
use App\Models\Reserva;
use Illuminate\Support\Carbon;

/**
 * El registro de viajeros de un país: a quién hay que avisarle de cada reserva y de cada llegada (D-100). Lo que es igual en todos, cuándo y cómo se reintenta,
 * está en `GestorEnvios`; aquí, lo que dice cada autoridad.
 */
interface RegistroViajeros
{
    public function clave(): string;

    public function nombre(): string;

    /**
     * Desde cuándo se le comunica a esta propiedad; null si no está conectada. Lo anterior no se manda de golpe.
     */
    public function conectadaDesde(Propiedad $propiedad): ?Carbon;

    /**
     * @param  array<string, array<string, string>>  $correcciones  lo que alguien corrigió a mano, por persona
     */
    public function armar(Reserva $reserva, string $tipo, array $correcciones): Comunicacion;

    /**
     * @param  list<Comunicacion>  $comunicaciones
     * @return string el lote con que se aceptó el envío; procesarlo es otra cosa
     *
     * @throws ErrorAutoridad
     */
    public function enviar(Propiedad $propiedad, string $tipo, array $comunicaciones): string;

    /**
     * @param  list<string>  $codigos
     * @return string el lote
     *
     * @throws ErrorAutoridad
     */
    public function anular(Propiedad $propiedad, array $codigos): string;

    /**
     * @param  list<string>  $lotes
     * @return array<string, ResultadoLote> por lote; el que no aparece, aún no se sabe
     *
     * @throws ErrorAutoridad
     */
    public function consultar(Propiedad $propiedad, array $lotes): array;
}
