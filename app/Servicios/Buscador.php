<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Servicios;

use App\Enums\EstadoReserva;
use App\Enums\TipoReserva;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * El buscador de las listas: se escribe cualquier palabra y se encuentra por lo que se recuerde, sin elegir un campo. Con varias palabras,
 * cada una debe aparecer en algún dato: «ana cancún» encuentra a la Ana de Cancún, en cualquier orden.
 */
final class Buscador
{
    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $consulta
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    public static function reservas(Builder $consulta, string $busqueda): Builder
    {
        foreach (self::palabras($busqueda) as $palabra) {
            $patron = self::patron($palabra);
            $consulta->where(function (Builder $q) use ($palabra, $patron): void {
                foreach (['numero', 'codigo_publico', 'origen_texto', 'tarifa_nombre', 'grupo_codigo', 'comentario'] as $campo) {
                    $q->orWhere($campo, 'like', $patron);
                }
                $q->orWhereHas('unidad', fn (Builder $u): Builder => $u->where('nombre', 'like', $patron))
                    ->orWhereHas('propiedad', fn (Builder $p): Builder => $p->where('nombre', 'like', $patron))
                    ->orWhereHas('titular', fn (Builder $h): Builder => self::enHuesped($h, $palabra))
                    ->orWhereHas('huespedes', fn (Builder $h): Builder => self::enHuesped($h, $palabra));
                foreach (EstadoReserva::cases() as $estado) {
                    if (self::coincide($estado->etiqueta(), $palabra)) {
                        $q->orWhere('estado', $estado->value);
                    }
                }
                if (self::coincide(TipoReserva::Bloqueo->etiqueta(), $palabra)) {
                    $q->orWhere('tipo', TipoReserva::Bloqueo->value);
                }
                $fecha = self::fecha($palabra);
                if ($fecha !== null) {
                    $q->orWhere('llegada', $fecha)->orWhere('salida', $fecha);
                }
            });
        }

        return $consulta;
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $consulta
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    public static function huespedes(Builder $consulta, string $busqueda): Builder
    {
        foreach (self::palabras($busqueda) as $palabra) {
            $consulta->where(function (Builder $q) use ($palabra): void {
                self::enHuesped($q, $palabra);
                if (ctype_digit($palabra)) {
                    $q->orWhere($q->getModel()->qualifyColumn('id'), (int) $palabra)->orWhere('clave_origen', $palabra);
                }
            });
        }

        return $consulta;
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $consulta
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    private static function enHuesped(Builder $consulta, string $palabra): Builder
    {
        $patron = self::patron($palabra);

        return $consulta->where(function (Builder $q) use ($patron, $palabra): void {
            foreach (['nombre', 'apellido1', 'apellido2', 'apodo', 'nacionalidad_texto', 'domicilio_ciudad', 'domicilio_region', 'domicilio_pais_texto', 'id_fiscal_personal', 'id_fiscal_empresa'] as $campo) {
                $q->orWhere($campo, 'like', $patron);
            }
            $q->orWhereHas('contactos', fn (Builder $c): Builder => $c->where('valor', 'like', $patron)
                ->orWhere('valor_normalizado', 'like', self::patron(mb_strtolower($palabra))));
        });
    }

    /**
     * @return list<string>
     */
    private static function palabras(string $busqueda): array
    {
        return array_slice(preg_split('/\s+/u', trim($busqueda), -1, PREG_SPLIT_NO_EMPTY) ?: [], 0, 8);
    }

    private static function patron(string $palabra): string
    {
        return '%'.addcslashes($palabra, '%_\\').'%';
    }

    private static function coincide(string $etiqueta, string $palabra): bool
    {
        return mb_strlen($palabra) >= 3 && str_contains(mb_strtolower(Str::ascii($etiqueta)), mb_strtolower(Str::ascii($palabra)));
    }

    /**
     * 15/12/2026, 15/12/26, 2026-12-15, o 12/15/2026 en inglés: como la escribe quien la busca, en su idioma.
     */
    private static function fecha(string $palabra): ?string
    {
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $palabra, $p) === 1) {
            return self::armar((int) $p[1], (int) $p[2], (int) $p[3]);
        }
        if (preg_match('#^(\d{1,2})[/.-](\d{1,2})[/.-](\d{2}|\d{4})$#', $palabra, $p) !== 1) {
            return null;
        }
        $anio = (int) (strlen($p[3]) === 2 ? '20'.$p[3] : $p[3]);
        $mesPrimero = str_starts_with((string) (CarbonImmutable::now()->locale(app()->getLocale())->getIsoFormats()['L'] ?? ''), 'M');

        return $mesPrimero ? self::armar($anio, (int) $p[1], (int) $p[2]) : self::armar($anio, (int) $p[2], (int) $p[1]);
    }

    private static function armar(int $anio, int $mes, int $dia): ?string
    {
        return checkdate($mes, $dia, $anio) ? sprintf('%04d-%02d-%02d', $anio, $mes, $dia) : null;
    }
}
