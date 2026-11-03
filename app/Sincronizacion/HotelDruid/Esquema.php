<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Sincronizacion\HotelDruid;

/**
 * Esquema 3.081 de HotelDruid: qué se admite, qué se conserva y qué es obligatorio
 * (subordinacion.md A1, B8 y C1).
 */
final class Esquema
{
    public const VERSIONES_ADMITIDAS = ['3.08', '3.081'];

    /** Tablas globales que se conservan en el espejo. El resto se descarta al leer. */
    public const TABLAS_GLOBALES = [
        'anni', 'versioni', 'appartamenti', 'clienti', 'relclienti', 'personalizza',
        'nazioni', 'regioni', 'citta', 'documentiid', 'parentele', 'descrizioni', 'casse',
        'beniinventario', 'magazzini', 'relinventario',
    ];

    /** Tablas por año (prenota2026…) que se conservan; todas son obligatorias para cada año. */
    public const TABLAS_ANUALES = [
        'prenota', 'prenotacanc', 'costiprenota', 'rclientiprenota', 'periodi', 'ntariffe',
        'regole', 'soldi', 'costi',
    ];

    public const TABLAS_GLOBALES_OBLIGATORIAS = ['anni', 'versioni', 'appartamenti', 'clienti', 'personalizza'];

    /**
     * Separa el nombre base y el año: prenota2026 → ['prenota', 2026]; clienti → ['clienti', null].
     *
     * @return array{0: string, 1: int|null}
     */
    public static function separar(string $tabla): array
    {
        if (preg_match('/^([a-z]+)([0-9]{4})$/', $tabla, $m)) {
            return [$m[1], (int) $m[2]];
        }

        return [$tabla, null];
    }

    public static function seConserva(string $base, ?int $anio): bool
    {
        return $anio === null
            ? in_array($base, self::TABLAS_GLOBALES, true)
            : in_array($base, self::TABLAS_ANUALES, true);
    }
}
