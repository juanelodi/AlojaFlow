<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Sincronizacion\HotelDruid\Proyeccion;

use App\Enums\EstadoReserva;
use App\Enums\TipoEventoReserva;
use App\Enums\TipoReserva;
use App\Sincronizacion\NochesProyectadas;
use App\Support\Huellas;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Las reservas de la principal, año por año: las vigentes, las canceladas y lo que tiene cerrado, con sus noches,
 * sus acompañantes y los eventos de lo que cambió (subordinacion.md C5, C6 y C9; modelo-datos.md §6).
 */
final class ReservasPrincipal
{
    private const LOTE = 1000;

    /** Lo que, si cambia, obliga a rehacer las noches de una reserva. */
    private const DECIDEN_LAS_NOCHES = ['unidad_id', 'estado', 'llegada', 'salida', 'total'];

    /** En `regole`, la marca de que la regla es un cierre de la unidad (D-083). */
    private const MARCA_DE_CIERRE = 'x';

    /** @var Collection<array-key, stdClass> las que ya estaban, por su clave de origen */
    private Collection $guardadas;

    /** @var array<string, int> clave de origen => reserva_id, también de las que se dejaron de lado por repetidas */
    private array $ids = [];

    /** @var array<string, true> claves que vienen en esta copia */
    private array $vistas = [];

    /** @var array<string, array{0: int, 1: string}> estancia => [año, clave], para reconocer la que está en dos años */
    private array $estancias = [];

    /** @var list<array<string, mixed>> */
    private array $noches = [];

    /** @var list<array<string, mixed>> */
    private array $eventos = [];

    /**
     * @param  array<string, int>  $unidades  nombre de la unidad en su forma comparable => unidad_id
     * @param  array<string, int>  $huespedes  `idclienti` => huesped_id
     */
    public function __construct(
        private readonly Contexto $contexto,
        private readonly array $unidades,
        private readonly array $huespedes,
        private readonly bool $anunciar,
    ) {
    }

    /**
     * @return array<string, int> clave de origen (`AAAA:idprenota`) => reserva_id
     */
    public function proyectar(): array
    {
        $this->guardadas = DB::table('reservas')->where('principal_id', $this->contexto->principal->id)->whereNotNull('clave_origen')
            ->get(['id', 'clave_origen', 'huella_identidad', 'huella_datos', 'eliminada_en_principal_en'])->keyBy('clave_origen');
        $parentescos = $this->parentescos();

        foreach ($this->contexto->espejo->anios() as $anio) {
            $periodos = CalendarioPeriodos::delAnio($this->contexto->espejo, $anio, $this->contexto->conversor);
            $acompanantes = $this->acompanantes($anio, $parentescos);
            foreach (['prenota' => false, 'prenotacanc' => true] as $tabla => $cancelada) {
                foreach ($this->contexto->espejo->filas($tabla, $anio) as $fila) {
                    $this->reserva($anio, $fila, $cancelada, $periodos, $acompanantes[(string) ($fila['idprenota'] ?? '')] ?? []);
                }
            }
            foreach ($this->contexto->espejo->filas('regole', $anio) as $fila) {
                $this->cierre($anio, $fila, $periodos);
            }
        }

        $this->retirarAusentes();
        $this->guardarNoches();
        foreach (array_chunk($this->eventos, self::LOTE) as $lote) {
            DB::table('eventos_reserva')->insert($lote);
        }
        $this->contexto->resultado->eventos = count($this->eventos);

        return $this->ids;
    }

    /**
     * @param  array<string, string|null>  $fila
     * @param  list<array{huesped_id: int, orden: int, parentesco_texto: string|null}>  $acompanantes
     */
    private function reserva(int $anio, array $fila, bool $cancelada, CalendarioPeriodos $periodos, array $acompanantes): void
    {
        $conversor = $this->contexto->conversor;
        $numero = (string) ($fila['idprenota'] ?? '');
        $estancia = $periodos->estancia($fila['iddatainizio'] ?? null, $fila['iddatafine'] ?? null);
        if (! ctype_digit($numero) || $estancia === null) {
            $this->contexto->resultado->incidencia('reservas_sin_fechas');

            return;
        }
        $unidadId = $this->unidades[Contexto::comparable((string) ($fila['idappartamenti'] ?? ''))] ?? null;
        if ($unidadId === null) {
            $this->contexto->resultado->incidencia('reservas_sin_unidad');

            return;
        }
        $clave = $anio.':'.$numero;
        $titularId = $this->huespedes[(string) ($fila['idclienti'] ?? '')] ?? null;
        if ($this->yaVinoEnOtroAnio($anio, $clave, $fila, [$unidadId, $estancia[0], $estancia[1], $titularId, $cancelada])) {
            return;
        }

        $comentario = $conversor->texto($fila['commento'] ?? null, 65000);
        $origen = $conversor->texto($fila['origine'] ?? null, 100);
        $total = $conversor->importe($fila['tariffa_tot'] ?? null);
        $datos = [
            'propiedad_id' => $this->contexto->propiedadId(),
            'unidad_id' => $unidadId,
            'titular_id' => $titularId,
            // Los bloqueos del plugin de calendarios de una principal 1.2.x: su marca en el comentario y sin importe.
            'tipo' => ($total === 0 && $comentario !== null && stripos($comentario, '[ical]') !== false ? TipoReserva::Bloqueo : TipoReserva::Reserva)->value,
            'estado' => match (true) {
                $cancelada => EstadoReserva::Cancelada->value,
                str_starts_with(strtoupper(trim((string) ($fila['conferma'] ?? ''))), 'S') => EstadoReserva::Confirmada->value,
                default => EstadoReserva::SinConfirmar->value,
            },
            'numero' => $numero.'/'.$anio,
            'llegada' => $estancia[0],
            'salida' => $estancia[1],
            'personas' => $this->pequeno($fila['num_persone'] ?? null),
            'origen_texto' => $origen,
            'grupo_codigo' => $this->grupo($comentario, $origen),
            'tarifa_nombre' => $this->tarifa($fila['tariffa'] ?? null),
            'moneda' => $this->contexto->moneda(),
            'total' => $total,
            'pagado' => $conversor->importe($fila['pagato'] ?? null),
            'deposito' => $conversor->importe($fila['caparra'] ?? null),
            'descuento' => $conversor->importe($fila['sconto'] ?? null),
            'comision' => $conversor->importe($fila['commissioni'] ?? null),
            'impuestos_porcentaje' => $conversor->porcentaje($fila['tasseperc'] ?? null),
            'entrada_registrada_en' => $conversor->fechaHora($fila['checkin'] ?? null),
            'salida_registrada_en' => $conversor->fechaHora($fila['checkout'] ?? null),
            'comentario' => $comentario,
            'creada_en_origen' => $conversor->fechaHora($fila['datainserimento'] ?? null),
            'modificada_en_origen' => $conversor->fechaHora($fila['data_modifica'] ?? null),
        ];

        // Lo que hace que sea «la misma» es su fecha de alta en la principal (D-028).
        $this->aplicar($clave, hash('sha256', (string) ($fila['datainserimento'] ?? '')), $datos, $acompanantes);
    }

    /**
     * Una estancia que cruza el fin de año está en las tablas de los dos años (C9) y se proyecta una vez, con la clave
     * del año en que empieza. La del año siguiente apunta a la anterior con `id_anni_prec`; si no lo trae, se
     * reconoce por ser la misma unidad, las mismas fechas y el mismo titular.
     *
     * @param  array<string, string|null>  $fila
     * @param  array{0: int, 1: string, 2: string, 3: int|null, 4: bool}  $estancia
     */
    private function yaVinoEnOtroAnio(int $anio, string $clave, array $fila, array $estancia): bool
    {
        $anterior = (string) ($fila['id_anni_prec'] ?? '');
        $original = ctype_digit($anterior) && (int) $anterior > 0 ? ($anio - 1).':'.(int) $anterior : null;
        $huella = implode('|', array_map(fn (int|string|bool|null $parte): string => (string) (is_bool($parte) ? (int) $parte : $parte), $estancia));
        if ($original === null || ! isset($this->ids[$original])) {
            $vista = $this->estancias[$huella] ?? null;
            $original = $vista !== null && $vista[0] < $anio ? $vista[1] : null;
        }
        if ($original === null || ! isset($this->ids[$original])) {
            $this->estancias[$huella] ??= [$anio, $clave];

            return false;
        }

        // Los pagos de su año la siguen encontrando por esta clave.
        $this->ids[$clave] = $this->ids[$original];
        $this->contexto->resultado->contar('reservas_en_dos_anios');

        return true;
    }

    /**
     * Lo que la principal tiene cerrado en una unidad se ve aquí como un bloqueo (D-083).
     *
     * @param  array<string, string|null>  $fila
     */
    private function cierre(int $anio, array $fila, CalendarioPeriodos $periodos): void
    {
        if (strtolower(trim((string) ($fila['motivazione2'] ?? ''))) !== self::MARCA_DE_CIERRE) {
            return;
        }
        $conversor = $this->contexto->conversor;
        $numero = (string) ($fila['idregole'] ?? '');
        $estancia = $periodos->estancia($fila['iddatainizio'] ?? null, $fila['iddatafine'] ?? null);
        $unidadId = $this->unidades[Contexto::comparable((string) ($fila['app_agenzia'] ?? ''))] ?? null;
        if (! ctype_digit($numero) || $estancia === null || $unidadId === null) {
            $this->contexto->resultado->incidencia('cierres_descartados');

            return;
        }

        $clave = 'cierre:'.$anio.':'.$numero;
        $this->aplicar($clave, hash('sha256', $clave), [
            'propiedad_id' => $this->contexto->propiedadId(),
            'unidad_id' => $unidadId,
            'titular_id' => null,
            'tipo' => TipoReserva::Bloqueo->value,
            'estado' => EstadoReserva::Confirmada->value,
            'numero' => mb_substr('C'.$numero.'/'.$anio, 0, 20),
            'llegada' => $estancia[0],
            'salida' => $estancia[1],
            'personas' => 0,
            'origen_texto' => $conversor->texto($fila['motivazione'] ?? null, 100),
            'grupo_codigo' => null,
            'tarifa_nombre' => null,
            'moneda' => $this->contexto->moneda(),
            'total' => 0,
            'pagado' => 0,
            'deposito' => 0,
            'descuento' => 0,
            'comision' => 0,
            'impuestos_porcentaje' => null,
            'entrada_registrada_en' => null,
            'salida_registrada_en' => null,
            'comentario' => null,
            'creada_en_origen' => null,
            'modificada_en_origen' => null,
        ], []);
    }

    /**
     * Inserta la reserva, la pone al día o la deja como está, según lo que ya hubiera con su clave (modelo-datos.md §6).
     *
     * @param  array<string, mixed>  $datos
     * @param  list<array{huesped_id: int, orden: int, parentesco_texto: string|null}>  $acompanantes
     */
    private function aplicar(string $clave, string $identidad, array $datos, array $acompanantes): void
    {
        if (isset($this->vistas[$clave])) {
            return;
        }
        $this->vistas[$clave] = true;
        $huella = Huellas::datos(['datos' => $datos, 'acompanantes' => $acompanantes]);
        $guardada = $this->guardadas->get($clave);

        // Otra fecha de alta con el mismo número: la principal lo reutilizó, y es otra reserva. La anterior conserva lo
        // que se le hubiera anotado aquí. Con huellas de otra generación no se puede saber, y se toma por la misma.
        if ($guardada !== null && $guardada->huella_identidad !== $identidad && $this->contexto->mismaGeneracion) {
            $this->retirar($guardada, true);
            $guardada = null;
        }

        if ($guardada === null) {
            $id = (int) DB::table('reservas')->insertGetId($datos + $this->contexto->columnasOrigen($clave, $identidad, $huella) + ['codigo_publico' => Huellas::codigoPublico()]);
            $this->ids[$clave] = $id;
            array_push($this->noches, ...NochesProyectadas::de($id, $datos));
            $this->guardarAcompanantes($id, $acompanantes);
            $this->evento($id, TipoEventoReserva::Creada, ['despues' => $this->resumen($datos)]);
            $this->contexto->resultado->contar('reservas_nuevas');
        } else {
            $this->ids[$clave] = (int) $guardada->id;
            if ($guardada->huella_datos !== $huella || $guardada->huella_identidad !== $identidad || $guardada->eliminada_en_principal_en !== null) {
                $this->ponerAlDia((int) $guardada->id, $identidad, $huella, $datos, $acompanantes);
            }
        }
        if (count($this->noches) >= self::LOTE) {
            $this->guardarNoches();
        }
    }

    /**
     * Escribe solo lo que cambió, y solo entonces lo anuncia: si lo único distinto es la huella (la guardó otra
     * generación del proyector), la reserva no se toca.
     *
     * @param  array<string, mixed>  $datos
     * @param  list<array{huesped_id: int, orden: int, parentesco_texto: string|null}>  $acompanantes
     */
    private function ponerAlDia(int $id, string $identidad, string $huella, array $datos, array $acompanantes): void
    {
        $antes = DB::table('reservas')->where('id', $id)->first();
        if ($antes === null) {
            return;
        }
        $distintas = Contexto::diferencias($antes, $datos);
        $otrosAcompanantes = $this->acompanantesGuardados($id) !== $acompanantes;
        $volvio = $antes->eliminada_en_principal_en !== null;
        $huellas = ['huella_identidad' => $identidad, 'huella_datos' => $huella, 'ejecucion_id' => $this->contexto->ejecucion->id];
        if ($distintas === [] && ! $otrosAcompanantes && ! $volvio) {
            DB::table('reservas')->where('id', $id)->update($huellas);

            return;
        }

        DB::table('reservas')->where('id', $id)->update($datos + $huellas + ['eliminada_en_principal_en' => null, 'actualizado_en' => $this->contexto->ahora]);
        if ($volvio || array_intersect($distintas, self::DECIDEN_LAS_NOCHES) !== []) {
            DB::table('reservas_noches')->where('reserva_id', $id)->delete();
            array_push($this->noches, ...NochesProyectadas::de($id, $datos));
        }
        if ($otrosAcompanantes) {
            DB::table('reservas_huespedes')->where('reserva_id', $id)->delete();
            $this->guardarAcompanantes($id, $acompanantes);
        }

        $tipo = match (true) {
            $volvio => TipoEventoReserva::Reactivada,
            $datos['estado'] === EstadoReserva::Cancelada->value && $antes->estado !== EstadoReserva::Cancelada->value => TipoEventoReserva::Cancelada,
            default => TipoEventoReserva::Modificada,
        };
        $this->evento($id, $tipo, [
            'antes' => ['llegada' => (string) $antes->llegada, 'salida' => (string) $antes->salida, 'unidad_id' => (int) $antes->unidad_id, 'estado' => (string) $antes->estado, 'total' => (int) $antes->total],
            'despues' => $this->resumen($datos),
        ]);
        $this->contexto->resultado->contar('reservas_actualizadas');
    }

    /**
     * Lo que ya no viene en la copia queda «eliminada en la principal». Nunca se borra: puede tener datos propios.
     */
    private function retirarAusentes(): void
    {
        foreach ($this->guardadas as $clave => $guardada) {
            if (! isset($this->vistas[(string) $clave]) && $guardada->eliminada_en_principal_en === null) {
                $this->retirar($guardada, false);
            }
        }
    }

    private function retirar(stdClass $guardada, bool $reutilizada): void
    {
        $cambios = ['eliminada_en_principal_en' => $guardada->eliminada_en_principal_en ?? $this->contexto->ahora, 'actualizado_en' => $this->contexto->ahora];
        if ($reutilizada) {
            // La clave queda libre para la reserva nueva; la vieja la recuerda en su histórico.
            $cambios += ['clave_origen' => null, 'clave_origen_historica' => $guardada->clave_origen];
        }
        DB::table('reservas')->where('id', $guardada->id)->update($cambios);
        DB::table('reservas_noches')->where('reserva_id', $guardada->id)->delete();
        if ($guardada->eliminada_en_principal_en === null) {
            $this->evento((int) $guardada->id, TipoEventoReserva::Eliminada, ['reutilizada' => $reutilizada]);
            $this->contexto->resultado->contar('reservas_eliminadas');
        }
    }

    /**
     * Quién va en cada reserva de un año (`rclientiprenotaAAAA`), por número de reserva.
     *
     * @param  array<string, string>  $parentescos
     * @return array<string, list<array{huesped_id: int, orden: int, parentesco_texto: string|null}>>
     */
    private function acompanantes(int $anio, array $parentescos): array
    {
        $porReserva = [];
        foreach ($this->contexto->espejo->filas('rclientiprenota', $anio) as $fila) {
            $reserva = (string) ($fila['idprenota'] ?? '');
            $huespedId = $this->huespedes[(string) ($fila['idclienti'] ?? '')] ?? null;
            // La misma persona no va dos veces en una reserva.
            if ($huespedId === null || isset($porReserva[$reserva][$huespedId])) {
                continue;
            }
            $porReserva[$reserva][$huespedId] = [
                'huesped_id' => $huespedId,
                'orden' => $this->pequeno($fila['num_ordine'] ?? null),
                'parentesco_texto' => $parentescos[(string) ($fila['parentela'] ?? '')] ?? null,
            ];
        }

        return array_map(array_values(...), $porReserva);
    }

    /**
     * El catálogo de parentescos de la principal: identificador => nombre.
     *
     * @return array<string, string>
     */
    private function parentescos(): array
    {
        $parentescos = [];
        foreach ($this->contexto->espejo->filas('parentele') as $fila) {
            $nombre = $this->contexto->conversor->texto($fila['nome_parentela'] ?? null, 70);
            if ($nombre !== null) {
                $parentescos[(string) ($fila['idparentele'] ?? '')] = $nombre;
            }
        }

        return $parentescos;
    }

    /**
     * @return list<array{huesped_id: int, orden: int, parentesco_texto: string|null}>
     */
    private function acompanantesGuardados(int $reservaId): array
    {
        return DB::table('reservas_huespedes')->where('reserva_id', $reservaId)->orderBy('id')->get(['huesped_id', 'orden', 'parentesco_texto'])
            ->map(fn (object $a): array => ['huesped_id' => (int) $a->huesped_id, 'orden' => (int) $a->orden, 'parentesco_texto' => $a->parentesco_texto === null ? null : (string) $a->parentesco_texto])
            ->values()->all();
    }

    /**
     * @param  list<array{huesped_id: int, orden: int, parentesco_texto: string|null}>  $acompanantes
     */
    private function guardarAcompanantes(int $reservaId, array $acompanantes): void
    {
        if ($acompanantes !== []) {
            DB::table('reservas_huespedes')->insert(array_map(fn (array $acompanante): array => $acompanante + ['reserva_id' => $reservaId], $acompanantes));
        }
    }

    private function guardarNoches(): void
    {
        foreach (array_chunk($this->noches, self::LOTE) as $lote) {
            DB::table('reservas_noches')->insert($lote);
        }
        $this->noches = [];
    }

    /**
     * El código de grupo de una principal 1.2.x: lo escribe en el comentario (`[grupo GRP-7]`) y en el origen («Grupo GRP-7»).
     */
    private function grupo(?string $comentario, ?string $origen): ?string
    {
        if (preg_match('/\[grupo\s+(GRP-\d+)\]/i', (string) $comentario, $partes) === 1 || preg_match('/^grupo\s+(GRP-\d+)$/i', (string) $origen, $partes) === 1) {
            return mb_substr(strtoupper($partes[1]), 0, 30);
        }

        return null;
    }

    /**
     * La principal guarda la tarifa de una reserva como «nombre#@&importe»: aquí solo se enseña el nombre.
     */
    private function tarifa(?string $valor): ?string
    {
        return $this->contexto->conversor->texto(explode('#@&', (string) $valor, 2)[0], 100);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function evento(int $reservaId, TipoEventoReserva $tipo, array $datos): void
    {
        if ($this->anunciar) {
            $this->eventos[] = [
                'propiedad_id' => $this->contexto->propiedadId(), 'reserva_id' => $reservaId, 'tipo' => $tipo->value,
                'datos' => json_encode($datos, JSON_UNESCAPED_UNICODE), 'ocurrido_en' => $this->contexto->ahora,
            ];
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
     * Para las columnas pequeñas: un número fuera de rango detendría toda la copia.
     */
    private function pequeno(?string $valor): int
    {
        return is_numeric($valor) ? min(65535, max(0, (int) $valor)) : 0;
    }
}
