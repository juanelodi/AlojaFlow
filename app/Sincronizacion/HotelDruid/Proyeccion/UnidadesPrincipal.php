<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Sincronizacion\HotelDruid\Proyeccion;

use App\Support\Huellas;
use Illuminate\Support\Facades\DB;

/**
 * Las unidades de la principal (subordinacion.md C3). Allá el nombre de la unidad es también su clave: aquí es el
 * código, el nombre y la clave de origen.
 */
final class UnidadesPrincipal
{
    public function __construct(private readonly Contexto $contexto)
    {
    }

    /**
     * Deja las unidades como en la copia y devuelve cómo encontrarlas.
     *
     * @return array<string, int> clave de origen => unidad_id
     */
    public function proyectar(): array
    {
        $guardadas = DB::table('unidades')->where('principal_id', $this->contexto->principal->id)->whereNotNull('clave_origen')->get()->keyBy('clave_origen');
        $ids = [];
        $yaVistas = [];

        foreach ($this->contexto->espejo->filas('appartamenti') as $fila) {
            $clave = $this->contexto->conversor->texto($fila['idappartamenti'] ?? null, 100);
            // El índice único no distingue mayúsculas: dos nombres que solo cambian en eso chocarían.
            if ($clave === null || isset($yaVistas[mb_strtolower($clave)])) {
                $this->contexto->resultado->incidencia('unidades_descartadas');

                continue;
            }
            $yaVistas[mb_strtolower($clave)] = true;
            $datos = $this->datos($clave, $fila);
            $huella = Huellas::datos($datos);
            $guardada = $guardadas->get($clave);
            $this->apartarCodigo($clave, $guardada === null ? null : (int) $guardada->id);

            if ($guardada === null) {
                $ids[$clave] = (int) DB::table('unidades')->insertGetId(
                    $datos + $this->contexto->columnasOrigen($clave, hash('sha256', $clave), $huella) + ['propiedad_id' => $this->contexto->propiedadId(), 'activa' => true]
                );
                $this->contexto->resultado->contar('unidades_nuevas');

                continue;
            }

            $ids[$clave] = (int) $guardada->id;
            $identidad = hash('sha256', $clave);
            if ($guardada->huella_datos === $huella && $guardada->huella_identidad === $identidad && (bool) $guardada->activa) {
                continue;
            }
            // Si lo único distinto son las huellas (las guardó otra generación del proyector), la unidad no se toca.
            $cambios = ['huella_identidad' => $identidad, 'huella_datos' => $huella, 'ejecucion_id' => $this->contexto->ejecucion->id];
            if (Contexto::diferencias($guardada, $datos + ['activa' => true]) !== []) {
                $cambios += $datos + ['activa' => true, 'actualizado_en' => $this->contexto->ahora];
                $this->contexto->resultado->contar('unidades_actualizadas');
            }
            DB::table('unidades')->where('id', $guardada->id)->update($cambios);
        }

        // La que ya no viene se desactiva: sus reservas pasadas, su plano y sus tareas siguen ahí.
        $ausentes = $guardadas->filter(fn (object $unidad): bool => ! isset($ids[(string) $unidad->clave_origen]) && (bool) $unidad->activa)->pluck('id')->all();
        if ($ausentes !== []) {
            DB::table('unidades')->whereIn('id', $ausentes)->update(['activa' => false, 'actualizado_en' => $this->contexto->ahora]);
            $this->contexto->resultado->contar('unidades_desactivadas', count($ausentes));
        }

        return $ids;
    }

    /**
     * @param  array<string, string|null>  $fila
     * @return array<string, mixed>
     */
    private function datos(string $clave, array $fila): array
    {
        $conversor = $this->contexto->conversor;

        return [
            'codigo' => $clave,
            'nombre' => $clave,
            'piso' => $conversor->texto($fila['numpiano'] ?? null, 50),
            'edificio' => $conversor->texto($fila['numcasa'] ?? null, 50),
            'capacidad' => $this->entero($fila['maxoccupanti'] ?? null, 0, 65535),
            'orden' => $this->entero($fila['priorita'] ?? null, -2147483648, 2147483647),
            'notas' => $conversor->texto($fila['commento'] ?? null, 65000),
        ];
    }

    /**
     * El código es único en la propiedad. Si lo tiene otra unidad (una que se renombró a mano y aún no se pone al
     * día), se le aparta para que la copia no se detenga; si sigue en la principal, recibe el suyo al llegar su turno.
     */
    private function apartarCodigo(string $codigo, ?int $unidadId): void
    {
        $ocupante = DB::table('unidades')->where('propiedad_id', $this->contexto->propiedadId())->where('codigo', $codigo)
            ->when($unidadId !== null, fn ($consulta) => $consulta->where('id', '!=', $unidadId))->value('id');
        if ($ocupante !== null) {
            DB::table('unidades')->where('id', $ocupante)->update(['codigo' => mb_substr($codigo, 0, 80).'~'.$ocupante, 'huella_datos' => null]);
        }
    }

    private function entero(?string $valor, int $minimo, int $maximo): int
    {
        return is_numeric($valor) ? min($maximo, max($minimo, (int) $valor)) : 0;
    }
}
