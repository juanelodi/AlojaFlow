<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Sincronizacion\AlojaFlow;

use App\Enums\TipoReserva;
use App\Models\ConexionCompartida;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use stdClass;
use Throwable;

/**
 * Lo que la principal le deja leer a una subordinada (docs/especificacion/subordinacion-alojaflow.md). Las fotos y los archivos nunca
 * viajan, y el documento de identidad solo si la conexión lo permite.
 */
final class Exportador
{
    public const VERSION_API = 1;

    public const POR_PAGINA = 500;

    public const COLECCIONES = ['unidades', 'huespedes', 'reservas', 'bloqueos', 'cajas', 'pagos', 'movimientos'];

    private const CAMPOS_HUESPED = [
        'nombre', 'apellido1', 'apellido2', 'apodo', 'tratamiento', 'sexo', 'idioma', 'fecha_nacimiento', 'lugar_nacimiento', 'pais_nacimiento_texto', 'nacionalidad',
        'nacionalidad_texto', 'domicilio_calle', 'domicilio_numero', 'domicilio_ciudad', 'domicilio_region', 'domicilio_codigo_postal', 'domicilio_pais', 'domicilio_pais_texto',
        'id_fiscal_personal', 'id_fiscal_empresa', 'notas',
    ];

    private const CAMPOS_DOCUMENTO = ['documento_tipo_texto', 'documento_vencimiento', 'documento_expedicion', 'documento_pais_texto'];

    private const CAMPOS_RESERVA = [
        'unidad_id', 'titular_id', 'tipo', 'estado', 'numero', 'llegada', 'salida', 'personas', 'origen_texto', 'grupo_codigo', 'tarifa_nombre', 'moneda', 'total', 'pagado', 'deposito',
        'descuento', 'comision', 'impuestos_porcentaje', 'entrada_registrada_en', 'salida_registrada_en', 'comentario', 'creado_en', 'actualizado_en',
    ];

    /**
     * @return array<string, mixed>
     */
    public function estado(ConexionCompartida $conexion): array
    {
        $propiedad = $conexion->propiedad;

        return [
            'api' => self::VERSION_API,
            'version' => (string) config('alojaflow.version'),
            'colecciones' => self::COLECCIONES,
            'revision' => $this->revision($conexion),
            'propiedad' => [
                'nombre' => $propiedad->nombre,
                'pais' => $propiedad->pais,
                'moneda' => $propiedad->moneda,
                'zona_horaria' => $propiedad->zona_horaria,
                'idioma' => $propiedad->idioma,
            ],
        ];
    }

    /**
     * Una página de una colección, por identificador: no se salta ni repite filas si algo cambia a la mitad.
     *
     * @return array{filas: list<array<string, mixed>>, siguiente: int|null}
     */
    public function pagina(ConexionCompartida $conexion, string $coleccion, int $despues): array
    {
        $filas = match ($coleccion) {
            'unidades' => $this->unidades($conexion->propiedad_id, $despues),
            'huespedes' => $this->huespedes($conexion, $despues),
            'reservas' => $this->reservas($conexion->propiedad_id, $despues),
            'cajas' => $this->delaCaja('cajas', ['nombre', 'activa'], $conexion->propiedad_id, $despues),
            'pagos' => $this->delaCaja('pagos', ['reserva_id', 'caja_id', 'fecha', 'metodo', 'importe', 'moneda', 'referencia'], $conexion->propiedad_id, $despues),
            'movimientos' => $this->delaCaja('movimientos', ['caja_id', 'fecha', 'tipo', 'categoria', 'concepto', 'metodo', 'importe', 'moneda', 'referencia'], $conexion->propiedad_id, $despues),
            default => $this->bloqueos($conexion->propiedad_id, $despues),
        };

        return ['filas' => $filas, 'siguiente' => count($filas) === self::POR_PAGINA ? (int) $filas[count($filas) - 1]['id'] : null];
    }

    /**
     * Cambia si cambió algo de lo que se comparte: si no, la subordinada no baja nada.
     */
    private function revision(ConexionCompartida $conexion): string
    {
        $id = $conexion->propiedad_id;
        $partes = [$conexion->incluir_documentos];
        foreach (['unidades', 'huespedes', 'reservas', 'cajas', 'pagos', 'movimientos'] as $tabla) {
            $partes[] = DB::table($tabla)->where('propiedad_id', $id)->selectRaw('COUNT(*) AS cuantas, MAX(id) AS ultimo, MAX(actualizado_en) AS cambio')->first();
        }
        // Reemplazar un contacto no toca la ficha del huésped.
        $partes[] = DB::table('contactos_huesped as c')->join('huespedes as h', 'h.id', '=', 'c.huesped_id')->where('h.propiedad_id', $id)->selectRaw('COUNT(*) AS cuantas, MAX(c.id) AS ultimo')->first();
        $partes[] = DB::table('reservas_huespedes as a')->join('reservas as r', 'r.id', '=', 'a.reserva_id')->where('r.propiedad_id', $id)->selectRaw('COUNT(*) AS cuantas, MAX(a.id) AS ultimo')->first();
        $partes[] = DB::table('bloqueos_externos')->where('propiedad_id', $id)->whereNull('eliminado_en')->orderBy('id')->get(['id', 'unidad_id', 'llegada', 'salida'])->all();

        return hash('sha256', (string) json_encode($partes));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function unidades(int $propiedadId, int $despues): array
    {
        return DB::table('unidades')->where('propiedad_id', $propiedadId)->where('id', '>', $despues)->orderBy('id')->limit(self::POR_PAGINA)
            ->get(['id', 'codigo', 'nombre', 'piso', 'edificio', 'capacidad', 'orden', 'notas', 'activa', 'creado_en'])
            ->map(fn (stdClass $u): array => (array) $u)->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function huespedes(ConexionCompartida $conexion, int $despues): array
    {
        $columnas = ['id', 'creado_en', ...self::CAMPOS_HUESPED, ...self::CAMPOS_DOCUMENTO, 'documento_numero'];
        $huespedes = DB::table('huespedes')->where('propiedad_id', $conexion->propiedad_id)->where('id', '>', $despues)->orderBy('id')->limit(self::POR_PAGINA)->get($columnas);
        $ids = $huespedes->pluck('id')->all();
        $contactos = DB::table('contactos_huesped')->whereIn('huesped_id', $ids)->orderBy('orden')->get(['huesped_id', 'tipo', 'valor', 'orden'])->groupBy('huesped_id');
        $campos = DB::table('campos_huesped')->whereIn('huesped_id', $ids)->get(['huesped_id', 'campo', 'valor'])->groupBy('huesped_id');

        return $huespedes->map(function (stdClass $h) use ($conexion, $contactos, $campos): array {
            $fila = (array) $h;
            $cifrado = $fila['documento_numero'];
            unset($fila['documento_numero']);
            if ($conexion->incluir_documentos) {
                $fila['documento_numero'] = $this->descifrar($cifrado);
            } else {
                foreach (self::CAMPOS_DOCUMENTO as $campo) {
                    unset($fila[$campo]);
                }
            }
            $fila['contactos'] = $contactos->get($h->id, collect())->map(fn (stdClass $c): array => ['tipo' => $c->tipo, 'valor' => $c->valor, 'orden' => $c->orden])->values()->all();
            $fila['campos'] = $campos->get($h->id, collect())->map(fn (stdClass $c): array => ['campo' => $c->campo, 'valor' => $c->valor])->values()->all();

            return $fila;
        })->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function reservas(int $propiedadId, int $despues): array
    {
        $reservas = DB::table('reservas')->where('propiedad_id', $propiedadId)->where('id', '>', $despues)->orderBy('id')->limit(self::POR_PAGINA)->get(['id', ...self::CAMPOS_RESERVA]);
        $acompanantes = DB::table('reservas_huespedes')->whereIn('reserva_id', $reservas->pluck('id')->all())->get(['reserva_id', 'huesped_id', 'orden', 'parentesco_texto'])->groupBy('reserva_id');

        return $reservas->map(function (stdClass $r) use ($acompanantes): array {
            $fila = (array) $r;
            $fila['acompanantes'] = $acompanantes->get($r->id, collect())
                ->map(fn (stdClass $a): array => ['huesped_id' => $a->huesped_id, 'orden' => $a->orden, 'parentesco' => $a->parentesco_texto])->values()->all();

            return $fila;
        })->all();
    }

    /**
     * Las fechas ocupadas en los portales: sin ellas, la subordinada vería libre lo que no lo está.
     *
     * @return list<array<string, mixed>>
     */
    private function bloqueos(int $propiedadId, int $despues): array
    {
        return DB::table('bloqueos_externos as b')->leftJoin('fuentes_ical as f', 'f.id', '=', 'b.fuente_id')->where('b.propiedad_id', $propiedadId)->whereNull('b.eliminado_en')
            ->where('b.id', '>', $despues)->orderBy('b.id')->limit(self::POR_PAGINA)
            ->get(['b.id', 'b.unidad_id', 'b.llegada', 'b.salida', 'b.uid', 'f.nombre as fuente'])
            ->map(fn (stdClass $b): array => (array) $b + ['tipo' => TipoReserva::Bloqueo->value])->all();
    }

    /**
     * @param  list<string>  $campos
     * @return list<array<string, mixed>>
     */
    private function delaCaja(string $tabla, array $campos, int $propiedadId, int $despues): array
    {
        return DB::table($tabla)->where('propiedad_id', $propiedadId)->where('id', '>', $despues)->orderBy('id')->limit(self::POR_PAGINA)
            ->get(['id', ...$campos])->map(fn (stdClass $f): array => (array) $f)->all();
    }

    private function descifrar(mixed $cifrado): ?string
    {
        if (! is_string($cifrado) || $cifrado === '') {
            return null;
        }
        try {
            return Crypt::decryptString($cifrado);
        } catch (Throwable) {
            return null;
        }
    }
}
