<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Reservas;

use App\Enums\EstadoComprobante;
use App\Enums\EstadoGrupo;
use App\Enums\EstadoReserva;
use App\Enums\MetodoPago;
use App\Enums\ModoPrecioGrupo;
use App\Grupos\CalculadorPrecio;
use App\Models\CuotaGrupo;
use App\Models\Grupo;
use App\Models\GrupoHabitacion;
use App\Models\Propiedad;
use App\Models\RegistroAuditoria;
use App\Models\Reserva;
use App\Models\Unidad;
use App\Support\Dinero;
use App\Support\Huellas;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Reservas de varias unidades con un mismo coordinador: se crean todas o ninguna, y un pago del
 * grupo se reparte entre lo que debe cada una. Cada habitación es una reserva normal: los informes, la caja y la disponibilidad no tienen que saber de grupos.
 */
final class GestorGrupos
{
    public function __construct(private readonly GestorReservas $reservas)
    {
    }

    /**
     * @param  list<int>  $unidades
     */
    public function crear(Propiedad $propiedad, string $nombre, int $coordinadorId, array $unidades, CarbonImmutable $llegada, CarbonImmutable $salida, int $personasPorUnidad, EstadoReserva $estado, ?string $notas = null): Grupo
    {
        if ($unidades === []) {
            throw new ReservaRechazada(__('ui.grupos.error_sin_unidades'), 'unidades');
        }

        return DB::transaction(function () use ($propiedad, $nombre, $coordinadorId, $unidades, $llegada, $salida, $personasPorUnidad, $estado, $notas): Grupo {
            $grupo = Grupo::query()->create([
                'propiedad_id' => $propiedad->id,
                'codigo' => 'GRP-'.Huellas::codigoPublico(6),
                'nombre' => $nombre,
                'estado' => $estado === EstadoReserva::SinConfirmar ? EstadoGrupo::Tentativo : EstadoGrupo::Confirmado,
                'coordinador_id' => $coordinadorId,
                'notas' => $notas,
            ])->refresh();
            foreach ($unidades as $unidadId) {
                $this->crearHabitacion($grupo, $unidadId, $llegada, $salida, $personasPorUnidad, $estado);
            }
            RegistroAuditoria::anotar('grupo.crear', $grupo, ['reservas' => count($unidades)], $propiedad->id);

            return $grupo;
        });
    }

    public function agregarHabitacion(Grupo $grupo, int $unidadId, CarbonImmutable $llegada, CarbonImmutable $salida, int $personas): Reserva
    {
        return DB::transaction(function () use ($grupo, $unidadId, $llegada, $salida, $personas): Reserva {
            $estado = $grupo->estado === EstadoGrupo::Tentativo ? EstadoReserva::SinConfirmar : EstadoReserva::Confirmada;
            $reserva = $this->crearHabitacion($grupo, $unidadId, $llegada, $salida, $personas, $estado);
            $this->aplicarPrecios($grupo);
            RegistroAuditoria::anotar('grupo.agregar_habitacion', $grupo, ['reserva' => $reserva->id], $grupo->propiedad_id);

            return $reserva;
        });
    }

    /**
     * Otra unidad u otras fechas, solo si las nuevas están libres: si no, la reserva se queda como estaba.
     */
    public function cambiarHabitacion(Reserva $reserva, int $unidadId, CarbonImmutable $llegada, CarbonImmutable $salida): void
    {
        DB::transaction(function () use ($reserva, $unidadId, $llegada, $salida): void {
            $this->reservas->cambiarEstancia($reserva, $unidadId, $llegada, $salida);
            if ($reserva->grupo !== null) {
                $this->aplicarPrecios($reserva->grupo);
            }
        });
    }

    public function cancelarHabitacion(Reserva $reserva): void
    {
        DB::transaction(function () use ($reserva): void {
            $this->reservas->cancelar($reserva);
            if ($reserva->grupo !== null) {
                $this->aplicarPrecios($reserva->grupo);
            }
        });
    }

    public function reactivarHabitacion(Reserva $reserva): void
    {
        DB::transaction(function () use ($reserva): void {
            $this->reservas->reactivar($reserva);
            if ($reserva->grupo?->estado === EstadoGrupo::Confirmado) {
                $this->reservas->confirmar($reserva->refresh());
            }
            if ($reserva->grupo !== null) {
                $this->aplicarPrecios($reserva->grupo);
            }
        });
    }

    /**
     * De tentativo a confirmado, o cancelar todo. Una vez cancelado se copia a otras fechas, no se revive: sus noches ya pudieron venderse.
     */
    public function cambiarEstado(Grupo $grupo, EstadoGrupo $estado): void
    {
        DB::transaction(function () use ($grupo, $estado): void {
            foreach ($grupo->reservas()->where('estado', '!=', EstadoReserva::Cancelada->value)->get() as $reserva) {
                /** @var Reserva $reserva */
                match ($estado) {
                    EstadoGrupo::Cancelado => $this->reservas->cancelar($reserva),
                    EstadoGrupo::Confirmado => $this->reservas->confirmar($reserva),
                    EstadoGrupo::Tentativo => null,
                };
            }
            $grupo->update(['estado' => $estado]);
            RegistroAuditoria::anotar('grupo.estado', $grupo, ['estado' => $estado->value], $grupo->propiedad_id);
        });
    }

    /**
     * El mismo grupo en otras fechas: mismas unidades, noches y precios, sin pagos ni lista. Todas las habitaciones o ninguna.
     */
    public function copiar(Grupo $grupo, CarbonImmutable $llegada, string $nombre): Grupo
    {
        return DB::transaction(function () use ($grupo, $llegada, $nombre): Grupo {
            $inicio = $grupo->primeraLlegada();
            $copia = Grupo::query()->create([
                'propiedad_id' => $grupo->propiedad_id,
                'codigo' => 'GRP-'.Huellas::codigoPublico(6),
                'nombre' => $nombre,
                'estado' => EstadoGrupo::Tentativo,
                'color' => $grupo->color,
                'coordinador_id' => $grupo->coordinador_id,
                'modo_precio' => $grupo->modo_precio,
                'precio_total' => $grupo->precio_total,
                'precios_capacidad' => $grupo->precios_capacidad,
                'gratis_cada' => $grupo->gratis_cada,
                'descuento_porcentaje' => $grupo->descuento_porcentaje,
                'extras' => $grupo->extras,
                'dias_acceso_tras_salida' => $grupo->dias_acceso_tras_salida,
            ])->refresh();
            foreach ($grupo->reservas()->with('habitacionGrupo')->where('estado', '!=', EstadoReserva::Cancelada->value)->orderBy('id')->get() as $reserva) {
                /** @var Reserva $reserva */
                $desfase = (int) $inicio?->diffInDays($reserva->llegada);
                $nueva = $this->crearHabitacion($copia, $reserva->unidad_id, $llegada->addDays($desfase), $llegada->addDays($desfase + $reserva->noches), $reserva->personas, EstadoReserva::SinConfirmar);
                $nueva->habitacionGrupo()->update(['precio_noche' => $reserva->habitacionGrupo?->precio_noche]);
            }
            $this->aplicarPrecios($copia);
            RegistroAuditoria::anotar('grupo.copiar', $copia, ['desde' => $grupo->id], $grupo->propiedad_id);

            return $copia;
        });
    }

    /**
     * Pasada la fecha límite, las habitaciones sin responsable vuelven a venderse, si así se pactó.
     *
     * @return int cuántas se liberaron
     */
    public function liberarSinResponsable(Grupo $grupo): int
    {
        $liberadas = 0;
        foreach ($grupo->reservas()->where('estado', '!=', EstadoReserva::Cancelada->value)->with('habitacionGrupo')->get() as $reserva) {
            /** @var Reserva $reserva */
            if (blank($reserva->habitacionGrupo?->responsable) && ! $reserva->estaEnCasa()) {
                $this->cancelarHabitacion($reserva);
                $liberadas++;
            }
        }
        if ($liberadas > 0) {
            RegistroAuditoria::anotar('grupo.liberar', $grupo, ['habitaciones' => $liberadas], $grupo->propiedad_id);
        }

        return $liberadas;
    }

    /**
     * @param  array{responsable?: string|null, telefono?: string|null, correo?: string|null, acompanantes?: string|null, personas?: int|null}  $datos
     */
    public function guardarHabitacion(Reserva $reserva, array $datos): void
    {
        DB::transaction(function () use ($reserva, $datos): void {
            if (($datos['personas'] ?? null) !== null) {
                $this->reservas->cambiarPersonas($reserva, (int) $datos['personas']);
            }
            GrupoHabitacion::query()->updateOrCreate(['reserva_id' => $reserva->id], [
                'grupo_id' => $reserva->grupo_id,
                'responsable' => self::texto($datos['responsable'] ?? null),
                'telefono' => self::texto($datos['telefono'] ?? null),
                'correo' => self::texto($datos['correo'] ?? null),
                'acompanantes' => self::texto($datos['acompanantes'] ?? null),
            ]);
        });
    }

    /**
     * @return array{total: int, pagado: int, pendiente: int, por_confirmar: int, moneda: string}
     */
    public function saldo(Grupo $grupo): array
    {
        $vigentes = $grupo->reservas()->where('estado', '!=', EstadoReserva::Cancelada->value);

        return [
            'total' => (int) (clone $vigentes)->sum('total'),
            'pagado' => (int) (clone $vigentes)->sum('pagado'),
            'pendiente' => (int) (clone $vigentes)->sum('pendiente'),
            'por_confirmar' => (int) $grupo->comprobantes()->where('estado', EstadoComprobante::PorConfirmar->value)->sum('importe'),
            'moneda' => $grupo->propiedad->moneda,
        ];
    }

    /**
     * @return array{lineas: array<int, array{base: int, descuento: int, gratis: bool, extras: int, total: int}>, subtotal: int, descuento: int, extras: int, total: int}
     */
    public function calculo(Grupo $grupo): array
    {
        $habitaciones = [];
        foreach ($grupo->reservas()->with(['unidad', 'habitacionGrupo'])->where('estado', '!=', EstadoReserva::Cancelada->value)->orderBy('id')->get() as $reserva) {
            /** @var Reserva $reserva */
            $habitaciones[] = [
                'id' => $reserva->id,
                'noches' => $reserva->noches,
                'capacidad' => $reserva->unidad->capacidad,
                'precio_noche' => $reserva->habitacionGrupo?->precio_noche,
                'base_tarifa' => $grupo->modo_precio === ModoPrecioGrupo::Tarifa ? Cotizador::presupuesto($reserva->unidad, $reserva->llegada, $reserva->salida, $reserva->personas)->total() : 0,
            ];
        }

        return CalculadorPrecio::calcular($grupo->modo_precio, $grupo->precio_total, (array) $grupo->precios_capacidad, $habitaciones, $grupo->gratis_cada, (float) $grupo->descuento_porcentaje, $grupo->extras);
    }

    /**
     * Pone a cada reserva lo que le toca. Con la tarifa normal y sin nada que la cambie no se toca nada, porque recepción pudo haber puesto un precio a
     * mano; $forzar es para cuando alguien acaba de cambiar los precios del grupo y espera verlos.
     */
    public function aplicarPrecios(Grupo $grupo, bool $forzar = false): void
    {
        $sinPrecioPropio = $grupo->modo_precio === ModoPrecioGrupo::Tarifa && (int) $grupo->gratis_cada < 2 && (float) $grupo->descuento_porcentaje === 0.0 && $grupo->extras === 0
            && ! GrupoHabitacion::query()->where('grupo_id', $grupo->id)->whereNotNull('precio_noche')->exists();
        if ($sinPrecioPropio && ! $forzar) {
            return;
        }
        $calculo = $this->calculo($grupo);
        DB::transaction(function () use ($grupo, $calculo): void {
            foreach ($grupo->reservas()->whereIn('id', array_keys($calculo['lineas']))->get() as $reserva) {
                /** @var Reserva $reserva */
                $this->reservas->cambiarTotal($reserva, $calculo['lineas'][$reserva->id]['total']);
            }
        });
    }

    /**
     * Anticipo y el resto en cuotas iguales hasta una fecha. Sustituye el calendario anterior.
     */
    public function generarCuotas(Grupo $grupo, int $anticipo, ?CarbonImmutable $fechaAnticipo, int $cuotas, CarbonImmutable $hasta): void
    {
        $total = $this->saldo($grupo)['total'];
        $idioma = $grupo->propiedad->idioma;
        // Solo la fecha: lo que venga con la zona de la propiedad no debe mover los días.
        $inicio = CarbonImmutable::parse(($fechaAnticipo ?? $grupo->propiedad->hoy())->format('Y-m-d'), 'UTC');
        $fin = CarbonImmutable::parse($hasta->format('Y-m-d'), 'UTC');
        DB::transaction(function () use ($grupo, $total, $anticipo, $cuotas, $idioma, $inicio, $fin): void {
            $grupo->cuotas()->delete();
            $anticipo = min($anticipo, $total);
            if ($anticipo > 0) {
                CuotaGrupo::query()->create(['grupo_id' => $grupo->id, 'concepto' => __('grupos.cuotas.anticipo', [], $idioma), 'importe' => $anticipo, 'vence_en' => $inicio]);
            }
            $resto = $total - $anticipo;
            $cuotas = max(1, $cuotas);
            if ($resto > 0) {
                $partes = Dinero::repartir($resto, $cuotas);
                $dias = max(0, (int) $inicio->diffInDays($fin, false));
                foreach ($partes as $i => $parte) {
                    CuotaGrupo::query()->create([
                        'grupo_id' => $grupo->id,
                        'concepto' => __('grupos.cuotas.numero', ['n' => $i + 1, 'de' => $cuotas], $idioma),
                        'importe' => $parte,
                        'vence_en' => $inicio->addDays((int) round($dias * ($i + 1) / $cuotas)),
                    ]);
                }
            }
            RegistroAuditoria::anotar('grupo.cuotas', $grupo, ['cuotas' => $grupo->cuotas()->count()], $grupo->propiedad_id);
        });
    }

    /**
     * Reparte un cobro entre las reservas con saldo, de la primera a la última; lo que sobre va a
     * la última como saldo a favor.
     */
    public function cobrar(Grupo $grupo, int $importe, MetodoPago $metodo, CarbonImmutable $fecha, ?string $referencia = null, ?int $cajaId = null): void
    {
        if ($importe <= 0) {
            throw new ReservaRechazada(__('ui.pagos.error_importe'), 'importe');
        }
        DB::transaction(function () use ($grupo, $importe, $metodo, $fecha, $referencia, $cajaId): void {
            $reservas = $grupo->reservas()->where('estado', '!=', EstadoReserva::Cancelada->value)->orderBy('id')->get();
            if ($reservas->isEmpty()) {
                throw new ReservaRechazada(__('ui.grupos.error_sin_reservas'), 'importe');
            }
            $restante = $importe;
            foreach ($reservas as $indice => $reserva) {
                /** @var Reserva $reserva */
                $parte = $indice === $reservas->count() - 1 ? $restante : min($restante, max(0, $reserva->pendiente));
                if ($parte > 0) {
                    $this->reservas->registrarPago($reserva, $parte, $metodo, $fecha, $referencia, $cajaId);
                    $restante -= $parte;
                }
                if ($restante === 0) {
                    break;
                }
            }
        });
    }

    private function crearHabitacion(Grupo $grupo, int $unidadId, CarbonImmutable $llegada, CarbonImmutable $salida, int $personasPorUnidad, EstadoReserva $estado): Reserva
    {
        $unidad = Unidad::query()->where('propiedad_id', $grupo->propiedad_id)->findOrFail($unidadId);
        $personas = $unidad->capacidad > 0 ? min($unidad->capacidad, $personasPorUnidad) : $personasPorUnidad;
        $presupuesto = Cotizador::presupuesto($unidad, $llegada, $salida, $personas);
        try {
            $reserva = $this->reservas->crear($grupo->propiedad, [
                'unidad_id' => $unidad->id,
                'titular_id' => $grupo->coordinador_id,
                'llegada' => $llegada->format('Y-m-d'),
                'salida' => $salida->format('Y-m-d'),
                'personas' => $personas,
                'estado' => $estado->value,
                'total' => $presupuesto->total(),
                'origen_texto' => __('ui.grupos.origen', ['nombre' => $grupo->nombre]),
                'grupo_id' => $grupo->id,
                'cargos' => [],
            ]);
        } catch (ReservaRechazada $e) {
            throw new ReservaRechazada(__('ui.grupos.error_unidad', ['unidad' => $unidad->nombre, 'motivo' => $e->getMessage()]), 'unidades');
        }
        GrupoHabitacion::query()->create(['grupo_id' => $grupo->id, 'reserva_id' => $reserva->id]);

        return $reserva;
    }

    private static function texto(?string $valor): ?string
    {
        $valor = trim((string) $valor);

        return $valor === '' ? null : $valor;
    }
}
