<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Camping;

use App\Enums\EstadoReserva;
use App\Enums\ServicioMedido;
use App\Enums\TipoReserva;
use App\Models\CuotaReserva;
use App\Models\Lectura;
use App\Models\Propiedad;
use App\Models\RegistroAuditoria;
use App\Models\Reserva;
use App\Models\Unidad;
use App\Modulos\Modulo;
use App\Modulos\Modulos;
use App\Reservas\GestorReservas;
use App\Support\Dinero;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Lo que marcan los medidores y lo que se cobra por ello (D-111). Se cobra a quien está en el lugar, y nunca lo que gastó el de antes:
 * la primera lectura de cada huésped solo es su punto de partida.
 */
final class GestorLecturas
{
    private const LECTURA_MAXIMA = 10_000_000_000;

    public function __construct(private readonly GestorReservas $reservas)
    {
    }

    public function ultima(int $unidadId, ServicioMedido $servicio): ?Lectura
    {
        return Lectura::query()->where('unidad_id', $unidadId)->where('servicio', $servicio->value)->orderByDesc('leida_en')->orderByDesc('id')->first();
    }

    /**
     * La última de cada medidor, de una vez: la pantalla se vuelve a pintar con cada tecla. No se captura una lectura anterior a la última, así que la última es la de mayor id.
     *
     * @return Collection<int, Lectura> por id de unidad
     */
    public function ultimas(Propiedad $propiedad, ServicioMedido $servicio): Collection
    {
        $ids = Lectura::query()->where('propiedad_id', $propiedad->id)->where('servicio', $servicio->value)->selectRaw('MAX(id)')->groupBy('unidad_id');

        return Lectura::query()->whereIn('id', $ids)->get()->keyBy('unidad_id');
    }

    /**
     * Quién responde por cada lugar ese día.
     *
     * @return Collection<int, Reserva> por id de unidad
     */
    public function reservasDe(Propiedad $propiedad, CarbonImmutable $fecha): Collection
    {
        return $this->estancias($fecha)->with('titular')->where('propiedad_id', $propiedad->id)->get()->unique('unidad_id')->keyBy('unidad_id');
    }

    /**
     * @return Builder<Reserva>
     */
    private function estancias(CarbonImmutable $fecha): Builder
    {
        return Reserva::query()->where('tipo', TipoReserva::Reserva->value)->where('estado', '!=', EstadoReserva::Cancelada->value)
            ->where('sincronizada', false)->where('llegada', '<=', $fecha->format('Y-m-d'))->where('salida', '>=', $fecha->format('Y-m-d'))->orderBy('llegada');
    }

    /**
     * Quién responde por lo que marque ese día. El de la salida también: su última lectura se toma al irse.
     */
    public function reservaDe(int $unidadId, CarbonImmutable $fecha): ?Reserva
    {
        return $this->estancias($fecha)->where('unidad_id', $unidadId)->first();
    }

    /**
     * Si esta lectura se le puede cobrar: tiene que haber otra antes, tomada desde que llegó.
     */
    public function cobrable(?Lectura $anterior, ?Reserva $reserva, bool $inicial = false): bool
    {
        return ! $inicial && $anterior !== null && $reserva !== null && $anterior->leida_en->format('Y-m-d') >= $reserva->llegada->format('Y-m-d');
    }

    /**
     * Todas o ninguna: si una está mal, se corrige y se vuelven a guardar.
     *
     * @param  array<int, array{actual?: mixed, inicial?: mixed}>  $capturas  por id de unidad
     * @return int lecturas guardadas
     */
    public function registrar(Propiedad $propiedad, ServicioMedido $servicio, CarbonImmutable $fecha, array $capturas): int
    {
        if ($fecha->format('Y-m-d') > $propiedad->hoy()->format('Y-m-d')) {
            throw new ErrorLectura(__('camping.lecturas.error_futura'));
        }
        $tarifa = (new AjustesCamping($propiedad))->tarifa($servicio);

        return DB::transaction(function () use ($propiedad, $servicio, $fecha, $capturas, $tarifa): int {
            $unidades = Unidad::query()->where('propiedad_id', $propiedad->id)->whereKey(array_keys($capturas))->orderBy('id')->lockForUpdate()->get();
            $guardadas = 0;
            foreach ($unidades as $unidad) {
                $captura = $capturas[$unidad->id];
                $texto = trim((string) ($captura['actual'] ?? ''));
                if ($texto === '') {
                    continue;
                }
                if (! is_numeric($texto) || (float) $texto < 0 || (float) $texto >= self::LECTURA_MAXIMA) {
                    throw new ErrorLectura(__('camping.lecturas.error_numero', ['unidad' => $unidad->nombre]));
                }
                $this->guardar($propiedad, $unidad, $servicio, $fecha, (float) $texto, ! empty($captura['inicial']), $tarifa);
                $guardadas++;
            }

            return $guardadas;
        });
    }

    private function guardar(Propiedad $propiedad, Unidad $unidad, ServicioMedido $servicio, CarbonImmutable $fecha, float $actual, bool $inicial, ?string $tarifa): void
    {
        $anterior = $this->ultima($unidad->id, $servicio);
        if ($anterior !== null && $anterior->leida_en->format('Y-m-d') > $fecha->format('Y-m-d')) {
            throw new ErrorLectura(__('camping.lecturas.error_fecha', ['unidad' => $unidad->nombre]));
        }
        if ($anterior !== null && ! $inicial && $actual < (float) $anterior->actual) {
            throw new ErrorLectura(__('camping.lecturas.error_menor', ['unidad' => $unidad->nombre, 'anterior' => (float) $anterior->actual]));
        }
        $reserva = $this->reservaDe($unidad->id, $fecha);
        $cobrable = $this->cobrable($anterior, $reserva, $inicial);
        $consumo = $anterior === null || $inicial ? 0.0 : round($actual - (float) $anterior->actual, 2);
        // El precio se toma al cobrar. Sin precio, se anota lo que marcó y no se cobra: nadie paga un cero que parezca cuenta.
        $importe = $cobrable && $tarifa !== null ? Dinero::aUnidadMenor(number_format($consumo * (float) $tarifa, 4, '.', ''), $propiedad->moneda) : 0;

        $lectura = Lectura::query()->create([
            'propiedad_id' => $propiedad->id,
            'unidad_id' => $unidad->id,
            'reserva_id' => $cobrable ? $reserva?->id : null,
            'servicio' => $servicio,
            'leida_en' => $fecha->format('Y-m-d'),
            'actual' => $actual,
            'consumo' => $consumo,
            'tarifa' => $cobrable ? $tarifa : null,
            'importe' => $importe,
            'inicial' => $inicial || $anterior === null,
            'creado_por' => auth()->id(),
        ]);
        if ($importe > 0 && $reserva !== null) {
            $this->cobrar($lectura, $reserva, $anterior, $propiedad);
        }
        RegistroAuditoria::anotar('lectura.crear', $lectura, ['servicio' => $servicio->value, 'consumo' => $consumo, 'importe' => $importe], $propiedad->id);
    }

    private function cobrar(Lectura $lectura, Reserva $reserva, ?Lectura $anterior, Propiedad $propiedad): void
    {
        $idioma = $propiedad->idioma;
        $nombre = __('camping.lecturas.cargo', [
            'servicio' => __('enums.ServicioMedido.'.$lectura->servicio->value, [], $idioma),
            'consumo' => (float) $lectura->consumo,
            'medida' => $lectura->servicio->medida(),
            'desde' => (string) $anterior?->leida_en->locale($idioma)->isoFormat('ll'),
            'hasta' => $lectura->leida_en->locale($idioma)->isoFormat('ll'),
        ], $idioma);
        $cargo = $this->reservas->agregarCargo($reserva, $nombre, (float) $lectura->consumo, $lectura->importe);
        $lectura->update(['cargo_id' => $cargo->id]);
        // Con calendario de cobros, un pago aparte: se cobra tal cual, y el calendario sigue sumando el total.
        if (Modulos::activo(Modulo::EstanciasLargas) && $reserva->cuotas()->exists()) {
            $reserva->cuotas()->create(['cargo_id' => $cargo->id, 'concepto' => $cargo->nombre, 'importe' => $lectura->importe, 'vence_en' => $lectura->leida_en->format('Y-m-d')]);
        }
    }

    /**
     * Solo la última de cada medidor: las demás ya son el punto de partida de otra. Lo cobrado se devuelve al total.
     */
    public function quitar(Lectura $lectura): void
    {
        DB::transaction(function () use ($lectura): void {
            $actual = Lectura::query()->lockForUpdate()->findOrFail($lectura->id);
            if ($this->ultima($actual->unidad_id, $actual->servicio)?->id !== $actual->id) {
                throw new ErrorLectura(__('camping.lecturas.error_no_ultima'));
            }
            if ($actual->cargo !== null) {
                CuotaReserva::query()->where('cargo_id', $actual->cargo->id)->first()?->delete();
                $this->reservas->quitarCargo($actual->cargo);
            }
            RegistroAuditoria::anotar('lectura.quitar', $actual, ['servicio' => $actual->servicio->value, 'importe' => $actual->importe], $actual->propiedad_id);
            $actual->delete();
        });
    }
}
