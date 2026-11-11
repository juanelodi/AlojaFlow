<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Sincronizacion\HotelDruid\Proyeccion;

use App\Enums\TipoContacto;
use App\Support\Contactos;
use App\Support\Huellas;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Los clientes de la principal como huéspedes, con sus contactos y sus campos personalizados (subordinacion.md C4,
 * modelo-datos.md §4.5).
 */
final class HuespedesPrincipal
{
    /** Columna propia => [columna de la principal, cuánto cabe]. */
    private const TEXTOS = [
        'nombre' => ['nome', 100],
        'apellido1' => ['cognome', 100],
        'apellido2' => ['cognome2', 100],
        'apodo' => ['soprannome', 100],
        'tratamiento' => ['titolo', 30],
        'pais_nacimiento_texto' => ['nazionenascita', 100],
        'nacionalidad_texto' => ['nazionalita', 100],
        'documento_tipo_texto' => ['tipodoc', 100],
        'documento_pais_texto' => ['nazionedoc', 100],
        'domicilio_calle' => ['via', 150],
        'domicilio_numero' => ['numcivico', 30],
        'domicilio_ciudad' => ['citta', 100],
        'domicilio_region' => ['regione', 100],
        'domicilio_codigo_postal' => ['cap', 30],
        'domicilio_pais_texto' => ['nazione', 100],
        'id_fiscal_personal' => ['cod_fiscale', 50],
        'id_fiscal_empresa' => ['partita_iva', 50],
        'notas' => ['commento', 65000],
    ];

    /** Columna de la principal => tipo de contacto, en el orden en que quedan. */
    private const CONTACTOS = [
        'telefono' => TipoContacto::Telefono, 'telefono2' => TipoContacto::Telefono, 'telefono3' => TipoContacto::Telefono,
        'email' => TipoContacto::Correo, 'email2' => TipoContacto::Correo, 'email3' => TipoContacto::Correo,
        'fax' => TipoContacto::Fax,
    ];

    /** El tipo con que la principal guarda un campo personalizado en `relclienti`: nombre en `testo1`, valor en `testo3`. */
    private const CAMPO_PERSONALIZADO = 'campo_pers';

    public function __construct(private readonly Contexto $contexto)
    {
    }

    /**
     * Deja los huéspedes como en la copia y devuelve cómo encontrarlos.
     *
     * @return array<string, int> clave de origen (el `idclienti`) => huesped_id
     */
    public function proyectar(): array
    {
        $guardados = DB::table('huespedes')->where('principal_id', $this->contexto->principal->id)->whereNotNull('clave_origen')
            ->get(['id', 'clave_origen', 'huella_identidad', 'huella_datos', 'eliminado_en_principal_en'])->keyBy('clave_origen');
        $camposPorCliente = $this->camposPersonalizados();
        $ids = [];

        foreach ($this->contexto->espejo->filas('clienti') as $fila) {
            $clave = (string) ($fila['idclienti'] ?? '');
            if (! ctype_digit($clave) || isset($ids[$clave])) {
                $this->contexto->resultado->incidencia('huespedes_descartados');

                continue;
            }
            $datos = $this->datos($fila);
            $contactos = $this->contactos($fila);
            $campos = $camposPorCliente[$clave] ?? [];
            $huella = Huellas::datos(['datos' => $datos, 'contactos' => $contactos, 'campos' => $campos]);
            // Lo que hace que sea «el mismo» es su fecha de alta en la principal (modelo-datos.md §6).
            $identidad = hash('sha256', (string) ($fila['datainserimento'] ?? ''));
            $guardado = $guardados->get($clave);

            // Otra fecha de alta con el mismo número: la principal lo reutilizó. Con huellas de otra generación no se puede saber.
            if ($guardado !== null && $guardado->huella_identidad !== $identidad && $this->contexto->mismaGeneracion) {
                DB::table('huespedes')->where('id', $guardado->id)->update([
                    'clave_origen' => null, 'clave_origen_historica' => $clave,
                    'eliminado_en_principal_en' => $guardado->eliminado_en_principal_en ?? $this->contexto->ahora, 'actualizado_en' => $this->contexto->ahora,
                ]);
                $guardado = null;
            }

            if ($guardado === null) {
                $ids[$clave] = (int) DB::table('huespedes')->insertGetId(
                    $this->conDocumentoCifrado($datos) + $this->contexto->columnasOrigen($clave, $identidad, $huella) + ['propiedad_id' => $this->contexto->propiedadId()]
                );
                $this->guardarContactos($ids[$clave], $contactos, $campos);
                $this->contexto->resultado->contar('huespedes_nuevos');

                continue;
            }

            $ids[$clave] = (int) $guardado->id;
            if ($guardado->huella_datos !== $huella || $guardado->huella_identidad !== $identidad || $guardado->eliminado_en_principal_en !== null) {
                $this->ponerAlDia((int) $guardado->id, $datos, $contactos, $campos, $identidad, $huella);
            }
        }

        $ausentes = $guardados->filter(fn (object $huesped): bool => ! isset($ids[(string) $huesped->clave_origen]) && $huesped->eliminado_en_principal_en === null)->pluck('id');
        foreach ($ausentes->chunk(1000) as $grupo) {
            DB::table('huespedes')->whereIn('id', $grupo->all())->update(['eliminado_en_principal_en' => $this->contexto->ahora]);
        }

        return $ids;
    }

    /**
     * Escribe solo lo que cambió: si lo único distinto es la huella (otra generación del proyector), la ficha no se toca.
     *
     * @param  array<string, mixed>  $datos
     * @param  list<array<string, mixed>>  $contactos
     * @param  list<array{campo: string, valor: string|null}>  $campos
     */
    private function ponerAlDia(int $id, array $datos, array $contactos, array $campos, string $identidad, string $huella): void
    {
        $guardado = DB::table('huespedes')->where('id', $id)->first();
        if ($guardado === null) {
            return;
        }
        $comparables = array_diff_key($datos, ['documento_numero' => true])
            + ['documento_numero_hmac' => $datos['documento_numero'] === null ? null : Huellas::hmacDocumento((string) $datos['documento_numero'])];
        $cambios = ['huella_identidad' => $identidad, 'huella_datos' => $huella, 'eliminado_en_principal_en' => null, 'ejecucion_id' => $this->contexto->ejecucion->id];
        $cambio = $guardado->eliminado_en_principal_en !== null;
        if (Contexto::diferencias($guardado, $comparables) !== []) {
            $cambios += $this->conDocumentoCifrado($datos);
            $cambio = true;
        }
        if ($this->contactosGuardados($id) !== $contactos || $this->camposGuardados($id) !== $campos) {
            DB::table('contactos_huesped')->where('huesped_id', $id)->delete();
            DB::table('campos_huesped')->where('huesped_id', $id)->delete();
            $this->guardarContactos($id, $contactos, $campos);
            $cambio = true;
        }
        if ($cambio) {
            $cambios['actualizado_en'] = $this->contexto->ahora;
            $this->contexto->resultado->contar('huespedes_actualizados');
        }
        DB::table('huespedes')->where('id', $id)->update($cambios);
    }

    /**
     * @param  array<string, string|null>  $fila
     * @return array<string, mixed>
     */
    private function datos(array $fila): array
    {
        $conversor = $this->contexto->conversor;
        $datos = [];
        foreach (self::TEXTOS as $columna => [$origen, $maximo]) {
            $datos[$columna] = $conversor->texto($fila[$origen] ?? null, $maximo);
        }
        $idioma = strtolower(trim((string) ($fila['lingua'] ?? '')));

        return $datos + [
            'sexo' => match (strtolower(substr(trim((string) ($fila['sesso'] ?? '')), 0, 1))) {
                'm' => 'hombre',
                'f' => 'mujer',
                default => null,
            },
            'idioma' => in_array($idioma, (array) config('alojaflow.idiomas'), true) ? $idioma : null,
            'fecha_nacimiento' => $conversor->fecha($fila['datanascita'] ?? null),
            'lugar_nacimiento' => $this->lugar($fila['cittanascita'] ?? null, $fila['regionenascita'] ?? null),
            'documento_numero' => $conversor->texto($fila['documento'] ?? null, 100),
            'documento_vencimiento' => $conversor->fecha($fila['scadenzadoc'] ?? null),
            'documento_expedicion' => $this->lugar($fila['cittadoc'] ?? null, $fila['regionedoc'] ?? null),
            'creado_en_origen' => $conversor->fechaHora($fila['datainserimento'] ?? null),
        ];
    }

    /**
     * Ciudad y región en un solo texto, como se guarda aquí.
     */
    private function lugar(?string $ciudad, ?string $region): ?string
    {
        $conversor = $this->contexto->conversor;
        $partes = array_filter([$conversor->texto($ciudad, 120), $conversor->texto($region, 120)], fn (?string $parte): bool => $parte !== null);

        return $partes === [] ? null : mb_substr(implode(', ', $partes), 0, 255);
    }

    /**
     * @param  array<string, string|null>  $fila
     * @return list<array{tipo: string, valor: string, valor_normalizado: string, orden: int}>
     */
    private function contactos(array $fila): array
    {
        $contactos = [];
        foreach (self::CONTACTOS as $columna => $tipo) {
            $valor = $this->contexto->conversor->texto($fila[$columna] ?? null, 255);
            if ($valor !== null) {
                $contactos[] = ['tipo' => $tipo->value, 'valor' => $valor, 'valor_normalizado' => Contactos::normalizar($tipo->value, $valor), 'orden' => count($contactos)];
            }
        }

        return $contactos;
    }

    /**
     * @return array<string, list<array{campo: string, valor: string|null}>> `idclienti` => sus campos
     */
    private function camposPersonalizados(): array
    {
        $campos = [];
        foreach ($this->contexto->espejo->filas('relclienti') as $fila) {
            $campo = $this->contexto->conversor->texto($fila['testo1'] ?? null, 100);
            if (($fila['tipo'] ?? null) === self::CAMPO_PERSONALIZADO && $campo !== null) {
                $campos[(string) ($fila['idclienti'] ?? '')][] = ['campo' => $campo, 'valor' => $this->contexto->conversor->texto($fila['testo3'] ?? null, 65000)];
            }
        }

        return $campos;
    }

    /**
     * @return list<array{tipo: string, valor: string, valor_normalizado: string, orden: int}>
     */
    private function contactosGuardados(int $huespedId): array
    {
        return DB::table('contactos_huesped')->where('huesped_id', $huespedId)->orderBy('orden')->orderBy('id')->get(['tipo', 'valor', 'valor_normalizado', 'orden'])
            ->map(fn (object $c): array => ['tipo' => (string) $c->tipo, 'valor' => (string) $c->valor, 'valor_normalizado' => (string) $c->valor_normalizado, 'orden' => (int) $c->orden])
            ->values()->all();
    }

    /**
     * @return list<array{campo: string, valor: string|null}>
     */
    private function camposGuardados(int $huespedId): array
    {
        return DB::table('campos_huesped')->where('huesped_id', $huespedId)->orderBy('id')->get(['campo', 'valor'])
            ->map(fn (object $c): array => ['campo' => (string) $c->campo, 'valor' => $c->valor === null ? null : (string) $c->valor])
            ->values()->all();
    }

    /**
     * @param  list<array<string, mixed>>  $contactos
     * @param  list<array{campo: string, valor: string|null}>  $campos
     */
    private function guardarContactos(int $huespedId, array $contactos, array $campos): void
    {
        if ($contactos !== []) {
            DB::table('contactos_huesped')->insert(array_map(fn (array $contacto): array => $contacto + ['huesped_id' => $huespedId], $contactos));
        }
        if ($campos !== []) {
            DB::table('campos_huesped')->insert(array_map(fn (array $campo): array => $campo + ['huesped_id' => $huespedId], $campos));
        }
    }

    /**
     * El número de documento se guarda cifrado, con un HMAC aparte para poder buscarlo (D-022).
     *
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private function conDocumentoCifrado(array $datos): array
    {
        $numero = $datos['documento_numero'];
        $datos['documento_numero'] = $numero === null ? null : Crypt::encryptString((string) $numero);
        $datos['documento_numero_hmac'] = $numero === null ? null : Huellas::hmacDocumento((string) $numero);

        return $datos;
    }
}
