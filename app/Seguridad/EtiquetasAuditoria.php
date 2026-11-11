<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Seguridad;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;

/**
 * Lo que se lee de un registro de auditoría, en el idioma de quien lo mira. En la base se guardan códigos, para que el registro no dependa
 * del idioma de quien hizo el cambio (D-133). Lo que no tiene traducción, como una columna poco común, sale como está: mejor crudo que en blanco.
 */
final class EtiquetasAuditoria
{
    /** Lo que AuditoriaModelos guarda cuando un valor no se puede copiar. */
    public const CAMBIO = 'cambió';

    private const CAMPOS_CON_COLUMNAS = 'campos';

    /** Solo estos guardan un valor del programa. En los demás, un «salida» lo escribió alguien, y la auditoría debe decir lo que se guardó. */
    private const CAMPOS_CON_VALORES = ['estado', 'origen', 'accion'];

    public static function accion(string $accion): string
    {
        return self::texto('auditoria.acciones.'.$accion)
            ?? self::texto('auditoria.verbos.'.Str::afterLast($accion, '.'))
            ?? $accion;
    }

    public static function objeto(?string $tipo, int|string|null $id): ?string
    {
        if ($tipo === null) {
            return null;
        }

        return (self::texto('auditoria.objetos.'.$tipo) ?? $tipo).' #'.$id;
    }

    /**
     * @param  array<string, mixed>|null  $cambios
     */
    public static function detalle(?array $cambios): ?string
    {
        if ($cambios === null || $cambios === []) {
            return null;
        }
        $partes = [];
        foreach ($cambios as $campo => $valor) {
            $partes[] = self::campo((string) $campo).': '.self::valor($valor, (string) $campo);
        }

        return implode(' · ', $partes);
    }

    /**
     * El buscador de la tabla encuentra lo que se lee en pantalla, no solo el código guardado.
     *
     * @param  Builder<\App\Models\RegistroAuditoria>  $query
     * @return Builder<\App\Models\RegistroAuditoria>
     */
    public static function buscarAccion(Builder $query, string $texto): Builder
    {
        return $query->where(function (Builder $query) use ($texto): void {
            $query->where('accion', 'like', '%'.$texto.'%')->orWhereIn('accion', self::clavesQueDicen('auditoria.acciones', $texto));
            foreach (self::clavesQueDicen('auditoria.verbos', $texto) as $verbo) {
                $query->orWhere('accion', 'like', '%.'.$verbo);
            }
        });
    }

    /**
     * @param  Builder<\App\Models\RegistroAuditoria>  $query
     * @return Builder<\App\Models\RegistroAuditoria>
     */
    public static function buscarObjeto(Builder $query, string $texto): Builder
    {
        return $query->where(function (Builder $query) use ($texto): void {
            $query->where('modelo_tipo', 'like', '%'.$texto.'%')->orWhereIn('modelo_tipo', self::clavesQueDicen('auditoria.objetos', $texto));
        });
    }

    private static function campo(string $campo): string
    {
        return self::texto('auditoria.campos.'.$campo) ?? str_replace('_', ' ', $campo);
    }

    private static function valor(mixed $valor, string $campo): string
    {
        if (is_array($valor)) {
            return self::arreglo($valor, $campo);
        }
        if (is_bool($valor)) {
            return (string) __($valor ? 'auditoria.si' : 'auditoria.no');
        }
        if ($valor === null) {
            return '—';
        }
        if ($valor === self::CAMBIO) {
            return (string) __('auditoria.cambio');
        }
        $texto = is_scalar($valor) ? (string) $valor : (string) json_encode($valor, JSON_UNESCAPED_UNICODE);

        return in_array($campo, self::CAMPOS_CON_VALORES, true) ? (self::texto('auditoria.valores.'.$texto) ?? $texto) : $texto;
    }

    /**
     * @param  array<array-key, mixed>  $valor
     */
    private static function arreglo(array $valor, string $campo): string
    {
        if (array_keys($valor) === ['antes', 'despues']) {
            return self::valor($valor['antes'], $campo).' → '.self::valor($valor['despues'], $campo);
        }
        if (array_is_list($valor)) {
            return implode(', ', array_map(
                fn (mixed $v): string => $campo === self::CAMPOS_CON_COLUMNAS && is_string($v) ? self::campo($v) : self::valor($v, $campo),
                $valor,
            ));
        }
        $partes = [];
        foreach ($valor as $subcampo => $subvalor) {
            $partes[] = self::campo((string) $subcampo).': '.self::valor($subvalor, (string) $subcampo);
        }

        return '('.implode(', ', $partes).')';
    }

    /**
     * Las claves llevan puntos o datos del registro: solo se acepta si de verdad es un texto, y no un grupo de ellos.
     */
    private static function texto(string $clave): ?string
    {
        if (! Lang::has($clave)) {
            return null;
        }
        $texto = __($clave);

        return is_string($texto) ? $texto : null;
    }

    /**
     * @return list<string> las claves, con sus puntos, cuyo texto contiene lo buscado
     */
    private static function clavesQueDicen(string $grupo, string $texto): array
    {
        $textos = __($grupo);
        if (! is_array($textos)) {
            return [];
        }

        return array_map('strval', array_keys(array_filter(Arr::dot($textos), fn (mixed $v): bool => is_string($v) && mb_stripos($v, $texto) !== false)));
    }
}
