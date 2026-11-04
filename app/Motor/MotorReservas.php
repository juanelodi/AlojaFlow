<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Motor;

use App\Enums\EstadoReserva;
use App\Enums\TipoContacto;
use App\Models\ConceptoCargo;
use App\Models\ContactoHuesped;
use App\Models\Huesped;
use App\Models\Propiedad;
use App\Models\Reserva;
use App\Models\Tarifa;
use App\Models\Unidad;
use App\Reservas\Cotizador;
use App\Reservas\Cupones;
use App\Reservas\GestorReservas;
use App\Reservas\ReservaRechazada;
use App\Servicios\Disponibilidad;
use App\Support\Contactos;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Reservas directas desde el sitio del alojamiento (D-042). El huésped ve tarifas con unidades
 * libres; la unidad concreta la asigna el sistema, según el orden que eligió el alojamiento.
 */
final class MotorReservas
{
    public function __construct(private readonly GestorReservas $reservas)
    {
    }

    /**
     * @return list<Oferta>
     */
    public function ofertas(Propiedad $propiedad, CarbonImmutable $llegada, CarbonImmutable $salida, int $personas): array
    {
        $ofertas = [];
        $libres = Disponibilidad::libres([$propiedad->id], $llegada->format('Y-m-d'), $salida->format('Y-m-d'), $personas)
            ->filter(fn (Unidad $u): bool => $u->tarifa_id !== null)
            ->groupBy('tarifa_id');

        foreach (Tarifa::query()->where('propiedad_id', $propiedad->id)->where('activa', true)->where('visible_motor', true)->orderBy('precio_noche')->get() as $tarifa) {
            $unidades = $libres->get($tarifa->id);
            if ($unidades === null || $unidades->isEmpty()) {
                continue;
            }
            /** @var Unidad $primera */
            $primera = $unidades->first();
            $presupuesto = Cotizador::presupuesto($primera, $llegada, $salida, $personas);
            if ($presupuesto->cotizacion === null) {
                continue;
            }
            $ofertas[] = new Oferta(
                $tarifa,
                $unidades->count(),
                (int) $unidades->max('capacidad'),
                $presupuesto,
                $presupuesto->cotizacion->estanciaMinima,
                $presupuesto->cotizacion->cumpleEstanciaMinima(),
            );
        }

        return $ofertas;
    }

    /**
     * Cargos opcionales que el huésped puede añadir.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, ConceptoCargo>
     */
    public function cargosOpcionales(Propiedad $propiedad): \Illuminate\Database\Eloquent\Collection
    {
        return ConceptoCargo::query()->where('propiedad_id', $propiedad->id)->where('activo', true)
            ->where('obligatorio', false)->where('visible_motor', true)->orderBy('orden')->orderBy('nombre')->get();
    }

    /**
     * Crea la reserva. Si otra petición se lleva la unidad en el último momento, prueba la siguiente.
     *
     * @param  array{tarifa_id: int, llegada: string, salida: string, personas: int, nombre: string, apellido: string|null, correo: string, telefono: string|null, comentario: string|null, cupon: string|null, cargos: list<int>, idioma: string}  $datos
     */
    public function reservar(Propiedad $propiedad, array $datos): Reserva
    {
        $ajustes = new AjustesMotor($propiedad);
        if (! $ajustes->activo()) {
            throw new ReservaRechazada(__('motor.error.cerrado'));
        }
        $llegada = CarbonImmutable::parse($datos['llegada'])->startOfDay();
        $salida = CarbonImmutable::parse($datos['salida'])->startOfDay();
        if ($llegada->format('Y-m-d') < $propiedad->hoy()->addDays($ajustes->diasMinimosAntelacion())->format('Y-m-d')) {
            throw new ReservaRechazada(__('motor.error.fecha_pasada'));
        }

        $tarifa = Tarifa::query()->where('propiedad_id', $propiedad->id)->where('activa', true)->where('visible_motor', true)->find($datos['tarifa_id']);
        if ($tarifa === null) {
            throw new ReservaRechazada(__('motor.error.sin_lugar'));
        }
        $candidatas = Disponibilidad::libres([$propiedad->id], $llegada->format('Y-m-d'), $salida->format('Y-m-d'), $datos['personas'])
            ->filter(fn (Unidad $u): bool => $u->tarifa_id === $tarifa->id);
        if ($candidatas->isEmpty()) {
            throw new ReservaRechazada(__('motor.error.sin_lugar'));
        }

        $cupon = null;
        if (filled($datos['cupon'])) {
            $cupon = Cupones::buscar($propiedad->id, $datos['cupon']);
            $motivo = $cupon === null ? __('ui.cupones.error_no_existe') : Cupones::motivoRechazo($cupon, $llegada, (int) $llegada->diffInDays($salida));
            if ($motivo !== null) {
                throw new ReservaRechazada($motivo, 'cupon');
            }
        }

        $permitidos = $this->cargosOpcionales($propiedad)->pluck('id')->all();
        $cargos = array_values(array_intersect($datos['cargos'], $permitidos));
        $elegidos = array_fill_keys($cargos, 1.0);

        return DB::transaction(function () use ($propiedad, $ajustes, $datos, $candidatas, $llegada, $salida, $cupon, $cargos, $elegidos): Reserva {
            $huesped = $this->huesped($propiedad, $datos);
            /** @var Unidad $primera */
            $primera = $candidatas->first();
            $presupuesto = Cotizador::presupuesto($primera, $llegada, $salida, $datos['personas'], $elegidos, $cupon);
            if ($presupuesto->cotizacion === null || ! $presupuesto->cotizacion->cumpleEstanciaMinima()) {
                throw new ReservaRechazada(__('motor.error.estancia_minima', ['noches' => $presupuesto->cotizacion->estanciaMinima ?? 1]));
            }
            $total = $presupuesto->total();
            $anticipo = (int) round($total * $ajustes->anticipoPorcentaje() / 100);
            $pagaraEnLinea = $anticipo > 0 && (new Pasarelas($propiedad))->disponibles() !== [];
            $confirmada = ! $pagaraEnLinea && $ajustes->confirmacionInmediata();

            foreach ($candidatas as $unidad) {
                /** @var Unidad $unidad */
                try {
                    return $this->reservas->crear($propiedad, [
                        'unidad_id' => $unidad->id,
                        'titular_id' => $huesped->id,
                        'llegada' => $llegada->format('Y-m-d'),
                        'salida' => $salida->format('Y-m-d'),
                        'personas' => $datos['personas'],
                        'estado' => ($confirmada ? EstadoReserva::Confirmada : EstadoReserva::SinConfirmar)->value,
                        'total' => $total,
                        'deposito' => $anticipo,
                        'descuento' => $presupuesto->descuento,
                        'origen_texto' => __('motor.origen'),
                        'comentario' => $datos['comentario'],
                        'cupon_codigo' => $cupon->codigo ?? '',
                        'idioma' => $datos['idioma'],
                        // Se retiene solo mientras se paga: si no paga, las noches vuelven a venderse.
                        'expira_en' => $pagaraEnLinea ? now('UTC')->addMinutes($ajustes->minutosRetencion()) : null,
                        'cargos' => array_map(fn (int $id): array => ['concepto_cargo_id' => $id, 'cantidad' => 1], $cargos),
                    ]);
                } catch (ReservaRechazada $e) {
                    if ($e->campo !== 'unidad_id') {
                        throw $e;
                    }
                }
            }

            throw new ReservaRechazada(__('motor.error.sin_lugar'));
        });
    }

    /**
     * Si el correo ya está en una ficha de la propiedad, se usa esa: no se duplica ni se cambia. Un
     * huésped no debe poder sobrescribir la ficha de otro desde una página pública.
     *
     * @param  array<string, mixed>  $datos
     */
    private function huesped(Propiedad $propiedad, array $datos): Huesped
    {
        $correo = Contactos::normalizar(TipoContacto::Correo->value, (string) $datos['correo']);
        $contacto = ContactoHuesped::query()->where('valor_normalizado', $correo)
            ->whereHas('huesped', fn ($q) => $q->where('propiedad_id', $propiedad->id)->where('sincronizada', false))->first();
        if ($contacto !== null) {
            return $contacto->huesped;
        }

        $huesped = Huesped::query()->create([
            'propiedad_id' => $propiedad->id,
            'nombre' => $datos['nombre'],
            'apellido1' => $datos['apellido'] ?: null,
            'idioma' => $datos['idioma'],
        ]);
        $huesped->contactos()->create(['tipo' => TipoContacto::Correo, 'valor' => (string) $datos['correo'], 'valor_normalizado' => $correo, 'orden' => 0]);
        if (filled($datos['telefono'])) {
            $huesped->contactos()->create(['tipo' => TipoContacto::Telefono, 'valor' => (string) $datos['telefono'], 'valor_normalizado' => Contactos::normalizar(TipoContacto::Telefono->value, (string) $datos['telefono']), 'orden' => 1]);
        }

        return $huesped;
    }
}
