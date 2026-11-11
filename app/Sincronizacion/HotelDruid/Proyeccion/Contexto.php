<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Sincronizacion\HotelDruid\Proyeccion;

use App\Enums\OrigenFila;
use App\Models\EjecucionSincronizacion;
use App\Models\Principal;
use App\Sincronizacion\HotelDruid\Conversor;
use App\Sincronizacion\HotelDruid\Espejo;
use App\Sincronizacion\HotelDruid\ResultadoProyeccion;
use Normalizer;

/**
 * Lo que comparten las piezas de una proyección: de qué principal y qué ejecución, de dónde se lee y dónde se anota.
 */
final class Contexto
{
    /**
     * @param  string  $ahora  la hora de esta proyección, en UTC: la misma en todas las filas que toca
     * @param  bool  $mismaGeneracion  si la carga anterior la hizo este mismo proyector; si no, las huellas guardadas
     *                                 no se pueden comparar con las de ahora (ver Proyector::GENERACION)
     */
    public function __construct(
        public readonly Principal $principal,
        public readonly EjecucionSincronizacion $ejecucion,
        public readonly Espejo $espejo,
        public readonly Conversor $conversor,
        public readonly ResultadoProyeccion $resultado,
        public readonly string $ahora,
        public readonly bool $mismaGeneracion,
    ) {
    }

    public function propiedadId(): int
    {
        return $this->principal->propiedad_id;
    }

    public function moneda(): string
    {
        return $this->principal->propiedad->moneda;
    }

    /**
     * Las columnas que dicen de dónde vino una fila proyectada (modelo-datos.md §2).
     *
     * @return array<string, mixed>
     */
    public function columnasOrigen(string $clave, string $identidad, string $huella): array
    {
        return [
            'origen' => OrigenFila::HotelDruid->value,
            'sincronizada' => true,
            'principal_id' => $this->principal->id,
            'clave_origen' => $clave,
            'huella_identidad' => $identidad,
            'huella_datos' => $huella,
            'ejecucion_id' => $this->ejecucion->id,
            'creado_en' => $this->ahora,
            'actualizado_en' => $this->ahora,
        ];
    }

    /**
     * Qué columnas de una fila guardada son distintas de las que se acaban de calcular. Se compara como texto, que es
     * como las devuelve la base.
     *
     * @param  array<string, mixed>  $calculadas
     * @return list<string>
     */
    public static function diferencias(object $guardada, array $calculadas): array
    {
        $distintas = [];
        foreach ($calculadas as $columna => $valor) {
            if (self::comoTexto($guardada->{$columna} ?? null) !== self::comoTexto($valor)) {
                $distintas[] = $columna;
            }
        }

        return $distintas;
    }

    /**
     * Un nombre como lo comparan los índices únicos de la base, que no distinguen mayúsculas ni acentos: «CABAÑA 1» y
     * «cabana 1» son el mismo. Sirve para buscar por nombre lo que la principal casa así.
     */
    public static function comparable(string $texto): string
    {
        $descompuesto = Normalizer::normalize(trim($texto), Normalizer::FORM_KD);
        $sinAcentos = preg_replace('/\p{Mn}+/u', '', $descompuesto === false ? trim($texto) : $descompuesto);

        return mb_strtolower($sinAcentos ?? trim($texto));
    }

    private static function comoTexto(mixed $valor): string
    {
        return match (true) {
            $valor === null => "\0",
            is_bool($valor) => (string) (int) $valor,
            default => (string) $valor,
        };
    }
}
