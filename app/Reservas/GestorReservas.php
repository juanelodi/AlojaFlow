<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Reservas;

use App\Dinero\PagoRecibido;
use App\Enums\EstadoReserva;
use App\Enums\MetodoPago;
use App\Enums\OrigenFila;
use App\Enums\TipoEventoReserva;
use App\Enums\TipoReserva;
use App\Limpieza\GestorLimpieza;
use App\Models\Canal;
use App\Models\CargoReserva;
use App\Models\Empresa;
use App\Models\EventoReserva;
use App\Models\Huesped;
use App\Models\Pago;
use App\Models\Propiedad;
use App\Models\RegistroAuditoria;
use App\Models\Reserva;
use App\Models\Unidad;
use App\Support\Dinero;
use App\Support\Huellas;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use LogicException;
use Throwable;

/**
 * Todo lo que cambia reservas en una propiedad independiente (D-035). Las noches llevan
 * exclusiva = 1: es la base de datos quien impide la doble reserva, aunque lleguen dos peticiones
 * a la vez (D-019). Las reservas subordinadas nunca pasan por aquí.
 */
final class GestorReservas
{
    public const NOCHES_MAXIMAS = 366;

    /**
     * @param  bool  $registrar  genera eventos y auditoría; el demo lo apaga para no parecer actividad real
     */
    public function __construct(private readonly bool $registrar = true)
    {
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function crear(Propiedad $propiedad, array $datos): Reserva
    {
        if (! $propiedad->esIndependiente()) {
            throw new LogicException('Solo una propiedad independiente crea reservas propias.');
        }
        $tipo = TipoReserva::tryFrom((string) ($datos['tipo'] ?? '')) ?? TipoReserva::Reserva;
        $fila = $this->preparar($propiedad, $tipo, $datos);

        return DB::transaction(function () use ($propiedad, $fila, $tipo, $datos): Reserva {
            // El candado sobre la propiedad reparte el consecutivo sin huecos ni repetidos.
            $bloqueada = Propiedad::query()->whereKey($propiedad->id)->lockForUpdate()->firstOrFail();
            $numero = $bloqueada->consecutivo_reserva + 1;
            $bloqueada->forceFill(['consecutivo_reserva' => $numero])->save();

            $reserva = new Reserva($fila + [
                'propiedad_id' => $propiedad->id,
                'tipo' => $tipo,
                'numero' => (string) $numero,
                'codigo_publico' => Huellas::codigoPublico(),
                'moneda' => $propiedad->moneda,
                'origen' => OrigenFila::AlojaFlow,
                'sincronizada' => false,
            ]);
            $reserva->save();
            $this->ocuparNoches($reserva);
            $this->asignarTitular($reserva);
            $this->guardarCargos($reserva, $datos);
            $reserva->refresh();
            $this->guardarImpuestos($reserva);

            $this->anotar($reserva, TipoEventoReserva::Creada, 'reserva.crear', ['despues' => $this->resumen($reserva)]);

            return $reserva;
        });
    }

    /**
     * Una reserva ya hecha pasa a un grupo (D-146). Fechas, unidad y precio no cambian: se vendió así.
     */
    public function unirAGrupo(Reserva $reserva, int $grupoId): Reserva
    {
        return DB::transaction(function () use ($reserva, $grupoId): Reserva {
            $actual = $this->bloquear($reserva);
            if ($actual->grupo_id !== null || $actual->estado === EstadoReserva::Cancelada || $actual->tipo !== TipoReserva::Reserva) {
                throw new ReservaRechazada(__('ui.grupos.error_reservas'));
            }
            $antes = $this->resumen($actual);
            $actual->grupo_id = $grupoId;
            $actual->version++;
            $actual->save();
            $this->anotar($actual, TipoEventoReserva::Modificada, 'reserva.modificar', ['antes' => $antes, 'despues' => $this->resumen($actual)]);

            return $actual;
        });
    }

    /**
     * @param  array<string, mixed>  $datos
     * @param  int|null  $version  la que tenía el formulario; si otra persona la cambió, se rechaza
     */
    public function modificar(Reserva $reserva, array $datos, ?int $version = null): Reserva
    {
        return DB::transaction(function () use ($reserva, $datos, $version): Reserva {
            $actual = $this->bloquear($reserva);
            if ($version !== null && $actual->version !== $version) {
                throw new ReservaRechazada(__('ui.reservas.error_version'));
            }
            $antes = $this->resumen($actual);
            $fila = $this->preparar($actual->propiedad, $actual->tipo, $datos + ['estado' => $actual->estado->value]);
            if ($actual->estado === EstadoReserva::Cancelada) {
                $fila['estado'] = EstadoReserva::Cancelada;
            }
            // La tarifa con que se vendió se queda hasta que alguien elija otra: retirarla o cambiar de unidad no cambia lo que se pactó.
            if ($actual->tarifa_id !== null && (! array_key_exists('tarifa_id', $datos) || (int) $datos['tarifa_id'] === $actual->tarifa_id)) {
                $fila['tarifa_id'] = $actual->tarifa_id;
                $fila['tarifa_nombre'] = $actual->tarifa_nombre;
            }

            $actual->fill($fila);
            $actual->version++;
            $actual->save();
            $this->ocuparNoches($actual);
            $this->asignarTitular($actual);
            $this->guardarCargos($actual, $datos);
            $actual->refresh();
            $this->guardarImpuestos($actual);

            $this->anotar($actual, TipoEventoReserva::Modificada, 'reserva.modificar', ['antes' => $antes, 'despues' => $this->resumen($actual)]);

            return $actual;
        });
    }

    /**
     * Mueve la reserva a otra unidad o a otras fechas, con las mismas noches (arrastrar en el
     * calendario). El total no cambia: se revisa aparte si hace falta.
     */
    public function mover(Reserva $reserva, int $unidadId, CarbonImmutable $llegada): Reserva
    {
        return DB::transaction(function () use ($reserva, $unidadId, $llegada): Reserva {
            $actual = $this->bloquear($reserva);
            if ($actual->estado === EstadoReserva::Cancelada) {
                throw new ReservaRechazada(__('ui.reservas.error_cancelada'));
            }
            $unidad = Unidad::query()->where('propiedad_id', $actual->propiedad_id)->where('activa', true)->find($unidadId);
            if ($unidad === null) {
                throw new ReservaRechazada(__('ui.reservas.error_unidad'));
            }
            if ($actual->tipo === TipoReserva::Reserva && $unidad->capacidad > 0 && $actual->personas > $unidad->capacidadMaxima()) {
                throw new ReservaRechazada(__('ui.reservas.error_capacidad', ['capacidad' => $unidad->capacidadMaxima()]));
            }
            $noches = (int) $actual->llegada->diffInDays($actual->salida);
            $antes = $this->resumen($actual);
            $actual->unidad_id = $unidad->id;
            $actual->llegada = $llegada->startOfDay();
            $actual->salida = $llegada->startOfDay()->addDays($noches);
            $actual->version++;
            $actual->save();
            $this->ocuparNoches($actual);
            $actual->refresh();
            $this->guardarImpuestos($actual);
            $this->anotar($actual, TipoEventoReserva::Modificada, 'reserva.mover', ['antes' => $antes, 'despues' => $this->resumen($actual)]);

            return $actual;
        });
    }

    /**
     * Otra unidad y otras fechas, con otras noches: lo de un grupo, donde cada habitación puede tener las suyas. Solo si están libres. El total no cambia:
     * lo pone el grupo.
     */
    public function cambiarEstancia(Reserva $reserva, int $unidadId, CarbonImmutable $llegada, CarbonImmutable $salida): Reserva
    {
        return DB::transaction(function () use ($reserva, $unidadId, $llegada, $salida): Reserva {
            $actual = $this->bloquear($reserva);
            if ($actual->estado === EstadoReserva::Cancelada) {
                throw new ReservaRechazada(__('ui.reservas.error_cancelada'));
            }
            $unidad = Unidad::query()->where('propiedad_id', $actual->propiedad_id)->where('activa', true)->find($unidadId);
            if ($unidad === null) {
                throw new ReservaRechazada(__('ui.reservas.error_unidad'), 'unidad_id');
            }
            if ($unidad->capacidad > 0 && $actual->personas > $unidad->capacidadMaxima()) {
                throw new ReservaRechazada(__('ui.reservas.error_capacidad', ['capacidad' => $unidad->capacidadMaxima()]), 'unidad_id');
            }
            [$llegada, $salida] = $this->fechas(['llegada' => $llegada->format('Y-m-d'), 'salida' => $salida->format('Y-m-d')]);
            $antes = $this->resumen($actual);
            $actual->unidad_id = $unidad->id;
            $actual->llegada = $llegada;
            $actual->salida = $salida;
            $actual->version++;
            $actual->save();
            $this->ocuparNoches($actual);
            $actual->refresh();
            $this->guardarImpuestos($actual);
            $this->anotar($actual, TipoEventoReserva::Modificada, 'reserva.modificar', ['antes' => $antes, 'despues' => $this->resumen($actual)]);

            return $actual;
        });
    }

    /**
     * Otras fechas en la misma unidad: arrastrar un extremo en el calendario (D-124). Un grupo pone su propio precio y sus fechas se cambian desde el grupo, y quien
     * ya llegó no cambia su llegada. El total no cambia: quien atiende decide.
     */
    public function cambiarFechas(Reserva $reserva, CarbonImmutable $llegada, CarbonImmutable $salida): Reserva
    {
        if ($reserva->grupo_id !== null) {
            throw new ReservaRechazada(__('ui.calendario.error_grupo'));
        }
        if ($reserva->salida_registrada_en !== null || ($reserva->entrada_registrada_en !== null && $llegada->format('Y-m-d') !== $reserva->llegada->format('Y-m-d'))) {
            throw new ReservaRechazada(__('ui.calendario.error_ya_llego'));
        }

        return $this->cambiarEstancia($reserva, $reserva->unidad_id, $llegada, $salida);
    }

    /**
     * Lo que costaría hoy según su tarifa, con sus cargos, su descuento y lo que no viene del catálogo. Null: sin tarifa.
     */
    public function totalSegunTarifa(Reserva $reserva): ?int
    {
        $presupuesto = $this->presupuestoDe($reserva);

        return $presupuesto->cotizacion === null ? null : $presupuesto->total() + $this->cargosSinCatalogo($reserva);
    }

    /**
     * Pone el total y los cargos que salen de la tarifa, como el botón del formulario.
     */
    public function aplicarTarifa(Reserva $reserva): Reserva
    {
        $presupuesto = $this->presupuestoDe($reserva);
        if ($presupuesto->cotizacion === null || $reserva->grupo_id !== null) {
            return $reserva;
        }

        return $this->modificar($reserva, [
            'unidad_id' => $reserva->unidad_id,
            'titular_id' => $reserva->titular_id,
            'llegada' => $reserva->llegada->format('Y-m-d'),
            'salida' => $reserva->salida->format('Y-m-d'),
            'personas' => $reserva->personas,
            'comentario' => $reserva->comentario,
            'origen_texto' => $reserva->origen_texto,
            'deposito' => $reserva->deposito,
            'comision' => $reserva->comision,
            'descuento' => $presupuesto->descuento,
            'total' => $presupuesto->total() + $this->cargosSinCatalogo($reserva),
            'cargos' => array_map(fn (int $concepto, float $cantidad): array => ['concepto_cargo_id' => $concepto, 'cantidad' => $cantidad], array_keys($this->cargosElegidos($reserva)), array_values($this->cargosElegidos($reserva))),
        ]);
    }

    private function presupuestoDe(Reserva $reserva): Presupuesto
    {
        $cupon = $reserva->cupon;
        $canalCobra = filled($reserva->origen_texto) && (bool) Canal::query()->where('propiedad_id', $reserva->propiedad_id)->where('nombre', $reserva->origen_texto)->value('cobra_impuestos');

        return Cotizador::presupuesto($reserva->unidad, $reserva->llegada, $reserva->salida, max(1, $reserva->personas), $this->cargosElegidos($reserva), $cupon, $cupon === null ? $reserva->descuento : 0, max(0, (int) $reserva->ninos), $canalCobra, $reserva->tarifa_id);
    }

    /**
     * Los que alguien eligió: los obligatorios se suman solos.
     *
     * @return array<int, float>
     */
    private function cargosElegidos(Reserva $reserva): array
    {
        $elegidos = [];
        foreach ($reserva->cargos()->with('concepto')->whereNotNull('concepto_cargo_id')->get() as $cargo) {
            if ($cargo->concepto !== null && ! $cargo->concepto->obligatorio) {
                $elegidos[$cargo->concepto->id] = (float) $cargo->cantidad;
            }
        }

        return $elegidos;
    }

    private function cargosSinCatalogo(Reserva $reserva): int
    {
        return (int) $reserva->cargos()->whereNull('concepto_cargo_id')->sum('importe');
    }

    /**
     * Solo el precio: lo que le toca a esta habitación del total de su grupo.
     */
    public function cambiarTotal(Reserva $reserva, int $total): Reserva
    {
        return DB::transaction(function () use ($reserva, $total): Reserva {
            $actual = $this->bloquear($reserva);
            if ($actual->total === $total) {
                return $actual;
            }
            if ($total < 0) {
                throw new ReservaRechazada(__('ui.reservas.error_importe'), 'total');
            }
            $antes = $this->resumen($actual);
            $actual->total = $total;
            $actual->version++;
            $actual->save();
            $this->ocuparNoches($actual);
            $actual->refresh();
            $this->guardarImpuestos($actual);
            $this->anotar($actual, TipoEventoReserva::Modificada, 'reserva.modificar', ['antes' => $antes, 'despues' => $this->resumen($actual)]);

            return $actual;
        });
    }

    /**
     * Un cargo que no viene del catálogo, como lo que marcó un medidor. Sube el total.
     */
    public function agregarCargo(Reserva $reserva, string $nombre, float $cantidad, int $importe): CargoReserva
    {
        return DB::transaction(function () use ($reserva, $nombre, $cantidad, $importe): CargoReserva {
            $actual = $this->bloquear($reserva);
            if ($actual->estado === EstadoReserva::Cancelada) {
                throw new ReservaRechazada(__('ui.reservas.error_cancelada'));
            }
            $cargo = $actual->cargos()->create(['concepto_cargo_id' => null, 'nombre' => mb_substr($nombre, 0, 100), 'cantidad' => $cantidad, 'importe' => $importe]);
            $this->cambiarTotal($actual, $actual->total + $importe);

            return $cargo;
        });
    }

    public function quitarCargo(CargoReserva $cargo): void
    {
        DB::transaction(function () use ($cargo): void {
            $actual = $this->bloquear($cargo->reserva);
            $this->cambiarTotal($actual, max(0, $actual->total - $cargo->importe));
            $cargo->delete();
        });
    }

    public function cambiarPersonas(Reserva $reserva, int $personas): Reserva
    {
        return DB::transaction(function () use ($reserva, $personas): Reserva {
            $actual = $this->bloquear($reserva);
            if ($actual->personas === $personas) {
                return $actual;
            }
            if ($personas < 1) {
                throw new ReservaRechazada(__('ui.reservas.error_personas'), 'personas');
            }
            $capacidad = $actual->unidad->capacidadMaxima();
            if ($capacidad > 0 && $personas > $capacidad) {
                throw new ReservaRechazada(__('ui.reservas.error_capacidad', ['capacidad' => $capacidad]), 'personas');
            }
            $antes = $this->resumen($actual);
            $actual->personas = $personas;
            $actual->version++;
            $actual->save();
            $this->anotar($actual, TipoEventoReserva::Modificada, 'reserva.modificar', ['antes' => $antes, 'despues' => $this->resumen($actual)]);

            return $actual;
        });
    }

    /**
     * Hasta cuándo se apartan las noches de una reserva sin confirmar. Sin fecha, no caduca.
     */
    public function fijarVencimiento(Reserva $reserva, ?Carbon $hasta): Reserva
    {
        return DB::transaction(function () use ($reserva, $hasta): Reserva {
            $actual = $this->bloquear($reserva);
            if ($actual->estado === EstadoReserva::SinConfirmar) {
                $actual->expira_en = $hasta;
                $actual->save();
            }

            return $actual;
        });
    }

    /**
     * @param  bool  $porVencimiento  Se le acabó el tiempo a la retención; no la canceló una persona, y un pago que llegue tarde todavía puede recuperarla.
     */
    public function cancelar(Reserva $reserva, bool $porVencimiento = false): Reserva
    {
        return DB::transaction(function () use ($reserva, $porVencimiento): Reserva {
            $actual = $this->bloquear($reserva);
            if ($actual->estado === EstadoReserva::Cancelada) {
                return $actual;
            }
            if ($actual->estaEnCasa()) {
                throw new ReservaRechazada(__('ui.reservas.error_en_casa'));
            }
            $actual->estado = EstadoReserva::Cancelada;
            if (! $porVencimiento) {
                $actual->expira_en = null;
            }
            $actual->version++;
            $actual->save();
            $actual->nochesOcupadas()->delete();
            $this->anotar($actual, TipoEventoReserva::Cancelada, 'reserva.cancelar');

            return $actual;
        });
    }

    public function reactivar(Reserva $reserva): Reserva
    {
        return DB::transaction(function () use ($reserva): Reserva {
            $actual = $this->bloquear($reserva);
            if ($actual->estado !== EstadoReserva::Cancelada) {
                return $actual;
            }
            $actual->estado = $actual->tipo === TipoReserva::Bloqueo ? EstadoReserva::Confirmada : EstadoReserva::SinConfirmar;
            // Si no, la tarea de vencidas la volvería a cancelar en su siguiente corrida.
            $actual->expira_en = null;
            $actual->version++;
            $actual->save();
            $this->ocuparNoches($actual);
            $this->anotar($actual, TipoEventoReserva::Reactivada, 'reserva.reactivar');

            return $actual;
        });
    }

    /**
     * Confirma una reserva sin confirmar y quita su retención.
     */
    public function confirmar(Reserva $reserva): Reserva
    {
        return DB::transaction(function () use ($reserva): Reserva {
            $actual = $this->bloquear($reserva);
            if ($actual->estado === EstadoReserva::Cancelada) {
                throw new ReservaRechazada(__('ui.reservas.error_cancelada'));
            }
            $yaEstaba = $actual->estado === EstadoReserva::Confirmada;
            $actual->estado = EstadoReserva::Confirmada;
            $actual->expira_en = null;
            $actual->version++;
            $actual->save();
            if (! $yaEstaba) {
                $this->anotar($actual, TipoEventoReserva::Modificada, 'reserva.confirmar', ['accion' => 'confirmar']);
            }

            return $actual;
        });
    }

    public function registrarEntrada(Reserva $reserva): Reserva
    {
        return DB::transaction(function () use ($reserva): Reserva {
            $actual = $this->bloquear($reserva);
            if ($actual->tipo !== TipoReserva::Reserva || $actual->estado === EstadoReserva::Cancelada || $actual->entrada_registrada_en !== null) {
                throw new ReservaRechazada(__('ui.reservas.error_entrada'));
            }
            if ($actual->llegada->format('Y-m-d') > $actual->propiedad->hoy()->format('Y-m-d')) {
                throw new ReservaRechazada(__('ui.reservas.error_entrada_futura'));
            }
            $actual->entrada_registrada_en = now('UTC');
            $actual->estado = EstadoReserva::Confirmada;
            $actual->version++;
            $actual->save();
            $this->anotar($actual, TipoEventoReserva::Modificada, 'reserva.entrada', ['accion' => 'entrada']);

            return $actual;
        });
    }

    public function registrarSalida(Reserva $reserva): Reserva
    {
        return DB::transaction(function () use ($reserva): Reserva {
            $actual = $this->bloquear($reserva);
            if (! $actual->estaEnCasa()) {
                throw new ReservaRechazada(__('ui.reservas.error_salida'));
            }
            $actual->salida_registrada_en = now('UTC');
            $actual->version++;
            $actual->save();
            $this->anotar($actual, TipoEventoReserva::Modificada, 'reserva.salida', ['accion' => 'salida']);
            app(GestorLimpieza::class)->salidaRegistrada($actual);

            return $actual;
        });
    }

    /**
     * Corrige la última marca: quita la salida o, si no hay salida, la entrada.
     */
    public function deshacerRegistro(Reserva $reserva): Reserva
    {
        return DB::transaction(function () use ($reserva): Reserva {
            $actual = $this->bloquear($reserva);
            if ($actual->salida_registrada_en !== null) {
                $actual->salida_registrada_en = null;
            } elseif ($actual->entrada_registrada_en !== null) {
                $actual->entrada_registrada_en = null;
            } else {
                return $actual;
            }
            $actual->version++;
            $actual->save();
            $this->anotar($actual, TipoEventoReserva::Modificada, 'reserva.deshacer_registro', ['accion' => 'deshacer']);

            return $actual;
        });
    }

    /**
     * @param  int  $importe  en la unidad menor; negativo = devolución
     * @param  PagoRecibido|null  $recibido  si se pagó en la segunda moneda (D-157): lo que de verdad se recibió
     */
    public function registrarPago(Reserva $reserva, int $importe, MetodoPago $metodo, CarbonImmutable $fecha, ?string $referencia = null, ?int $cajaId = null, ?PagoRecibido $recibido = null): Pago
    {
        if ($importe === 0) {
            throw new ReservaRechazada(__('ui.pagos.error_importe'), 'importe');
        }

        return DB::transaction(function () use ($reserva, $importe, $metodo, $fecha, $referencia, $cajaId, $recibido): Pago {
            $actual = $this->bloquear($reserva);
            if ($actual->tipo !== TipoReserva::Reserva) {
                throw new ReservaRechazada(__('ui.pagos.error_bloqueo'), 'importe');
            }
            $pago = new Pago([
                'propiedad_id' => $actual->propiedad_id,
                'reserva_id' => $actual->id,
                'caja_id' => $cajaId,
                'fecha' => $fecha,
                'metodo' => $metodo,
                'importe' => $importe,
                'moneda' => $actual->moneda,
                'referencia' => $referencia === '' ? null : $referencia,
                'user_id' => auth()->id(),
                'moneda_recibida' => $recibido?->moneda,
                'importe_recibido' => $recibido?->importe,
                'tipo_cambio' => $recibido?->tipoCambio,
            ]);
            $pago->save();
            $this->recalcularPagado($actual);
            if ($this->registrar) {
                RegistroAuditoria::anotar('pago.registrar', $pago, ['reserva' => $actual->id, 'importe' => $importe, 'moneda' => $actual->moneda]
                    + ($recibido === null ? [] : ['moneda_recibida' => $recibido->moneda, 'importe_recibido' => $recibido->importe, 'tipo_cambio' => $recibido->tipoCambio]), $actual->propiedad_id);
            }

            return $pago;
        });
    }

    public function quitarPago(Pago $pago): void
    {
        DB::transaction(function () use ($pago): void {
            $actual = $this->bloquear($pago->reserva);
            $importe = $pago->importe;
            $pago->delete();
            $this->recalcularPagado($actual);
            if ($this->registrar) {
                RegistroAuditoria::anotar('pago.quitar', $pago, ['reserva' => $actual->id, 'importe' => $importe, 'moneda' => $actual->moneda], $actual->propiedad_id);
            }
        });
    }

    /**
     * Comprueba las reglas y deja la fila lista para guardar.
     *
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private function preparar(Propiedad $propiedad, TipoReserva $tipo, array $datos): array
    {
        $unidad = Unidad::query()->where('propiedad_id', $propiedad->id)->where('activa', true)->find((int) ($datos['unidad_id'] ?? 0));
        if ($unidad === null) {
            throw new ReservaRechazada(__('ui.reservas.error_unidad'), 'unidad_id');
        }
        [$llegada, $salida] = $this->fechas($datos);

        $comentario = trim((string) ($datos['comentario'] ?? ''));
        $origen = trim((string) ($datos['origen_texto'] ?? ''));
        $base = [
            'unidad_id' => $unidad->id,
            'llegada' => $llegada->format('Y-m-d'),
            'salida' => $salida->format('Y-m-d'),
            'comentario' => $comentario === '' ? null : $comentario,
            'origen_texto' => $origen === '' ? null : mb_substr($origen, 0, 100),
        ];

        if ($tipo === TipoReserva::Bloqueo) {
            if (array_key_exists('propietario_id', $datos)) {
                // Solo el dueño de esa unidad: la estancia sale en su estado de cuenta, y él puede liberarla.
                $base['propietario_id'] = (int) $datos['propietario_id'] === $unidad->propietario_id ? $unidad->propietario_id : null;
            }

            return $base + [
                'titular_id' => null, 'estado' => EstadoReserva::Confirmada, 'personas' => 0, 'tarifa_nombre' => null, 'tarifa_id' => null,
                'total' => 0, 'deposito' => 0, 'descuento' => 0, 'comision' => 0,
            ];
        }

        $titular = Huesped::query()->where('propiedad_id', $propiedad->id)->find((int) ($datos['titular_id'] ?? 0));
        if ($titular === null) {
            throw new ReservaRechazada(__('ui.reservas.error_titular'), 'titular_id');
        }
        $personas = (int) ($datos['personas'] ?? 0);
        if ($personas < 1) {
            throw new ReservaRechazada(__('ui.reservas.error_personas'), 'personas');
        }
        if ($unidad->capacidad > 0 && $personas > $unidad->capacidadMaxima()) {
            throw new ReservaRechazada(__('ui.reservas.error_capacidad', ['capacidad' => $unidad->capacidadMaxima()]), 'personas');
        }
        $importes = [];
        foreach (['total', 'deposito', 'descuento', 'comision'] as $campo) {
            $importes[$campo] = (int) ($datos[$campo] ?? 0);
            if ($importes[$campo] < 0) {
                throw new ReservaRechazada(__('ui.reservas.error_importe'), $campo);
            }
        }
        $estado = EstadoReserva::tryFrom((string) ($datos['estado'] ?? '')) ?? EstadoReserva::Confirmada;

        if ($estado === EstadoReserva::Cancelada) {
            $estado = EstadoReserva::SinConfirmar;
        }

        $cotizacion = Cotizador::cotizar($unidad, $llegada, $salida, $personas, blank($datos['tarifa_id'] ?? null) ? null : (int) $datos['tarifa_id']);
        $fila = $base + $importes + [
            'titular_id' => $titular->id,
            'estado' => $estado,
            'personas' => $personas,
            'tarifa_nombre' => $cotizacion?->tarifa,
            'tarifa_id' => $cotizacion?->tarifaId,
        ];
        // Solo si vienen: quien cambia una reserva desde otra pantalla no los conoce, y no debe borrarlos.
        if (array_key_exists('ninos', $datos)) {
            $fila['ninos'] = min($personas, max(0, (int) $datos['ninos']));
        }
        if (array_key_exists('empresa_id', $datos)) {
            $fila['empresa_id'] = blank($datos['empresa_id']) ? null : Empresa::query()->where('propiedad_id', $propiedad->id)->whereKey((int) $datos['empresa_id'])->value('id');
        }
        if (array_key_exists('grupo_id', $datos)) {
            $fila['grupo_id'] = $datos['grupo_id'] === null ? null : (int) $datos['grupo_id'];
        }
        if (array_key_exists('idioma', $datos)) {
            $fila['idioma'] = $datos['idioma'];
        }
        if (array_key_exists('equipo', $datos)) {
            $equipo = array_filter(array_intersect_key((array) $datos['equipo'], array_flip(['tipo', 'largo', 'vehiculos', 'placas'])), fn (mixed $valor): bool => filled($valor));
            $fila['equipo'] = $equipo === [] ? null : $equipo;
        }
        if (array_key_exists('expira_en', $datos)) {
            $fila['expira_en'] = $datos['expira_en'];
        }
        if (array_key_exists('cupon_codigo', $datos)) {
            $fila['cupon_id'] = $this->cuponValido($propiedad, (string) $datos['cupon_codigo'], $llegada, (int) $llegada->diffInDays($salida), isset($datos['reserva_id']) ? (int) $datos['reserva_id'] : null);
        }

        return $fila;
    }

    private function cuponValido(Propiedad $propiedad, string $codigo, CarbonImmutable $llegada, int $noches, ?int $reservaId): ?int
    {
        if (trim($codigo) === '') {
            return null;
        }
        $cupon = Cupones::buscar($propiedad->id, $codigo);
        if ($cupon === null) {
            throw new ReservaRechazada(__('ui.cupones.error_no_existe'), 'cupon_codigo');
        }
        $motivo = Cupones::motivoRechazo($cupon, $llegada, $noches, $reservaId);
        if ($motivo !== null) {
            throw new ReservaRechazada($motivo, 'cupon_codigo');
        }

        return $cupon->id;
    }

    /**
     * Una foto de lo que se cobró de impuestos, para que el informe no cambie si mañana cambia la tasa (D-082).
     */
    private function guardarImpuestos(Reserva $reserva): void
    {
        $reserva->impuestos()->delete();
        if ($reserva->tipo === TipoReserva::Reserva) {
            $reserva->impuestos()->createMany(Cotizador::impuestosDeReserva($reserva));
        }
    }

    /**
     * Las líneas de cargos adicionales: las obligatorias del catálogo y las elegidas en el
     * formulario. Solo si el formulario las manda: otros caminos, como el demo, no las tocan.
     *
     * @param  array<string, mixed>  $datos
     */
    private function guardarCargos(Reserva $reserva, array $datos): void
    {
        if (! array_key_exists('cargos', $datos) || $reserva->tipo !== TipoReserva::Reserva) {
            return;
        }
        $elegidos = [];
        foreach ((array) $datos['cargos'] as $linea) {
            $concepto = (int) ($linea['concepto_cargo_id'] ?? 0);
            if ($concepto > 0) {
                $elegidos[$concepto] = max(0.01, (float) ($linea['cantidad'] ?? 1));
            }
        }
        $presupuesto = Cotizador::presupuesto($reserva->unidad, $reserva->llegada, $reserva->salida, $reserva->personas, $elegidos, tarifaId: $reserva->tarifa_id);
        // Lo que no viene del catálogo, como lo que marcó un medidor, no está en el formulario: se queda.
        $reserva->cargos()->whereNotNull('concepto_cargo_id')->delete();
        foreach ($presupuesto->cargos as $cargo) {
            $reserva->cargos()->create($cargo);
        }
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function fechas(array $datos): array
    {
        if (blank($datos['llegada'] ?? null) || blank($datos['salida'] ?? null)) {
            throw new ReservaRechazada(__('ui.reservas.error_fechas'), 'salida');
        }
        try {
            $llegada = CarbonImmutable::parse((string) $datos['llegada'], 'UTC')->startOfDay();
            $salida = CarbonImmutable::parse((string) $datos['salida'], 'UTC')->startOfDay();
        } catch (Throwable) {
            throw new ReservaRechazada(__('ui.reservas.error_fechas'), 'salida');
        }
        if ($salida->lte($llegada) || $llegada->diffInDays($salida) > self::NOCHES_MAXIMAS) {
            throw new ReservaRechazada(__('ui.reservas.error_fechas'), 'salida');
        }

        return [$llegada, $salida];
    }

    /**
     * Sustituye las noches de la reserva. Si otra ya ocupa alguna, el índice único lo rechaza.
     */
    private function ocuparNoches(Reserva $reserva): void
    {
        $reserva->nochesOcupadas()->delete();
        if ($reserva->estado === EstadoReserva::Cancelada) {
            return;
        }

        $this->revisarVinculadas($reserva);

        $cuantas = (int) $reserva->llegada->diffInDays($reserva->salida);
        $filas = [];
        foreach (Dinero::repartir($reserva->total, $cuantas) as $i => $importe) {
            $filas[] = [
                'reserva_id' => $reserva->id,
                'propiedad_id' => $reserva->propiedad_id,
                'unidad_id' => $reserva->unidad_id,
                'fecha' => $reserva->llegada->addDays($i)->format('Y-m-d'),
                'importe' => $importe,
                'exclusiva' => 1,
            ];
        }

        try {
            DB::table('reservas_noches')->insert($filas);
        } catch (QueryException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) === 1062) { // clave duplicada en MySQL y MariaDB
                throw new ReservaRechazada(__('ui.reservas.error_ocupada'), 'unidad_id');
            }
            throw $e;
        }
    }

    /**
     * Una unidad vinculada ocupada en esas fechas cierra esta (D-155). El índice único solo ve la misma unidad: aquí, el candado
     * sobre las unidades vinculadas, siempre en el mismo orden, hace esperar a otra reserva que llegue a la vez, y la lectura con
     * candado ve lo que esa otra ya guardó.
     */
    private function revisarVinculadas(Reserva $reserva): void
    {
        $vinculadas = UnidadesVinculadas::conflictos($reserva->unidad_id);
        if ($vinculadas === []) {
            return;
        }
        $todas = [...$vinculadas, $reserva->unidad_id];
        sort($todas);
        Unidad::query()->whereIn('id', $todas)->orderBy('id')->lockForUpdate()->pluck('id');
        $ocupada = DB::table('reservas_noches')->whereIn('unidad_id', $vinculadas)
            ->where('fecha', '>=', $reserva->llegada->format('Y-m-d'))->where('fecha', '<', $reserva->salida->format('Y-m-d'))
            ->where('reserva_id', '!=', $reserva->id)->orderBy('fecha')->sharedLock()->value('unidad_id');
        if ($ocupada !== null) {
            throw new ReservaRechazada(__('ui.reservas.error_vinculada', ['unidad' => (string) Unidad::query()->whereKey((int) $ocupada)->value('nombre')]), 'unidad_id');
        }
    }

    /**
     * El titular es también el primero de los huéspedes de la reserva; el resto no se toca.
     */
    private function asignarTitular(Reserva $reserva): void
    {
        if ($reserva->titular_id !== null) {
            $reserva->huespedes()->syncWithoutDetaching([$reserva->titular_id => ['orden' => 0]]);
        }
    }

    private function bloquear(Reserva $reserva): Reserva
    {
        $actual = Reserva::query()->with('propiedad')->whereKey($reserva->id)->lockForUpdate()->firstOrFail();
        if ($actual->sincronizada || ! $actual->propiedad->esIndependiente()) {
            throw new LogicException('Esta reserva la controla la principal.');
        }

        return $actual;
    }

    private function recalcularPagado(Reserva $reserva): void
    {
        $reserva->pagado = (int) Pago::query()->where('reserva_id', $reserva->id)->sum('importe');
        $reserva->save();
    }

    /**
     * @return array<string, mixed>
     */
    private function resumen(Reserva $reserva): array
    {
        return [
            'unidad_id' => $reserva->unidad_id,
            'llegada' => $reserva->llegada->format('Y-m-d'),
            'salida' => $reserva->salida->format('Y-m-d'),
            'estado' => $reserva->estado->value,
            'total' => $reserva->total,
        ];
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function anotar(Reserva $reserva, TipoEventoReserva $tipo, string $accion, array $datos = []): void
    {
        if (! $this->registrar) {
            return;
        }
        EventoReserva::query()->create([
            'propiedad_id' => $reserva->propiedad_id,
            'reserva_id' => $reserva->id,
            'tipo' => $tipo,
            'datos' => $datos === [] ? null : $datos,
            'ocurrido_en' => now('UTC'),
        ]);
        RegistroAuditoria::anotar($accion, $reserva, $datos, $reserva->propiedad_id);
    }
}
