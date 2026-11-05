<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Sincronizacion\AlojaFlow;

use App\Enums\EstadoReserva;
use App\Enums\OrigenFila;
use App\Enums\TipoContacto;
use App\Enums\TipoEventoReserva;
use App\Enums\TipoReserva;
use App\Models\EjecucionSincronizacion;
use App\Models\Principal;
use App\Sincronizacion\ContextoSincronizacion;
use App\Sincronizacion\HotelDruid\ResultadoProyeccion;
use App\Sincronizacion\NochesProyectadas;
use App\Support\Contactos;
use App\Support\Huellas;
use Carbon\CarbonImmutable;
use DateTimeZone;
use Generator;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Convierte lo que mandó otra instalación en el modelo propio, como lo hace el de HotelDruid: unidades, huéspedes, reservas y noches,
 * con sus eventos. Lo que llega se trata como dato ajeno: se recorta y se comprueba, porque viene de otro servidor.
 */
class Proyector
{
    private const LOTE = 500;

    private const CAMPOS_TEXTO_HUESPED = [
        'nombre' => 100, 'apellido1' => 100, 'apellido2' => 100, 'apodo' => 100, 'tratamiento' => 30, 'lugar_nacimiento' => 255, 'pais_nacimiento_texto' => 100, 'nacionalidad_texto' => 100,
        'documento_tipo_texto' => 100, 'documento_expedicion' => 255, 'documento_pais_texto' => 100, 'domicilio_calle' => 150, 'domicilio_numero' => 30, 'domicilio_ciudad' => 100,
        'domicilio_region' => 100, 'domicilio_codigo_postal' => 30, 'domicilio_pais_texto' => 100, 'id_fiscal_personal' => 50, 'id_fiscal_empresa' => 50, 'notas' => 65000,
    ];

    private Principal $principal;

    private EjecucionSincronizacion $ejecucion;

    private ResultadoProyeccion $resultado;

    private bool $generarEventos;

    private string $ahora;

    /** @var list<array<string, mixed>> */
    private array $eventos = [];

    /**
     * @param  array<string, mixed>  $propiedadRemota
     * @param  array<string, string>  $archivos  colección => archivo con una fila por línea
     */
    public function proyectar(Principal $principal, EjecucionSincronizacion $ejecucion, array $propiedadRemota, array $archivos, bool $generarEventos): ResultadoProyeccion
    {
        $this->principal = $principal;
        $this->ejecucion = $ejecucion;
        $this->generarEventos = $generarEventos;
        $this->resultado = new ResultadoProyeccion();
        $this->eventos = [];
        $this->ahora = CarbonImmutable::now('UTC')->format('Y-m-d H:i:s');

        ContextoSincronizacion::ejecutar(function () use ($propiedadRemota, $archivos): void {
            DB::transaction(function () use ($propiedadRemota, $archivos): void {
                $this->ajustarPropiedad($propiedadRemota);
                $unidades = $this->proyectarUnidades($archivos['unidades']);
                $huespedes = $this->proyectarHuespedes($archivos['huespedes']);
                $this->proyectarReservas($archivos['reservas'], $archivos['bloqueos'], $unidades, $huespedes);
                foreach (array_chunk($this->eventos, self::LOTE) as $lote) {
                    DB::table('eventos_reserva')->insert($lote);
                }
                $this->resultado->eventos = count($this->eventos);
            });
        });

        return $this->resultado;
    }

    /**
     * Mientras la subordinada no tenga reservas, toma la moneda y la zona de la principal: si no, los importes y las fechas no cuadran.
     *
     * @param  array<string, mixed>  $remota
     */
    private function ajustarPropiedad(array $remota): void
    {
        $propiedadId = $this->principal->propiedad_id;
        if (DB::table('reservas')->where('propiedad_id', $propiedadId)->exists()) {
            return;
        }
        $cambios = [];
        $moneda = strtoupper((string) ($remota['moneda'] ?? ''));
        if (preg_match('/^[A-Z]{3}$/', $moneda)) {
            $cambios['moneda'] = $moneda;
        }
        $zona = (string) ($remota['zona_horaria'] ?? '');
        if (in_array($zona, DateTimeZone::listIdentifiers(), true)) {
            $cambios['zona_horaria'] = $zona;
        }
        if ($cambios !== []) {
            DB::table('propiedades')->where('id', $propiedadId)->update($cambios);
            $this->principal->unsetRelation('propiedad');
        }
    }

    /**
     * @return array<int, int> id en la principal => unidad_id
     */
    private function proyectarUnidades(string $archivo): array
    {
        $existentes = DB::table('unidades')->where('principal_id', $this->principal->id)->whereNotNull('clave_origen')
            ->get(['id', 'clave_origen', 'huella_datos', 'activa'])->keyBy('clave_origen');
        $mapa = [];

        foreach ($this->filas($archivo) as $f) {
            $remota = $this->entero($f['id'] ?? null);
            if ($remota <= 0) {
                continue;
            }
            $clave = (string) $remota;
            $datos = [
                'codigo' => $this->texto($f['codigo'] ?? null, 100) ?? $clave,
                'nombre' => $this->texto($f['nombre'] ?? null, 100) ?? $clave,
                'piso' => $this->texto($f['piso'] ?? null, 50),
                'edificio' => $this->texto($f['edificio'] ?? null, 50),
                'capacidad' => $this->pequeno($f['capacidad'] ?? null),
                'orden' => $this->entero($f['orden'] ?? null),
                'notas' => $this->texto($f['notas'] ?? null, 65000),
            ];
            $activa = (bool) ($f['activa'] ?? true);
            $huella = Huellas::datos($datos + ['activa' => $activa]);
            /** @var stdClass|null $existente */
            $existente = $existentes->get($clave);
            $this->liberarCodigo((string) $datos['codigo'], $existente === null ? null : (int) $existente->id);

            if ($existente === null) {
                $mapa[$remota] = (int) DB::table('unidades')->insertGetId($datos + $this->columnasOrigen($clave, hash('sha256', $clave), $huella) + ['propiedad_id' => $this->principal->propiedad_id, 'activa' => $activa]);
                $this->resultado->contar('unidades_nuevas');
            } else {
                $mapa[$remota] = (int) $existente->id;
                if ($existente->huella_datos !== $huella) {
                    DB::table('unidades')->where('id', $existente->id)->update($datos + ['huella_datos' => $huella, 'activa' => $activa, 'ejecucion_id' => $this->ejecucion->id, 'actualizado_en' => $this->ahora]);
                    $this->resultado->contar('unidades_actualizadas');
                }
            }
        }

        $desaparecidas = $existentes->keys()->diff(array_map('strval', array_keys($mapa)))->all();
        if ($desaparecidas !== []) {
            DB::table('unidades')->where('principal_id', $this->principal->id)->whereIn('clave_origen', $desaparecidas)->update(['activa' => false, 'huella_datos' => null, 'actualizado_en' => $this->ahora]);
        }

        return $mapa;
    }

    /**
     * El código es único en la propiedad. En la principal se puede borrar la unidad «101» y crear otra igual, o intercambiar dos: la
     * que lo tenía aquí se hace a un lado, y si sigue viva recibe el suyo al llegar su turno.
     */
    private function liberarCodigo(string $codigo, ?int $unidadId): void
    {
        $ocupante = DB::table('unidades')->where('propiedad_id', $this->principal->propiedad_id)->where('codigo', $codigo)->when($unidadId !== null, fn ($q) => $q->where('id', '!=', $unidadId))->value('id');
        if ($ocupante !== null) {
            DB::table('unidades')->where('id', $ocupante)->update(['codigo' => mb_substr($codigo, 0, 80).'~'.$ocupante, 'huella_datos' => null]);
        }
    }

    /**
     * @return array<int, int> id en la principal => huesped_id
     */
    private function proyectarHuespedes(string $archivo): array
    {
        $existentes = DB::table('huespedes')->where('principal_id', $this->principal->id)->whereNotNull('clave_origen')
            ->get(['id', 'clave_origen', 'huella_identidad', 'huella_datos', 'eliminado_en_principal_en'])->keyBy('clave_origen');
        $mapa = [];

        foreach ($this->filas($archivo) as $f) {
            $remota = $this->entero($f['id'] ?? null);
            if ($remota <= 0) {
                continue;
            }
            $clave = (string) $remota;
            $datos = $this->datosHuesped($f);
            $contactos = $this->contactos($f);
            $campos = $this->campos($f);
            $huella = Huellas::datos(['datos' => $datos, 'contactos' => $contactos, 'campos' => $campos]);
            $identidad = hash('sha256', (string) ($f['creado_en'] ?? ''));
            /** @var stdClass|null $existente */
            $existente = $existentes->get($clave);

            if ($existente !== null && $existente->huella_identidad === $identidad) {
                $mapa[$remota] = (int) $existente->id;
                if ($existente->huella_datos !== $huella || $existente->eliminado_en_principal_en !== null) {
                    DB::table('huespedes')->where('id', $existente->id)->update($this->cifrarDocumento($datos) + [
                        'huella_datos' => $huella, 'eliminado_en_principal_en' => null, 'ejecucion_id' => $this->ejecucion->id, 'actualizado_en' => $this->ahora,
                    ]);
                    DB::table('contactos_huesped')->where('huesped_id', $existente->id)->delete();
                    DB::table('campos_huesped')->where('huesped_id', $existente->id)->delete();
                    $this->guardarContactos((int) $existente->id, $contactos, $campos);
                    $this->resultado->contar('huespedes_actualizados');
                }

                continue;
            }

            if ($existente !== null) {
                // La principal se restauró y reutilizó el número: la ficha vieja queda como eliminada.
                DB::table('huespedes')->where('id', $existente->id)->update(['clave_origen' => null, 'clave_origen_historica' => $clave, 'eliminado_en_principal_en' => $this->ahora, 'actualizado_en' => $this->ahora]);
            }
            $id = (int) DB::table('huespedes')->insertGetId($this->cifrarDocumento($datos) + $this->columnasOrigen($clave, $identidad, $huella) + ['propiedad_id' => $this->principal->propiedad_id]);
            $this->guardarContactos($id, $contactos, $campos);
            $mapa[$remota] = $id;
            $this->resultado->contar('huespedes_nuevos');
        }

        $desaparecidos = $existentes->keys()->diff(array_map('strval', array_keys($mapa)))->all();
        foreach (array_chunk($desaparecidos, 1000) as $grupo) {
            DB::table('huespedes')->where('principal_id', $this->principal->id)->whereIn('clave_origen', $grupo)->whereNull('eliminado_en_principal_en')->update(['eliminado_en_principal_en' => $this->ahora]);
        }

        return $mapa;
    }

    /**
     * @param  list<array<string, mixed>>  $contactos
     * @param  list<array{campo: string, valor: string|null}>  $campos
     */
    private function guardarContactos(int $huespedId, array $contactos, array $campos): void
    {
        if ($contactos !== []) {
            DB::table('contactos_huesped')->insert(array_map(fn (array $c): array => $c + ['huesped_id' => $huespedId], $contactos));
        }
        if ($campos !== []) {
            DB::table('campos_huesped')->insert(array_map(fn (array $c): array => $c + ['huesped_id' => $huespedId], $campos));
        }
    }

    /**
     * @param  array<int, int>  $unidades
     * @param  array<int, int>  $huespedes
     */
    private function proyectarReservas(string $archivoReservas, string $archivoBloqueos, array $unidades, array $huespedes): void
    {
        $existentes = DB::table('reservas')->where('principal_id', $this->principal->id)->whereNotNull('clave_origen')
            ->get(['id', 'clave_origen', 'huella_identidad', 'huella_datos', 'estado', 'eliminada_en_principal_en', 'llegada', 'salida', 'unidad_id', 'total'])->keyBy('clave_origen');
        $vistas = [];

        foreach ($this->filas($archivoReservas) as $f) {
            $remota = $this->entero($f['id'] ?? null);
            $datos = $remota > 0 ? $this->datosReserva($f, $unidades, $huespedes) : null;
            if ($datos === null) {
                continue;
            }
            $grupo = $this->acompanantes($f, $huespedes);
            $clave = (string) $remota;
            $vistas[$clave] = true;
            $this->aplicarReserva($existentes->get($clave), $clave, hash('sha256', (string) ($f['creado_en'] ?? '')), $datos, $grupo);
        }
        foreach ($this->filas($archivoBloqueos) as $f) {
            $remota = $this->entero($f['id'] ?? null);
            $datos = $remota > 0 ? $this->datosBloqueo($f, $remota, $unidades) : null;
            if ($datos === null) {
                continue;
            }
            $clave = 'b:'.$remota;
            $vistas[$clave] = true;
            $this->aplicarReserva($existentes->get($clave), $clave, hash('sha256', (string) ($f['uid'] ?? '')), $datos, []);
        }

        foreach ($existentes as $clave => $existente) {
            if (! isset($vistas[(string) $clave]) && $existente->eliminada_en_principal_en === null) {
                $this->eliminarReserva($existente, false);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $datos
     * @param  list<array<string, mixed>>  $acompanantes
     */
    private function aplicarReserva(?stdClass $existente, string $clave, string $identidad, array $datos, array $acompanantes): void
    {
        $huella = Huellas::datos(['datos' => $datos, 'acompanantes' => $acompanantes]);
        if ($existente !== null && $existente->huella_identidad === $identidad) {
            if ($existente->huella_datos !== $huella || $existente->eliminada_en_principal_en !== null) {
                $this->actualizarReserva($existente, $datos, $huella, $acompanantes);
            }

            return;
        }
        if ($existente !== null) {
            $this->eliminarReserva($existente, true);
        }
        $id = (int) DB::table('reservas')->insertGetId($datos + $this->columnasOrigen($clave, $identidad, $huella) + ['codigo_publico' => Huellas::codigoPublico()]);
        $this->guardarNochesYAcompanantes($id, $datos, $acompanantes);
        $this->evento($id, TipoEventoReserva::Creada, ['despues' => $this->resumen($datos)]);
        $this->resultado->contar('reservas_nuevas');
    }

    /**
     * @param  array<string, mixed>  $datos
     * @param  list<array<string, mixed>>  $acompanantes
     */
    private function actualizarReserva(stdClass $existente, array $datos, string $huella, array $acompanantes): void
    {
        DB::table('reservas')->where('id', $existente->id)->update($datos + ['huella_datos' => $huella, 'eliminada_en_principal_en' => null, 'ejecucion_id' => $this->ejecucion->id, 'actualizado_en' => $this->ahora]);
        DB::table('reservas_noches')->where('reserva_id', $existente->id)->delete();
        DB::table('reservas_huespedes')->where('reserva_id', $existente->id)->delete();
        $this->guardarNochesYAcompanantes((int) $existente->id, $datos, $acompanantes);

        $tipo = match (true) {
            $existente->eliminada_en_principal_en !== null => TipoEventoReserva::Reactivada,
            $datos['estado'] === EstadoReserva::Cancelada->value && $existente->estado !== EstadoReserva::Cancelada->value => TipoEventoReserva::Cancelada,
            default => TipoEventoReserva::Modificada,
        };
        $this->evento((int) $existente->id, $tipo, [
            'antes' => ['llegada' => (string) $existente->llegada, 'salida' => (string) $existente->salida, 'unidad_id' => (int) $existente->unidad_id, 'estado' => (string) $existente->estado, 'total' => (int) $existente->total],
            'despues' => $this->resumen($datos),
        ]);
        $this->resultado->contar('reservas_actualizadas');
    }

    private function eliminarReserva(stdClass $existente, bool $reutilizada): void
    {
        $cambios = ['eliminada_en_principal_en' => $this->ahora, 'actualizado_en' => $this->ahora];
        if ($reutilizada) {
            $cambios += ['clave_origen' => null, 'clave_origen_historica' => $existente->clave_origen];
        }
        DB::table('reservas')->where('id', $existente->id)->update($cambios);
        DB::table('reservas_noches')->where('reserva_id', $existente->id)->delete();
        $this->evento((int) $existente->id, TipoEventoReserva::Eliminada, ['reutilizada' => $reutilizada]);
        $this->resultado->contar('reservas_eliminadas');
    }

    /**
     * @param  array<string, mixed>  $datos
     * @param  list<array<string, mixed>>  $acompanantes
     */
    private function guardarNochesYAcompanantes(int $reservaId, array $datos, array $acompanantes): void
    {
        foreach (array_chunk(NochesProyectadas::de($reservaId, $datos), 1000) as $lote) {
            DB::table('reservas_noches')->insert($lote);
        }
        if ($acompanantes !== []) {
            DB::table('reservas_huespedes')->insert(array_map(fn (array $a): array => $a + ['reserva_id' => $reservaId], $acompanantes));
        }
    }

    /**
     * @param  array<string, mixed>  $f
     * @param  array<int, int>  $unidades
     * @param  array<int, int>  $huespedes
     * @return array<string, mixed>|null
     */
    private function datosReserva(array $f, array $unidades, array $huespedes): ?array
    {
        $llegada = $this->fecha($f['llegada'] ?? null);
        $salida = $this->fecha($f['salida'] ?? null);
        if ($llegada === null || $salida === null || $salida <= $llegada) {
            $this->resultado->incidencia('reservas_sin_fechas');

            return null;
        }
        $unidadId = $unidades[$this->entero($f['unidad_id'] ?? null)] ?? null;
        if ($unidadId === null) {
            $this->resultado->incidencia('reservas_sin_unidad');

            return null;
        }
        $propiedad = $this->principal->propiedad;

        return [
            'propiedad_id' => $this->principal->propiedad_id,
            'unidad_id' => $unidadId,
            'titular_id' => $huespedes[$this->entero($f['titular_id'] ?? null)] ?? null,
            'tipo' => (TipoReserva::tryFrom((string) ($f['tipo'] ?? '')) ?? TipoReserva::Reserva)->value,
            'estado' => (EstadoReserva::tryFrom((string) ($f['estado'] ?? '')) ?? EstadoReserva::SinConfirmar)->value,
            'numero' => $this->texto($f['numero'] ?? null, 20) ?? (string) $this->entero($f['id'] ?? null),
            'llegada' => $llegada,
            'salida' => $salida,
            'personas' => $this->pequeno($f['personas'] ?? null),
            'origen_texto' => $this->texto($f['origen_texto'] ?? null, 100),
            'grupo_codigo' => $this->texto($f['grupo_codigo'] ?? null, 30),
            'tarifa_nombre' => $this->texto($f['tarifa_nombre'] ?? null, 100),
            'moneda' => preg_match('/^[A-Z]{3}$/', (string) ($f['moneda'] ?? '')) ? (string) $f['moneda'] : $propiedad->moneda,
            'total' => $this->entero($f['total'] ?? null),
            'pagado' => $this->entero($f['pagado'] ?? null),
            'deposito' => $this->entero($f['deposito'] ?? null),
            'descuento' => $this->entero($f['descuento'] ?? null),
            'comision' => $this->entero($f['comision'] ?? null),
            'impuestos_porcentaje' => is_numeric($f['impuestos_porcentaje'] ?? null) ? min(999.0, max(0.0, (float) $f['impuestos_porcentaje'])) : null,
            'entrada_registrada_en' => $this->fechaHora($f['entrada_registrada_en'] ?? null),
            'salida_registrada_en' => $this->fechaHora($f['salida_registrada_en'] ?? null),
            'comentario' => $this->texto($f['comentario'] ?? null, 65000),
            'creada_en_origen' => $this->fechaHora($f['creado_en'] ?? null),
            'modificada_en_origen' => $this->fechaHora($f['actualizado_en'] ?? null),
        ];
    }

    /**
     * Fechas ocupadas en un portal: en la subordinada se ven como un bloqueo más.
     *
     * @param  array<string, mixed>  $f
     * @param  array<int, int>  $unidades
     * @return array<string, mixed>|null
     */
    private function datosBloqueo(array $f, int $remota, array $unidades): ?array
    {
        $llegada = $this->fecha($f['llegada'] ?? null);
        $salida = $this->fecha($f['salida'] ?? null);
        $unidadId = $unidades[$this->entero($f['unidad_id'] ?? null)] ?? null;
        if ($llegada === null || $salida === null || $salida <= $llegada || $unidadId === null) {
            $this->resultado->incidencia('bloqueos_descartados');

            return null;
        }

        return [
            'propiedad_id' => $this->principal->propiedad_id,
            'unidad_id' => $unidadId,
            'titular_id' => null,
            'tipo' => TipoReserva::Bloqueo->value,
            'estado' => EstadoReserva::Confirmada->value,
            'numero' => mb_substr('B-'.$remota, 0, 20),
            'llegada' => $llegada,
            'salida' => $salida,
            'personas' => 0,
            'origen_texto' => $this->texto($f['fuente'] ?? null, 100),
            'moneda' => $this->principal->propiedad->moneda,
            'total' => 0,
        ];
    }

    /**
     * @param  array<string, mixed>  $f
     * @param  array<int, int>  $huespedes
     * @return list<array<string, mixed>>
     */
    private function acompanantes(array $f, array $huespedes): array
    {
        $filas = [];
        foreach ((array) ($f['acompanantes'] ?? []) as $a) {
            $huespedId = is_array($a) ? ($huespedes[$this->entero($a['huesped_id'] ?? null)] ?? null) : null;
            if ($huespedId !== null) {
                $filas[$huespedId] = ['huesped_id' => $huespedId, 'orden' => $this->pequeno($a['orden'] ?? null), 'parentesco_texto' => $this->texto($a['parentesco'] ?? null, 70)];
            }
        }

        return array_values($filas);
    }

    /**
     * @param  array<string, mixed>  $f
     * @return array<string, mixed>
     */
    private function datosHuesped(array $f): array
    {
        $datos = [];
        foreach (self::CAMPOS_TEXTO_HUESPED as $campo => $maximo) {
            $datos[$campo] = $this->texto($f[$campo] ?? null, $maximo);
        }
        $sexo = (string) ($f['sexo'] ?? '');
        $idioma = $f['idioma'] ?? null;
        $nacionalidad = (string) ($f['nacionalidad'] ?? '');
        $pais = (string) ($f['domicilio_pais'] ?? '');

        return $datos + [
            'sexo' => in_array($sexo, ['hombre', 'mujer'], true) ? $sexo : null,
            'idioma' => in_array($idioma, (array) config('alojaflow.idiomas'), true) ? $idioma : null,
            'fecha_nacimiento' => $this->fecha($f['fecha_nacimiento'] ?? null),
            'nacionalidad' => preg_match('/^[A-Z]{2}$/', $nacionalidad) ? $nacionalidad : null,
            'domicilio_pais' => preg_match('/^[A-Z]{2}$/', $pais) ? $pais : null,
            'documento_vencimiento' => $this->fecha($f['documento_vencimiento'] ?? null),
            'documento_numero' => $this->texto($f['documento_numero'] ?? null, 100),
            'creado_en_origen' => $this->fechaHora($f['creado_en'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private function cifrarDocumento(array $datos): array
    {
        $numero = $datos['documento_numero'];
        $datos['documento_numero'] = $numero === null ? null : Crypt::encryptString((string) $numero);
        $datos['documento_numero_hmac'] = $numero === null ? null : Huellas::hmacDocumento((string) $numero);

        return $datos;
    }

    /**
     * @param  array<string, mixed>  $f
     * @return list<array<string, mixed>>
     */
    private function contactos(array $f): array
    {
        $contactos = [];
        foreach ((array) ($f['contactos'] ?? []) as $c) {
            $tipo = is_array($c) ? TipoContacto::tryFrom((string) ($c['tipo'] ?? '')) : null;
            $valor = is_array($c) ? $this->texto($c['valor'] ?? null, 255) : null;
            if ($tipo !== null && $valor !== null) {
                $contactos[] = ['tipo' => $tipo->value, 'valor' => $valor, 'valor_normalizado' => Contactos::normalizar($tipo->value, $valor), 'orden' => min(255, $this->pequeno($c['orden'] ?? null))];
            }
        }

        return $contactos;
    }

    /**
     * @param  array<string, mixed>  $f
     * @return list<array{campo: string, valor: string|null}>
     */
    private function campos(array $f): array
    {
        $campos = [];
        foreach ((array) ($f['campos'] ?? []) as $c) {
            $campo = is_array($c) ? $this->texto($c['campo'] ?? null, 100) : null;
            if ($campo !== null) {
                $campos[] = ['campo' => $campo, 'valor' => $this->texto($c['valor'] ?? null, 65000)];
            }
        }

        return $campos;
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function evento(int $reservaId, TipoEventoReserva $tipo, array $datos): void
    {
        // La primera carga no genera eventos: no son cambios (comunicacion-eventos.md).
        if ($this->generarEventos) {
            $this->eventos[] = ['propiedad_id' => $this->principal->propiedad_id, 'reserva_id' => $reservaId, 'tipo' => $tipo->value, 'datos' => json_encode($datos, JSON_UNESCAPED_UNICODE), 'ocurrido_en' => $this->ahora];
        }
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private function resumen(array $datos): array
    {
        return ['llegada' => $datos['llegada'], 'salida' => $datos['salida'], 'unidad_id' => $datos['unidad_id'], 'estado' => $datos['estado'], 'total' => $datos['total']];
    }

    /**
     * @return array<string, mixed>
     */
    private function columnasOrigen(string $clave, string $identidad, string $huella): array
    {
        return [
            'origen' => OrigenFila::OtraInstalacion->value,
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
     * @return Generator<int, array<string, mixed>>
     */
    private function filas(string $archivo): Generator
    {
        $entrada = fopen($archivo, 'rb');
        if ($entrada === false) {
            return;
        }
        try {
            while (($linea = fgets($entrada)) !== false) {
                $fila = json_decode($linea, true);
                if (is_array($fila)) {
                    yield $fila;
                }
            }
        } finally {
            fclose($entrada);
        }
    }

    private function texto(mixed $valor, int $maximo): ?string
    {
        if (! is_scalar($valor)) {
            return null;
        }
        $texto = trim((string) $valor);

        return $texto === '' ? null : mb_substr($texto, 0, $maximo);
    }

    private function entero(mixed $valor): int
    {
        return is_numeric($valor) ? (int) $valor : 0;
    }

    /**
     * Para columnas pequeñas: un número fuera de rango detendría toda la copia.
     */
    private function pequeno(mixed $valor): int
    {
        return min(65535, max(0, $this->entero($valor)));
    }

    private function fecha(mixed $valor): ?string
    {
        if (! is_string($valor) || ! preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $valor, $m) || ! checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return null;
        }

        return $m[0];
    }

    private function fechaHora(mixed $valor): ?string
    {
        if ($this->fecha($valor) === null || ! preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}/', (string) $valor)) {
            return null;
        }

        return str_replace('T', ' ', substr((string) $valor, 0, 19));
    }
}
