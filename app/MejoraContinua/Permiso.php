<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\MejoraContinua;

use App\Demo\ModoDemo;
use App\Support\Ajustes;

/**
 * Si esta instalación manda datos para la mejora continua (D-150). Lo decide quien la administra: al
 * instalar, con una casilla que explica qué se manda; al actualizar desde una versión anterior, en el
 * panel. Mientras no decide, no se manda nada. No es obligatorio y se puede cambiar cuando sea.
 */
final class Permiso
{
    /** true o false; si no existe, todavía no se ha decidido. */
    public const AJUSTE = 'mejora.permiso';

    /** Nombre, dominio y correo, solo si eligió identificarse para recibir ayuda. */
    public const AJUSTE_IDENTIFICACION = 'mejora.identificacion';

    public static function decidido(): bool
    {
        return is_bool(Ajustes::obtener(self::AJUSTE));
    }

    public static function concedido(): bool
    {
        return Ajustes::obtener(self::AJUSTE) === true;
    }

    public static function guardar(bool $concedido): void
    {
        Ajustes::guardar(self::AJUSTE, $concedido);
    }

    /**
     * Si hay a dónde mandar: un receptor configurado y fuera de la demostración pública.
     */
    public static function hayReceptor(): bool
    {
        return filled(config('alojaflow.mejora_continua.url')) && ! ModoDemo::activo();
    }

    /**
     * Si se mandan latidos, errores y visitas. Los reportes no dependen de esto: los manda quien los escribe.
     */
    public static function activa(): bool
    {
        return self::concedido() && self::hayReceptor();
    }

    /**
     * @return array{nombre: string, dominio: string, correo: string}|null
     */
    public static function identificacion(): ?array
    {
        $datos = Ajustes::obtener(self::AJUSTE_IDENTIFICACION);
        if (! is_array($datos) || ! filled($datos['nombre'] ?? null)) {
            return null;
        }

        return [
            'nombre' => (string) $datos['nombre'],
            'dominio' => (string) ($datos['dominio'] ?? ''),
            'correo' => (string) ($datos['correo'] ?? ''),
        ];
    }

    /**
     * @param  array{nombre: string, dominio: string, correo: string}|null  $datos
     */
    public static function guardarIdentificacion(?array $datos): void
    {
        $datos === null ? Ajustes::borrar(self::AJUSTE_IDENTIFICACION) : Ajustes::guardar(self::AJUSTE_IDENTIFICACION, $datos);
    }
}
