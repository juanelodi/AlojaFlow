<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
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
    /** @var array<string, int> quién tiene cada código en la propiedad, por su forma comparable */
    private array $codigos = [];

    public function __construct(private readonly Contexto $contexto)
    {
    }

    /**
     * Deja las unidades como en la copia y devuelve cómo encontrarlas.
     *
     * @return array<string, int> nombre de la unidad en su forma comparable (Contexto::comparable) => unidad_id
     */
    public function proyectar(): array
    {
        // Los índices únicos no distinguen mayúsculas ni acentos: aquí se busca igual, o un nombre que solo cambió en eso chocaría.
        $guardadas = DB::table('unidades')->where('principal_id', $this->contexto->principal->id)->whereNotNull('clave_origen')->get()
            ->keyBy(fn (object $unidad): string => Contexto::comparable((string) $unidad->clave_origen));
        $this->codigos = DB::table('unidades')->where('propiedad_id', $this->contexto->propiedadId())->pluck('id', 'codigo')
            ->mapWithKeys(fn (mixed $id, mixed $codigo): array => [Contexto::comparable((string) $codigo) => (int) $id])->all();
        $ids = [];

        foreach ($this->contexto->espejo->filas('appartamenti') as $fila) {
            $clave = $this->contexto->conversor->texto($fila['idappartamenti'] ?? null, 100);
            $llave = $clave === null ? null : Contexto::comparable($clave);
            if ($clave === null || $llave === null || isset($ids[$llave])) {
                $this->contexto->resultado->incidencia('unidades_descartadas');

                continue;
            }
            $datos = $this->datos($clave, $fila);
            $huella = Huellas::datos($datos);
            $identidad = hash('sha256', $clave);
            $guardada = $guardadas->get($llave);
            $this->apartarCodigo($clave, $llave, $guardada === null ? null : (int) $guardada->id);

            if ($guardada === null) {
                $ids[$llave] = (int) DB::table('unidades')->insertGetId(
                    $datos + $this->contexto->columnasOrigen($clave, $identidad, $huella) + ['propiedad_id' => $this->contexto->propiedadId(), 'activa' => true]
                );
                $this->codigos[$llave] = $ids[$llave];
                $this->contexto->resultado->contar('unidades_nuevas');

                continue;
            }

            $ids[$llave] = (int) $guardada->id;
            $this->codigos[$llave] = $ids[$llave];
            if ($guardada->huella_datos === $huella && $guardada->huella_identidad === $identidad && (bool) $guardada->activa) {
                continue;
            }
            // Si lo único distinto son las huellas (las guardó otra generación del proyector), la unidad no se toca.
            $cambios = ['huella_identidad' => $identidad, 'huella_datos' => $huella, 'ejecucion_id' => $this->contexto->ejecucion->id];
            if (Contexto::diferencias($guardada, $datos + ['activa' => true, 'clave_origen' => $clave]) !== []) {
                $cambios += $datos + ['activa' => true, 'clave_origen' => $clave, 'actualizado_en' => $this->contexto->ahora];
                $this->contexto->resultado->contar('unidades_actualizadas');
            }
            DB::table('unidades')->where('id', $guardada->id)->update($cambios);
        }

        // La que ya no viene se desactiva: sus reservas pasadas, su plano y sus tareas siguen ahí.
        $presentes = array_flip($ids);
        $ausentes = $guardadas->filter(fn (object $unidad): bool => ! isset($presentes[(int) $unidad->id]) && (bool) $unidad->activa)->pluck('id')->all();
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
    private function apartarCodigo(string $codigo, string $llave, ?int $unidadId): void
    {
        $ocupante = $this->codigos[$llave] ?? null;
        if ($ocupante !== null && $ocupante !== $unidadId) {
            DB::table('unidades')->where('id', $ocupante)->update(['codigo' => mb_substr($codigo, 0, 80).'~'.$ocupante, 'huella_datos' => null]);
        }
    }

    private function entero(?string $valor, int $minimo, int $maximo): int
    {
        return is_numeric($valor) ? min($maximo, max($minimo, (int) $valor)) : 0;
    }
}
