<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Grupos;

use App\Enums\EstadoReserva;
use App\Models\Grupo;
use App\Models\Reserva;
use App\Reservas\GestorGrupos;
use App\Reservas\ReservaRechazada;
use App\Support\Csv;

/**
 * Quién va en cada habitación, de ida y vuelta a una hoja de cálculo. Se identifica por el número de reserva; lo que se puede cambiar es la lista, no las
 * fechas ni la unidad. La lógica viene de la 1.2.3, reescrita.
 */
final class ListaHuespedes
{
    public const BYTES_MAXIMOS = 1048576;

    /** Las cuatro primeras son solo para ubicarse: al importar se ignoran menos la primera. */
    private const COLUMNAS = ['reserva', 'unidad', 'llegada', 'salida', 'responsable', 'telefono', 'correo', 'personas', 'acompanantes'];

    public function __construct(private readonly GestorGrupos $grupos)
    {
    }

    /**
     * Para abrirla en Excel, llenarla y pegarla de vuelta.
     */
    public function exportar(Grupo $grupo): string
    {
        $filas = [array_map(fn (string $c): string => __('grupos.lista.'.$c), self::COLUMNAS)];
        foreach ($this->reservas($grupo) as $reserva) {
            $filas[] = [
                $reserva->numero,
                $reserva->unidad->nombre,
                $reserva->llegada->format('Y-m-d'),
                $reserva->salida->format('Y-m-d'),
                $reserva->habitacionGrupo?->responsable,
                $reserva->habitacionGrupo?->telefono,
                $reserva->habitacionGrupo?->correo,
                $reserva->personas,
                $reserva->habitacionGrupo?->acompanantes,
            ];
        }

        return Csv::paraExcel($filas);
    }

    /**
     * Las filas correctas se guardan; de las demás se dice por qué no.
     *
     * @return array{guardadas: int, errores: list<string>}
     */
    public function importar(Grupo $grupo, string $texto): array
    {
        $texto = Csv::enUtf8($texto);
        if (strlen($texto) > self::BYTES_MAXIMOS) {
            return ['guardadas' => 0, 'errores' => [__('grupos.lista.error_tamano')]];
        }
        $lineas = preg_split('/\r\n|\r|\n/', trim($texto)) ?: [];
        $separador = Csv::separador($lineas[0]);
        $reservas = $this->reservas($grupo)->keyBy(fn (Reserva $r): string => (string) $r->numero);

        $guardadas = 0;
        $errores = [];
        foreach ($lineas as $i => $linea) {
            if (trim($linea) === '') {
                continue;
            }
            $campos = array_map(fn (string $campo): string => Csv::sinProteccion(trim($campo)), str_getcsv($linea, $separador, '"', ''));
            $reserva = $reservas->get($campos[0]);
            if ($reserva === null) {
                // La primera línea es el encabezado que se descargó.
                if ($i > 0) {
                    $errores[] = __('grupos.lista.error_reserva', ['linea' => $i + 1, 'numero' => $campos[0]]);
                }

                continue;
            }
            $correo = $campos[6] ?? '';
            if ($correo !== '' && filter_var($correo, FILTER_VALIDATE_EMAIL) === false) {
                $errores[] = __('grupos.lista.error_correo', ['linea' => $i + 1]);

                continue;
            }
            $personas = ($campos[7] ?? '') === '' ? null : (int) $campos[7];
            try {
                $this->grupos->guardarHabitacion($reserva, [
                    'responsable' => mb_substr($campos[4] ?? '', 0, 150),
                    'telefono' => mb_substr($campos[5] ?? '', 0, 50),
                    'correo' => mb_substr($correo, 0, 150),
                    'acompanantes' => mb_substr($campos[8] ?? '', 0, 2000),
                    'personas' => $personas,
                ]);
                $guardadas++;
            } catch (ReservaRechazada $e) {
                $errores[] = __('grupos.lista.error_linea', ['linea' => $i + 1, 'motivo' => $e->getMessage()]);
            }
        }

        return ['guardadas' => $guardadas, 'errores' => $errores];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Reserva>
     */
    private function reservas(Grupo $grupo): \Illuminate\Database\Eloquent\Collection
    {
        return $grupo->reservas()->with(['unidad', 'habitacionGrupo'])->where('estado', '!=', EstadoReserva::Cancelada->value)->orderBy('id')->get();
    }
}
