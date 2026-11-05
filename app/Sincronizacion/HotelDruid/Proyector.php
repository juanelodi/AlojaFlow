<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Sincronizacion\HotelDruid;

use App\Enums\EstadoReserva;
use App\Enums\MetodoPago;
use App\Enums\OrigenFila;
use App\Enums\TipoEventoReserva;
use App\Enums\TipoMovimiento;
use App\Enums\TipoReserva;
use App\Models\EjecucionSincronizacion;
use App\Models\Principal;
use App\Sincronizacion\ContextoSincronizacion;
use App\Sincronizacion\NochesProyectadas;
use App\Sincronizacion\ProyeccionCaja;
use App\Support\Contactos;
use App\Support\Huellas;
use Carbon\CarbonImmutable;
use Generator;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Convierte el espejo de una principal en el modelo propio: unidades, huéspedes, reservas y
 * noches; detecta los cambios y genera los eventos (modelo-datos.md §6; subordinacion.md C).
 * Todo en una transacción: si algo falla, la proyección anterior sigue intacta.
 */
class Proyector
{
    private const LOTE = 500;

    private Principal $principal;

    private EjecucionSincronizacion $ejecucion;

    private Conversor $conversor;

    private ResultadoProyeccion $resultado;

    private bool $generarEventos;

    private string $ahora;

    /** @var list<array<string, mixed>> */
    private array $eventos = [];

    public function proyectar(Principal $principal, EjecucionSincronizacion $ejecucion, bool $generarEventos): ResultadoProyeccion
    {
        $this->principal = $principal;
        $this->ejecucion = $ejecucion;
        $this->generarEventos = $generarEventos;
        $this->resultado = new ResultadoProyeccion();
        $this->eventos = [];
        $this->ahora = CarbonImmutable::now('UTC')->format('Y-m-d H:i:s');
        $propiedad = $principal->propiedad;
        $this->conversor = new Conversor($propiedad->zona_horaria, $propiedad->moneda);

        ContextoSincronizacion::ejecutar(function (): void {
            DB::transaction(function (): void {
                $unidades = $this->proyectarUnidades();
                $huespedes = $this->proyectarHuespedes();
                $this->proyectarReservas($unidades, $huespedes);
                $this->proyectarCaja();
                $this->guardarEventos();
            });
        });

        return $this->resultado;
    }

    /**
     * @return array<int|string, int> idappartamenti => unidad_id (PHP guarda las claves numéricas como enteros)
     */
    private function proyectarUnidades(): array
    {
        $existentes = DB::table('unidades')->where('principal_id', $this->principal->id)->whereNotNull('clave_origen')
            ->get(['id', 'clave_origen', 'huella_datos', 'activa'])->keyBy('clave_origen');
        $mapa = [];

        foreach ($this->filas('appartamenti') as [, $f]) {
            $clave = trim((string) ($f['idappartamenti'] ?? ''));
            if ($clave === '') {
                continue;
            }
            $datos = [
                'codigo' => mb_substr($clave, 0, 100),
                'nombre' => mb_substr($clave, 0, 100),
                'piso' => $this->conversor->texto($f['numpiano'] ?? null, 50),
                'edificio' => $this->conversor->texto($f['numcasa'] ?? null, 50),
                'capacidad' => max(0, (int) ($f['maxoccupanti'] ?? 0)),
                'orden' => (int) ($f['priorita'] ?? 0),
                'notas' => $this->conversor->texto($f['commento'] ?? null, 65000),
            ];
            $huella = Huellas::datos($datos);
            /** @var stdClass|null $existente */
            $existente = $existentes->get($clave);

            if ($existente === null) {
                $mapa[$clave] = (int) DB::table('unidades')->insertGetId($datos + $this->columnasOrigen($clave, hash('sha256', $clave), $huella) + [
                    'propiedad_id' => $this->principal->propiedad_id,
                    'activa' => true,
                ]);
                $this->resultado->contar('unidades_nuevas');
            } else {
                $mapa[$clave] = (int) $existente->id;
                if ($existente->huella_datos !== $huella || ! $existente->activa) {
                    DB::table('unidades')->where('id', $existente->id)->update($datos + [
                        'huella_datos' => $huella, 'activa' => true, 'ejecucion_id' => $this->ejecucion->id, 'actualizado_en' => $this->ahora,
                    ]);
                    $this->resultado->contar('unidades_actualizadas');
                }
            }
        }

        $desaparecidas = $existentes->keys()->diff(array_keys($mapa))->all();
        if ($desaparecidas !== []) {
            DB::table('unidades')->where('principal_id', $this->principal->id)->whereIn('clave_origen', $desaparecidas)
                ->update(['activa' => false, 'actualizado_en' => $this->ahora]);
        }

        return $mapa;
    }

    /**
     * @return array<int, int> idclienti => huesped_id
     */
    private function proyectarHuespedes(): array
    {
        $campos = [];
        foreach ($this->filas('relclienti') as [, $f]) {
            if (($f['tipo'] ?? '') === 'campo_pers' && ($f['testo1'] ?? '') !== '') {
                $campos[(int) $f['idclienti']][(string) $f['testo1']] = $f['testo3'] ?? null;
            }
        }

        $existentes = DB::table('huespedes')->where('principal_id', $this->principal->id)->whereNotNull('clave_origen')
            ->get(['id', 'clave_origen', 'huella_identidad', 'huella_datos', 'eliminado_en_principal_en'])->keyBy('clave_origen');
        $mapa = [];
        $pendientes = [];

        foreach ($this->filas('clienti') as [, $f]) {
            $id = (int) ($f['idclienti'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $clave = (string) $id;
            $datos = $this->datosHuesped($f);
            $contactos = $this->contactos($f);
            $camposHuesped = $campos[$id] ?? [];
            $huella = Huellas::datos(['datos' => $datos, 'contactos' => $contactos, 'campos' => $camposHuesped]);
            $identidad = hash('sha256', (string) ($f['datainserimento'] ?? ''));
            /** @var stdClass|null $existente */
            $existente = $existentes->get($clave);

            if ($existente !== null && $existente->huella_identidad === $identidad) {
                $mapa[$id] = (int) $existente->id;
                if ($existente->huella_datos !== $huella || $existente->eliminado_en_principal_en !== null) {
                    DB::table('huespedes')->where('id', $existente->id)->update($this->cifrarDocumento($datos) + [
                        'huella_datos' => $huella, 'eliminado_en_principal_en' => null,
                        'ejecucion_id' => $this->ejecucion->id, 'actualizado_en' => $this->ahora,
                    ]);
                    $this->reemplazarContactos((int) $existente->id, $contactos, $camposHuesped);
                    $this->resultado->contar('huespedes_actualizados');
                }

                continue;
            }

            if ($existente !== null) {
                // La principal reutilizó el número de cliente: la ficha vieja queda como eliminada.
                DB::table('huespedes')->where('id', $existente->id)->update([
                    'clave_origen' => null, 'clave_origen_historica' => $clave,
                    'eliminado_en_principal_en' => $this->ahora, 'actualizado_en' => $this->ahora,
                ]);
            }

            $pendientes[$id] = [
                'fila' => $this->cifrarDocumento($datos) + $this->columnasOrigen($clave, $identidad, $huella) + ['propiedad_id' => $this->principal->propiedad_id],
                'contactos' => $contactos,
                'campos' => $camposHuesped,
            ];
            if (count($pendientes) >= self::LOTE) {
                $mapa += $this->insertarHuespedes($pendientes);
                $pendientes = [];
            }
        }
        $mapa += $this->insertarHuespedes($pendientes);

        $desaparecidos = $existentes->keys()->diff(array_keys($mapa))->all();
        foreach (array_chunk($desaparecidos, 1000) as $grupo) {
            DB::table('huespedes')->where('principal_id', $this->principal->id)->whereIn('clave_origen', $grupo)
                ->whereNull('eliminado_en_principal_en')->update(['eliminado_en_principal_en' => $this->ahora]);
        }

        return $mapa;
    }

    /**
     * @param  array<int, array{fila: array<string, mixed>, contactos: list<array<string, mixed>>, campos: array<string, string|null>}>  $pendientes
     * @return array<int, int> idclienti => huesped_id
     */
    private function insertarHuespedes(array $pendientes): array
    {
        if ($pendientes === []) {
            return [];
        }

        DB::table('huespedes')->insert(array_column($pendientes, 'fila'));
        $ids = DB::table('huespedes')->where('principal_id', $this->principal->id)->whereIn('clave_origen', array_map('strval', array_keys($pendientes)))
            ->pluck('id', 'clave_origen')->all();

        $mapa = [];
        $contactos = [];
        $campos = [];
        foreach ($pendientes as $idCliente => $pendiente) {
            $huespedId = (int) $ids[(string) $idCliente];
            $mapa[$idCliente] = $huespedId;
            foreach ($pendiente['contactos'] as $contacto) {
                $contactos[] = $contacto + ['huesped_id' => $huespedId];
            }
            foreach ($pendiente['campos'] as $campo => $valor) {
                $campos[] = ['huesped_id' => $huespedId, 'campo' => mb_substr($campo, 0, 100), 'valor' => $valor];
            }
        }
        foreach (array_chunk($contactos, self::LOTE) as $lote) {
            DB::table('contactos_huesped')->insert($lote);
        }
        foreach (array_chunk($campos, self::LOTE) as $lote) {
            DB::table('campos_huesped')->insert($lote);
        }
        $this->resultado->contar('huespedes_nuevos', count($pendientes));

        return $mapa;
    }

    /**
     * @param  list<array<string, mixed>>  $contactos
     * @param  array<string, string|null>  $campos
     */
    private function reemplazarContactos(int $huespedId, array $contactos, array $campos): void
    {
        DB::table('contactos_huesped')->where('huesped_id', $huespedId)->delete();
        DB::table('campos_huesped')->where('huesped_id', $huespedId)->delete();
        if ($contactos !== []) {
            DB::table('contactos_huesped')->insert(array_map(fn (array $c): array => $c + ['huesped_id' => $huespedId], $contactos));
        }
        foreach ($campos as $campo => $valor) {
            DB::table('campos_huesped')->insert(['huesped_id' => $huespedId, 'campo' => mb_substr($campo, 0, 100), 'valor' => $valor]);
        }
    }

    /**
     * @param  array<int|string, int>  $unidades
     * @param  array<int, int>  $huespedes
     */
    private function proyectarReservas(array $unidades, array $huespedes): void
    {
        $periodos = [];
        foreach ($this->filas('periodi') as [$anio, $f]) {
            $periodos[(int) $anio][(int) $f['idperiodi']] = [$this->conversor->fecha($f['datainizio'] ?? null), $this->conversor->fecha($f['datafine'] ?? null)];
        }
        $parentescos = [];
        foreach ($this->filas('parentele') as [, $f]) {
            $parentescos[(int) $f['idparentele']] = $this->conversor->texto($f['nome_parentela'] ?? null, 70);
        }
        $acompanantes = [];
        foreach ($this->filas('rclientiprenota') as [$anio, $f]) {
            $acompanantes[$anio.':'.(int) $f['idprenota']][] = [
                (int) $f['idclienti'], (int) ($f['num_ordine'] ?? 0), $parentescos[(int) ($f['parentela'] ?? 0)] ?? null,
            ];
        }

        $existentes = DB::table('reservas')->where('principal_id', $this->principal->id)->whereNotNull('clave_origen')
            ->get(['id', 'clave_origen', 'huella_identidad', 'huella_datos', 'estado', 'eliminada_en_principal_en', 'llegada', 'salida', 'unidad_id', 'total'])
            ->keyBy('clave_origen');
        $vistas = [];
        $firmas = [];
        $pendientes = [];

        foreach (['prenota' => false, 'prenotacanc' => true] as $tabla => $cancelada) {
            foreach ($this->filas($tabla) as [$anio, $f]) {
                $idPrenota = (int) ($f['idprenota'] ?? 0);
                if ($idPrenota <= 0 || $anio === null) {
                    continue;
                }
                $clave = $anio.':'.$idPrenota;
                $llegada = $periodos[$anio][(int) ($f['iddatainizio'] ?? 0)][0] ?? null;
                $salida = $periodos[$anio][(int) ($f['iddatafine'] ?? 0)][1] ?? null;
                if ($llegada === null || $salida === null || $salida <= $llegada) {
                    $this->resultado->incidencia('reservas_sin_fechas');

                    continue;
                }
                $unidadId = $unidades[trim((string) ($f['idappartamenti'] ?? ''))] ?? null;
                if ($unidadId === null) {
                    $this->resultado->incidencia('reservas_sin_unidad');

                    continue;
                }
                $idCliente = (int) ($f['idclienti'] ?? 0);
                // La misma estancia copiada en la tabla del año siguiente se proyecta una sola vez (C9).
                $firma = $unidadId.'|'.$llegada.'|'.$salida.'|'.$idCliente;
                if (isset($firmas[$firma])) {
                    $this->resultado->incidencia('reservas_en_dos_anios');

                    continue;
                }
                $firmas[$firma] = true;

                $datos = $this->datosReserva($f, $cancelada, $unidadId, $huespedes[$idCliente] ?? null, $llegada, $salida, $idPrenota, $anio);
                $grupo = $acompanantes[$clave] ?? [];
                $huella = Huellas::datos(['datos' => $datos, 'acompanantes' => $grupo]);
                $identidad = hash('sha256', (string) ($f['datainserimento'] ?? ''));
                /** @var stdClass|null $existente */
                $existente = $existentes->get($clave);
                $vistas[$clave] = true;

                if ($existente !== null && $existente->huella_identidad === $identidad) {
                    if ($existente->huella_datos !== $huella || $existente->eliminada_en_principal_en !== null) {
                        $this->actualizarReserva($existente, $datos, $huella, $grupo, $huespedes);
                    }

                    continue;
                }

                if ($existente !== null) {
                    // Número reutilizado: la reserva vieja (con sus datos propios) queda como eliminada (§6).
                    $this->eliminarReserva($existente, true);
                }
                $pendientes[$clave] = ['fila' => $datos + $this->columnasOrigen($clave, $identidad, $huella) + ['codigo_publico' => Huellas::codigoPublico()], 'acompanantes' => $grupo];
                if (count($pendientes) >= self::LOTE) {
                    $this->insertarReservas($pendientes, $huespedes);
                    $pendientes = [];
                }
            }
        }
        $this->insertarReservas($pendientes, $huespedes);

        foreach ($existentes as $clave => $existente) {
            if (! isset($vistas[(string) $clave]) && $existente->eliminada_en_principal_en === null) {
                $this->eliminarReserva($existente, false);
            }
        }
    }

    /**
     * @param  array<string, string|null>  $f
     * @return array<string, mixed>
     */
    private function datosReserva(array $f, bool $cancelada, int $unidadId, ?int $titularId, string $llegada, string $salida, int $idPrenota, int $anio): array
    {
        $c = $this->conversor;
        $total = $c->importe($f['tariffa_tot'] ?? null);
        $comentario = $c->texto($f['commento'] ?? null, 65000);
        $origen = $c->texto($f['origine'] ?? null, 100);
        $esBloqueo = ($comentario !== null && str_contains($comentario, '[ical]'))
            || ($total === 0 && $origen !== null && preg_match('/^ical\b/i', $origen) === 1);
        $grupo = null;
        if (preg_match('/\[grupo (GRP-[A-Z0-9-]+)\]/i', (string) $comentario, $m) || preg_match('/^Grupo (GRP-[A-Z0-9-]+)/i', (string) $origen, $m)) {
            $grupo = strtoupper($m[1]);
        }
        $tarifa = $f['tariffa'] ?? null;
        if ($tarifa !== null && str_contains($tarifa, '#@&')) {
            $tarifa = strstr($tarifa, '#@&', true);
        }

        return [
            'propiedad_id' => $this->principal->propiedad_id,
            'unidad_id' => $unidadId,
            'titular_id' => $titularId,
            'tipo' => $esBloqueo ? TipoReserva::Bloqueo->value : TipoReserva::Reserva->value,
            'estado' => $cancelada ? EstadoReserva::Cancelada->value
                : (strtoupper(substr((string) ($f['conferma'] ?? ''), 0, 1)) === 'S' ? EstadoReserva::Confirmada->value : EstadoReserva::SinConfirmar->value),
            'numero' => $idPrenota.'/'.$anio,
            'llegada' => $llegada,
            'salida' => $salida,
            'personas' => max(0, (int) ($f['num_persone'] ?? 0)),
            'origen_texto' => $origen,
            'grupo_codigo' => $grupo,
            'tarifa_nombre' => $c->texto($tarifa === false ? null : $tarifa, 100),
            'moneda' => $this->principal->propiedad->moneda,
            'total' => $total,
            'pagado' => $c->importe($f['pagato'] ?? null),
            'deposito' => $c->importe($f['caparra'] ?? null),
            'descuento' => $c->importe($f['sconto'] ?? null),
            'comision' => $c->importe($f['commissioni'] ?? null),
            'impuestos_porcentaje' => $c->porcentaje($f['tasseperc'] ?? null),
            'entrada_registrada_en' => $c->fechaHora($f['checkin'] ?? null),
            'salida_registrada_en' => $c->fechaHora($f['checkout'] ?? null),
            'comentario' => $comentario,
            'creada_en_origen' => $c->fechaHora($f['datainserimento'] ?? null),
            'modificada_en_origen' => $c->fechaHora($f['data_modifica'] ?? null),
        ];
    }

    /**
     * @param  array<int|string, array{fila: array<string, mixed>, acompanantes: list<array{0: int, 1: int, 2: string|null}>}>  $pendientes
     * @param  array<int, int>  $huespedes
     */
    private function insertarReservas(array $pendientes, array $huespedes): void
    {
        if ($pendientes === []) {
            return;
        }

        DB::table('reservas')->insert(array_column($pendientes, 'fila'));
        $ids = DB::table('reservas')->where('principal_id', $this->principal->id)->whereIn('clave_origen', array_map('strval', array_keys($pendientes)))
            ->pluck('id', 'clave_origen')->all();

        $noches = [];
        foreach ($pendientes as $clave => $pendiente) {
            $id = (int) $ids[(string) $clave];
            $noches = array_merge($noches, NochesProyectadas::de($id, $pendiente['fila']));
            $this->guardarAcompanantes($id, $pendiente['acompanantes'], $huespedes);
            $this->evento($id, TipoEventoReserva::Creada, ['despues' => $this->resumen($pendiente['fila'])]);
        }
        foreach (array_chunk($noches, 1000) as $lote) {
            DB::table('reservas_noches')->insert($lote);
        }
        $this->resultado->contar('reservas_nuevas', count($pendientes));
    }

    /**
     * @param  array<string, mixed>  $datos
     * @param  list<array{0: int, 1: int, 2: string|null}>  $acompanantes
     * @param  array<int, int>  $huespedes
     */
    private function actualizarReserva(stdClass $existente, array $datos, string $huella, array $acompanantes, array $huespedes): void
    {
        DB::table('reservas')->where('id', $existente->id)->update($datos + [
            'huella_datos' => $huella, 'eliminada_en_principal_en' => null,
            'ejecucion_id' => $this->ejecucion->id, 'actualizado_en' => $this->ahora,
        ]);
        DB::table('reservas_noches')->where('reserva_id', $existente->id)->delete();
        $noches = NochesProyectadas::de((int) $existente->id, $datos);
        foreach (array_chunk($noches, 1000) as $lote) {
            DB::table('reservas_noches')->insert($lote);
        }
        DB::table('reservas_huespedes')->where('reserva_id', $existente->id)->delete();
        $this->guardarAcompanantes((int) $existente->id, $acompanantes, $huespedes);

        $antes = [
            'llegada' => (string) $existente->llegada, 'salida' => (string) $existente->salida,
            'unidad_id' => (int) $existente->unidad_id, 'estado' => (string) $existente->estado, 'total' => (int) $existente->total,
        ];
        $tipo = match (true) {
            $existente->eliminada_en_principal_en !== null => TipoEventoReserva::Reactivada,
            $datos['estado'] === EstadoReserva::Cancelada->value && $existente->estado !== EstadoReserva::Cancelada->value => TipoEventoReserva::Cancelada,
            default => TipoEventoReserva::Modificada,
        };
        $this->evento((int) $existente->id, $tipo, ['antes' => $antes, 'despues' => $this->resumen($datos)]);
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
     * @param  list<array{0: int, 1: int, 2: string|null}>  $acompanantes
     * @param  array<int, int>  $huespedes
     */
    private function guardarAcompanantes(int $reservaId, array $acompanantes, array $huespedes): void
    {
        $filas = [];
        foreach ($acompanantes as [$idCliente, $orden, $parentesco]) {
            $huespedId = $huespedes[$idCliente] ?? null;
            if ($huespedId !== null) {
                $filas[$huespedId] = ['reserva_id' => $reservaId, 'huesped_id' => $huespedId, 'orden' => max(0, $orden), 'parentesco_texto' => $parentesco];
            }
        }
        if ($filas !== []) {
            DB::table('reservas_huespedes')->insert(array_values($filas));
        }
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function evento(int $reservaId, TipoEventoReserva $tipo, array $datos): void
    {
        // La primera carga de una principal no genera eventos: no son cambios (comunicacion-eventos.md).
        if (! $this->generarEventos) {
            return;
        }

        $this->eventos[] = [
            'propiedad_id' => $this->principal->propiedad_id,
            'reserva_id' => $reservaId,
            'tipo' => $tipo->value,
            'datos' => json_encode($datos, JSON_UNESCAPED_UNICODE),
            'ocurrido_en' => $this->ahora,
        ];
    }

    private function guardarEventos(): void
    {
        foreach (array_chunk($this->eventos, self::LOTE) as $lote) {
            DB::table('eventos_reserva')->insert($lote);
        }
        $this->resultado->eventos = count($this->eventos);
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private function resumen(array $datos): array
    {
        return [
            'llegada' => $datos['llegada'], 'salida' => $datos['salida'], 'unidad_id' => $datos['unidad_id'],
            'estado' => $datos['estado'], 'total' => $datos['total'],
        ];
    }

    /**
     * @param  array<string, string|null>  $f
     * @return array<string, mixed>
     */
    private function datosHuesped(array $f): array
    {
        $c = $this->conversor;
        $sexo = strtolower(trim((string) ($f['sesso'] ?? '')));

        return [
            'nombre' => $c->texto($f['nome'] ?? null, 100),
            'apellido1' => $c->texto($f['cognome'] ?? null, 100),
            'apellido2' => $c->texto($f['cognome2'] ?? null, 100),
            'apodo' => $c->texto($f['soprannome'] ?? null, 100),
            'tratamiento' => $c->texto($f['titolo'] ?? null, 30),
            'sexo' => match ($sexo) {
                'm' => 'hombre',
                'f' => 'mujer',
                default => null,
            },
            // Solo si es un idioma de los nuestros: con otro texto, los mensajes a ese huésped fallarían.
            'idioma' => in_array($f['lingua'] ?? null, (array) config('alojaflow.idiomas'), true) ? $f['lingua'] : null,
            'fecha_nacimiento' => $c->fecha($f['datanascita'] ?? null),
            'lugar_nacimiento' => $c->texto(implode(', ', array_filter([$f['cittanascita'] ?? null, $f['regionenascita'] ?? null])), 255),
            'pais_nacimiento_texto' => $c->texto($f['nazionenascita'] ?? null, 100),
            'nacionalidad_texto' => $c->texto($f['nazionalita'] ?? null, 100),
            'documento_tipo_texto' => $c->texto($f['tipodoc'] ?? null, 100),
            'documento_numero' => $c->texto($f['documento'] ?? null, 100),
            'documento_vencimiento' => $c->fecha($f['scadenzadoc'] ?? null),
            'documento_expedicion' => $c->texto(implode(', ', array_filter([$f['cittadoc'] ?? null, $f['regionedoc'] ?? null])), 255),
            'documento_pais_texto' => $c->texto($f['nazionedoc'] ?? null, 100),
            'domicilio_calle' => $c->texto($f['via'] ?? null, 150),
            'domicilio_numero' => $c->texto($f['numcivico'] ?? null, 30),
            'domicilio_ciudad' => $c->texto($f['citta'] ?? null, 100),
            'domicilio_region' => $c->texto($f['regione'] ?? null, 100),
            'domicilio_codigo_postal' => $c->texto($f['cap'] ?? null, 30),
            'domicilio_pais_texto' => $c->texto($f['nazione'] ?? null, 100),
            'id_fiscal_personal' => $c->texto($f['cod_fiscale'] ?? null, 50),
            'id_fiscal_empresa' => $c->texto($f['partita_iva'] ?? null, 50),
            'notas' => $c->texto($f['commento'] ?? null, 65000),
            'creado_en_origen' => $c->fechaHora($f['datainserimento'] ?? null),
        ];
    }

    /**
     * El número de documento se guarda cifrado, con un HMAC para buscarlo (D-022).
     *
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private function cifrarDocumento(array $datos): array
    {
        $numero = $datos['documento_numero'];
        $datos['documento_numero'] = $numero === null ? null : Crypt::encryptString((string) $numero);
        $datos['documento_numero_hmac'] = $numero === null ? null
            : Huellas::hmacDocumento((string) $numero);

        return $datos;
    }

    /**
     * @param  array<string, string|null>  $f
     * @return list<array<string, mixed>>
     */
    private function contactos(array $f): array
    {
        $contactos = [];
        $orden = 0;
        foreach (['telefono' => 'telefono', 'telefono2' => 'telefono', 'telefono3' => 'telefono', 'email' => 'correo', 'email2' => 'correo', 'email3' => 'correo', 'fax' => 'fax'] as $columna => $tipo) {
            $valor = $this->conversor->texto($f[$columna] ?? null, 255);
            if ($valor === null) {
                continue;
            }
            $contactos[] = ['tipo' => $tipo, 'valor' => $valor, 'valor_normalizado' => Contactos::normalizar($tipo, $valor), 'orden' => $orden++];
        }

        return $contactos;
    }

    /**
     * @return array<string, mixed>
     */
    private function columnasOrigen(string $clave, string $identidad, string $huella): array
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
     * La caja de la principal, uno por uno (D-078). Cada cobro está en `soldi`, con el número de reserva en su motivo. La tabla `costi` es el
     * libro de la caja: lo que viene de una reserva ('p') ya está en `soldi` y solo dice en qué caja entró, para no contarlo dos veces; lo
     * demás son gastos e ingresos sueltos. Su concepto de una reserva lleva el apellido del huésped: se lee el número y no se guarda.
     */
    private function proyectarCaja(): void
    {
        $proyeccion = new ProyeccionCaja($this->principal, $this->ahora);
        $moneda = $this->principal->propiedad->moneda;
        $c = $this->conversor;

        $cajas = [];
        $claves = [];
        foreach ($this->filas('casse') as [, $f]) {
            $nombre = $c->texto($f['nome_cassa'] ?? null, 100);
            if ($nombre !== null && ! isset($claves[mb_strtolower($nombre)])) {
                $clave = 'casse:'.(int) ($f['idcasse'] ?? 0);
                $claves[mb_strtolower($nombre)] = $clave;
                $cajas[$clave] = ['nombre' => $nombre, 'activa' => true];
            }
        }
        $idsCaja = $proyeccion->cajas($cajas);
        $cajaDe = fn (array $f): ?int => $idsCaja[$claves[mb_strtolower((string) $c->texto($f['nome_cassa'] ?? null, 100))] ?? ''] ?? null;
        $fechaDe = fn (array $f): ?string => $c->fecha($f['data_transazione'] ?? null) ?? $c->fecha($f['data_inserimento'] ?? $f['datainserimento'] ?? null);

        $reservas = DB::table('reservas')->where('principal_id', $this->principal->id)->whereNotNull('clave_origen')->pluck('id', 'clave_origen');
        $pagos = [];
        $sinCaja = [];
        foreach ($this->filas('soldi') as [$anio, $f]) {
            $partes = explode(';', (string) ($f['motivazione'] ?? ''));
            $reservaId = $reservas[$anio.':'.(int) explode(',', $partes[3] ?? '')[0]] ?? null;
            $importe = $c->importe($f['saldo_prenota'] ?? null);
            $fecha = $fechaDe($f);
            if ($reservaId === null || $importe === 0 || $fecha === null) {
                continue;
            }
            $clave = 'soldi:'.$anio.':'.(int) ($f['idsoldi'] ?? 0);
            $metodo = ProyeccionCaja::metodo($f['metodo_pagamento'] ?? null);
            $pagos[$clave] = [
                'reserva_id' => (int) $reservaId,
                'caja_id' => null,
                'fecha' => $fecha,
                'metodo' => $metodo->value,
                'importe' => $importe,
                'moneda' => $moneda,
                'referencia' => $metodo === MetodoPago::Otro ? $c->texto($f['metodo_pagamento'] ?? null, 100) : null,
            ];
            $sinCaja[$reservaId.'|'.$importe.'|'.$fecha][] = $clave;
        }

        $movimientos = [];
        foreach ($this->filas('costi') as [$anio, $f]) {
            $importe = $c->importe($f['val_costo'] ?? null);
            $fecha = $fechaDe($f);
            if ($importe === 0 || $fecha === null) {
                continue;
            }
            if (($f['provenienza_costo'] ?? '') === 'p') {
                if (preg_match('/^\S+\s+(\d+)\s/u', (string) ($f['nome_costo'] ?? ''), $m) === 1) {
                    $casar = ($reservas[$anio.':'.$m[1]] ?? 0).'|'.$importe.'|'.$fecha;
                    // Si no casa, el cobro se queda sin caja: puede haberse corregido en la principal después.
                    $clave = ($sinCaja[$casar] ?? []) === [] ? null : array_shift($sinCaja[$casar]);
                    if ($clave !== null) {
                        $pagos[$clave]['caja_id'] = $cajaDe($f);
                    }
                }

                continue;
            }
            $movimientos['costi:'.$anio.':'.(int) ($f['idcosti'] ?? 0)] = [
                'caja_id' => $cajaDe($f),
                'fecha' => $fecha,
                'tipo' => ($f['tipo_costo'] ?? '') === 's' ? TipoMovimiento::Gasto->value : TipoMovimiento::Ingreso->value,
                'categoria' => null,
                'concepto' => $c->texto($f['nome_costo'] ?? null, 255) ?? '—',
                'metodo' => ProyeccionCaja::metodo($f['metodo_pagamento'] ?? null)->value,
                'importe' => abs($importe),
                'moneda' => $moneda,
                'referencia' => null,
            ];
        }
        $proyeccion->pagos($pagos);
        $proyeccion->movimientos($movimientos);
        $this->resultado->cuentas['pagos'] = count($pagos);
        $this->resultado->cuentas['movimientos'] = count($movimientos);
    }

    /**
     * Filas del espejo de una tabla en esta ejecución, en el orden de la copia.
     *
     * @return Generator<int, array{0: int|null, 1: array<string, string|null>}>
     */
    private function filas(string $tabla): Generator
    {
        $consulta = DB::table('hd_filas')->select(['id', 'anio', 'datos'])
            ->where('ejecucion_id', $this->ejecucion->id)->where('tabla', $tabla);

        foreach ($consulta->lazyById(1000) as $fila) {
            /** @var array<string, string|null> $datos */
            $datos = json_decode(Crypt::decryptString((string) $fila->datos), true);
            yield [$fila->anio === null ? null : (int) $fila->anio, $datos];
        }
    }
}
