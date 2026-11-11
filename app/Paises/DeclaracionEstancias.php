<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Paises;

use App\Enums\EstadoReserva;
use App\Models\Impuesto;
use App\Models\Propiedad;
use App\Support\Csv;
use Illuminate\Support\Facades\DB;

/**
 * El semestre del impuesto sobre las estancias de Cataluña, en el archivo que acepta la Agencia Tributaria de Cataluña para su carga masiva (D-101). Las estancias se
 * cuentan en el semestre en que empiezan: si una cruza de un semestre a otro, hay que repartirla a mano. No presenta nada: el archivo se sube en su sede.
 */
final class DeclaracionEstancias
{
    private const CODIGO = 'IEET';

    /** La cabecera de su plantilla, tal cual: no admite otra. */
    private const COLUMNAS = ['NIF o CIF', 'Apellidos y nombre o Razon Social', 'Tipo de establecimiento', 'Numero de registro completo', 'Categoria Establecimiento', 'Ubicado en centro de apuestas', 'Ejercicio', 'Periodo', 'Estancias no exentas', 'Exentas sociales', 'Exentas menors', 'Exentas fuerza mayor', 'Exentas salud'];

    public const TIPOS = ['EH', 'AT', 'CA', 'TR', 'AJ', 'HT', 'AE'];

    public const CATEGORIAS = ['E5', 'E4', 'TC', 'OC'];

    public const PERIODOS = ['1S', '2S'];

    public function aplica(Propiedad $propiedad): bool
    {
        return Impuesto::query()->where('propiedad_id', $propiedad->id)->where('codigo', self::CODIGO)->exists();
    }

    /**
     * @return array{no_exentas: int, menores: int}
     */
    public function estancias(Propiedad $propiedad, int $ejercicio, string $periodo): array
    {
        [$desde, $hasta] = $periodo === '1S' ? [$ejercicio.'-04-01', $ejercicio.'-09-30'] : [$ejercicio.'-10-01', ($ejercicio + 1).'-03-31'];
        $suma = DB::table('reservas_impuestos as i')->join('reservas as r', 'r.id', '=', 'i.reserva_id')->join('impuestos as t', 't.id', '=', 'i.impuesto_id')
            ->where('r.propiedad_id', $propiedad->id)->where('t.codigo', self::CODIGO)->where('r.estado', '!=', EstadoReserva::Cancelada->value)
            ->whereBetween('r.llegada', [$desde, $hasta])
            ->selectRaw('COALESCE(SUM(i.unidades), 0) as unidades, COALESCE(SUM(i.unidades_exentas), 0) as exentas')->first();

        return ['no_exentas' => (int) ($suma->unidades ?? 0), 'menores' => (int) ($suma->exentas ?? 0)];
    }

    /**
     * @param  array{nif: string, nombre: string, tipo: string, registro: string, categoria: string}  $datos
     */
    public function csv(Propiedad $propiedad, int $ejercicio, string $periodo, array $datos): string
    {
        $estancias = $this->estancias($propiedad, $ejercicio, $periodo);
        $limpio = fn (string $texto): string => str_replace(';', ' ', trim($texto));
        $fila = [
            strtoupper((string) preg_replace('/[\s.\-]/', '', $datos['nif'])),
            mb_substr($limpio($datos['nombre']), 0, 60),
            $datos['tipo'],
            $limpio($datos['registro']),
            $datos['categoria'],
            'N',
            (string) $ejercicio,
            $periodo,
            (string) $estancias['no_exentas'],
            '0',
            (string) $estancias['menores'],
            '0',
            '0',
        ];

        return implode(';', self::COLUMNAS)."\r\n".implode(';', Csv::fila($fila))."\r\n";
    }
}
