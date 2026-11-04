<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Reservas;

use App\Enums\EstadoReserva;
use App\Enums\MetodoPago;
use App\Enums\OrigenFila;
use App\Enums\TipoEventoReserva;
use App\Enums\TipoReserva;
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
    private const NOCHES_MAXIMAS = 366;

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

        return DB::transaction(function () use ($propiedad, $fila, $tipo): Reserva {
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
            $reserva->refresh();

            $this->anotar($reserva, TipoEventoReserva::Creada, 'reserva.crear', ['despues' => $this->resumen($reserva)]);

            return $reserva;
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

            $actual->fill($fila);
            $actual->version++;
            $actual->save();
            $this->ocuparNoches($actual);
            $this->asignarTitular($actual);
            $actual->refresh();

            $this->anotar($actual, TipoEventoReserva::Modificada, 'reserva.modificar', ['antes' => $antes, 'despues' => $this->resumen($actual)]);

            return $actual;
        });
    }

    public function cancelar(Reserva $reserva): Reserva
    {
        return DB::transaction(function () use ($reserva): Reserva {
            $actual = $this->bloquear($reserva);
            if ($actual->estado === EstadoReserva::Cancelada) {
                return $actual;
            }
            if ($actual->estaEnCasa()) {
                throw new ReservaRechazada(__('ui.reservas.error_en_casa'));
            }
            $actual->estado = EstadoReserva::Cancelada;
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
            $actual->version++;
            $actual->save();
            $this->ocuparNoches($actual);
            $this->anotar($actual, TipoEventoReserva::Reactivada, 'reserva.reactivar');

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
            if ($actual->llegada->gt($actual->propiedad->hoy())) {
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
     */
    public function registrarPago(Reserva $reserva, int $importe, MetodoPago $metodo, CarbonImmutable $fecha, ?string $referencia = null): Pago
    {
        if ($importe === 0) {
            throw new ReservaRechazada(__('ui.pagos.error_importe'), 'importe');
        }

        return DB::transaction(function () use ($reserva, $importe, $metodo, $fecha, $referencia): Pago {
            $actual = $this->bloquear($reserva);
            if ($actual->tipo !== TipoReserva::Reserva) {
                throw new ReservaRechazada(__('ui.pagos.error_bloqueo'), 'importe');
            }
            $pago = new Pago([
                'propiedad_id' => $actual->propiedad_id,
                'reserva_id' => $actual->id,
                'fecha' => $fecha,
                'metodo' => $metodo,
                'importe' => $importe,
                'moneda' => $actual->moneda,
                'referencia' => $referencia === '' ? null : $referencia,
                'user_id' => auth()->id(),
            ]);
            $pago->save();
            $this->recalcularPagado($actual);
            if ($this->registrar) {
                RegistroAuditoria::anotar('pago.registrar', $pago, ['reserva' => $actual->id, 'importe' => $importe, 'moneda' => $actual->moneda], $actual->propiedad_id);
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
            return $base + [
                'titular_id' => null, 'estado' => EstadoReserva::Confirmada, 'personas' => 0, 'tarifa_nombre' => null,
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
        if ($unidad->capacidad > 0 && $personas > $unidad->capacidad) {
            throw new ReservaRechazada(__('ui.reservas.error_capacidad', ['capacidad' => $unidad->capacidad]), 'personas');
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

        return $base + $importes + [
            'titular_id' => $titular->id,
            'estado' => $estado,
            'personas' => $personas,
            'tarifa_nombre' => Cotizador::cotizar($unidad, $llegada, $salida)?->tarifa,
        ];
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
