<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Sincronizacion\HotelDruid;

/**
 * Qué tablas de la copia de HotelDruid se guardan en el espejo (subordinacion.md C1) y cuáles tienen que venir (B8).
 * Lo que no está aquí se salta sin leerse: usuarios con sus contraseñas, sesiones, claves de API, conexiones con
 * otros sistemas y mensajes internos.
 */
final class Esquema
{
    public const VERSIONES_ADMITIDAS = ['3.08', '3.081'];

    /** Una sola tabla por instalación. */
    public const TABLAS_GLOBALES = [
        'anni', 'versioni', 'appartamenti', 'clienti', 'relclienti', 'personalizza', 'nazioni', 'regioni', 'citta',
        'documentiid', 'parentele', 'descrizioni', 'casse', 'beniinventario', 'magazzini', 'relinventario',
    ];

    /** Una tabla por año, con el año pegado al nombre (`prenota2026`). */
    public const TABLAS_ANUALES = [
        'prenota', 'prenotacanc', 'costiprenota', 'rclientiprenota', 'periodi', 'ntariffe', 'regole', 'soldi', 'costi',
    ];

    public const TABLAS_GLOBALES_OBLIGATORIAS = ['anni', 'versioni', 'appartamenti', 'clienti', 'personalizza'];

    /**
     * Parte el nombre de una tabla de la copia en su nombre base y su año: `prenota2026` es `prenota` de 2026.
     *
     * @return array{0: string, 1: int|null}
     */
    public static function separar(string $tabla): array
    {
        if (preg_match('/^([a-z]+)([0-9]{4})$/', $tabla, $partes) === 1) {
            return [$partes[1], (int) $partes[2]];
        }

        return [$tabla, null];
    }

    public static function seConserva(string $base, ?int $anio): bool
    {
        return in_array($base, $anio === null ? self::TABLAS_GLOBALES : self::TABLAS_ANUALES, true);
    }
}
