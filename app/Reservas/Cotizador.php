<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Reservas;

use App\Enums\BaseCargo;
use App\Enums\ModoTarifa;
use App\Models\Canal;
use App\Models\ConceptoCargo;
use App\Models\Cupon;
use App\Models\Impuesto;
use App\Models\Reserva;
use App\Models\Tarifa;
use App\Models\TemporadaTarifa;
use App\Models\Unidad;
use App\Modulos\Modulo;
use App\Modulos\Modulos;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * Calcula precios (D-036, D-041). Cada tarifa elige si cobra por noche con persona extra o por
 * persona. Cada noche usa el precio de la temporada que la cubre o, si no hay, el de la tarifa; las
 * noches de viernes y sábado usan el de fin de semana si existe. Si dos temporadas se pisan, vale la
 * que empieza más tarde. Una estancia larga usa el precio por semana o por mes de la tarifa o de su
 * temporada; una temporada que no los tiene cobra su precio normal, para no regalar la temporada alta (D-107).
 * Una unidad puede venderse con varias tarifas (D-118), y una tarifa puede salir de otra, con un ajuste (D-119).
 */
final class Cotizador
{
    private const ORDEN_LARGOS = [Mensualidades::SEMANA => 1, Mensualidades::MES => 2, Cotizacion::TEMPORADA => 3];

    public static function cotizar(Unidad $unidad, CarbonImmutable $llegada, CarbonImmutable $salida, int $personas = 1, ?int $tarifaId = null): ?Cotizacion
    {
        // Las temporadas son fechas sin zona: una llegada con la zona de la propiedad caería en el día de al lado.
        $llegada = CarbonImmutable::parse($llegada->format('Y-m-d'));
        $salida = CarbonImmutable::parse($salida->format('Y-m-d'));
        $tarifa = self::tarifaDe($unidad, $tarifaId);
        // El recorrido es por noche: sin este tope, unas fechas absurdas ocupan el servidor.
        if ($tarifa === null || $salida->lte($llegada) || $llegada->diffInDays($salida) > GestorReservas::NOCHES_MAXIMAS) {
            return null;
        }

        $precios = $tarifa->precios();
        $temporadas = TemporadaTarifa::query()->where('tarifa_id', $precios->id)
            ->where('desde', '<', $salida->format('Y-m-d'))
            ->where('hasta', '>=', $llegada->format('Y-m-d'))
            ->orderByDesc('desde')->orderByDesc('id')
            ->get();
        $personas = max(1, $personas);
        $nochesDeSuMes = self::nochesDeSuMes($llegada, $salida);

        $noches = [];
        $estanciaMinima = max($tarifa->estancia_minima, $precios->estancia_minima);
        $estanciaLarga = null;
        // Un mes entre 31 no da centavos justos: se redondea lo acumulado, para que la suma dé el precio del mes.
        $exacto = 0.0;
        $sumado = 0;
        for ($dia = $llegada, $i = 0; $dia->lt($salida); $dia = $dia->addDay(), $i++) {
            $temporada = $temporadas->first(fn (TemporadaTarifa $t): bool => $t->desde->lte($dia) && $t->hasta->gte($dia));
            if ($dia->equalTo($llegada) && $temporada?->estancia_minima !== null) {
                $estanciaMinima = max($estanciaMinima, $temporada->estancia_minima);
            }
            [$largo, $tipo] = self::precioDeTemporadaCompleta($temporada, $llegada, $salida) ?? self::precioLargo($temporada ?? $precios, count($nochesDeSuMes), $nochesDeSuMes[$i]);
            $estanciaLarga = (self::ORDEN_LARGOS[(string) $tipo] ?? 0) > (self::ORDEN_LARGOS[(string) $estanciaLarga] ?? 0) ? $tipo : $estanciaLarga;
            $exacto += $tarifa->conAjuste(self::precioNoche($precios, $temporada, $dia, $personas, $largo));
            $noches[] = (int) round($exacto) - $sumado;
            $sumado += $noches[$i];
        }

        return new Cotizacion($tarifa->nombre, array_sum($noches), $noches, max(1, $estanciaMinima), $estanciaLarga, $tarifa->id);
    }

    /**
     * Lo que cuesta una noche suelta con las personas incluidas, para el calendario de tarifas. Sin estancia larga: esa depende de cuánto se quede.
     *
     * @param  iterable<TemporadaTarifa>  $temporadas  de la tarifa de la que salen sus precios, de la más reciente a la más antigua
     * @return array{precio: int, temporada: ?TemporadaTarifa, estancia_minima: int}
     */
    public static function precioDeNoche(Tarifa $tarifa, iterable $temporadas, CarbonImmutable $dia): array
    {
        $precios = $tarifa->precios();
        $temporada = null;
        foreach ($temporadas as $candidata) {
            if ($candidata->desde->format('Y-m-d') <= $dia->format('Y-m-d') && $candidata->hasta->format('Y-m-d') >= $dia->format('Y-m-d')) {
                $temporada = $candidata;
                break;
            }
        }
        $personas = $precios->modo === ModoTarifa::PorPersona ? 1 : max(1, $precios->personas_incluidas);

        return [
            'precio' => (int) round($tarifa->conAjuste(self::precioNoche($precios, $temporada, $dia, $personas, null))),
            'temporada' => $temporada,
            'estancia_minima' => max(1, $tarifa->estancia_minima, $precios->estancia_minima, (int) $temporada?->estancia_minima),
        ];
    }

    /**
     * La que se pidió, si esa unidad la vende y sigue activa; si no, la principal.
     */
    private static function tarifaDe(Unidad $unidad, ?int $tarifaId): ?Tarifa
    {
        $ids = $unidad->tarifasIds();
        if ($ids === []) {
            return null;
        }
        $activas = Tarifa::query()->with('base')->where('activa', true)->whereIn('id', $ids)->get()->keyBy('id');

        return $activas->get((int) $tarifaId) ?? $activas->get($ids[0]) ?? $activas->first();
    }

    /**
     * Por cada noche, entre cuántas se reparte el precio de su mes: un mes completo cuesta lo mismo con 28 que con 31, y lo que sobra se cobra a treintavos.
     *
     * @return list<int>
     */
    private static function nochesDeSuMes(CarbonImmutable $llegada, CarbonImmutable $salida): array
    {
        $lista = [];
        foreach (Mensualidades::periodos($llegada, $salida, Mensualidades::MES) as $periodo) {
            $lista = [...$lista, ...array_fill(0, $periodo['noches'], $periodo['completo'] ? $periodo['noches'] : 30)];
        }

        return $lista;
    }

    /**
     * Quien se queda toda la temporada paga ese precio, repartido entre sus noches (D-112).
     *
     * @return array{0: float, 1: string}|null
     */
    private static function precioDeTemporadaCompleta(?TemporadaTarifa $temporada, CarbonImmutable $llegada, CarbonImmutable $salida): ?array
    {
        if ($temporada?->precio_completa === null || ! Modulos::activo(Modulo::Camping) || $llegada->gt($temporada->desde) || $salida->lte($temporada->hasta)) {
            return null;
        }

        return [$temporada->precio_completa / ((int) $temporada->desde->diffInDays($temporada->hasta) + 1), Cotizacion::TEMPORADA];
    }

    /**
     * @return array{0: float|null, 1: string|null} el precio base de la noche y de qué tipo es; sin módulo, sin precios o sin noches, ninguno
     */
    private static function precioLargo(Tarifa|TemporadaTarifa $fila, int $noches, int $nochesDeSuMes): array
    {
        if (! Modulos::activo(Modulo::EstanciasLargas)) {
            return [null, null];
        }
        if ($noches >= Cotizacion::NOCHES_MES && $fila->precio_mes !== null) {
            return [$fila->precio_mes / $nochesDeSuMes, Mensualidades::MES];
        }
        if ($noches >= Cotizacion::NOCHES_SEMANA && $fila->precio_semana !== null) {
            return [$fila->precio_semana / Cotizacion::NOCHES_SEMANA, Mensualidades::SEMANA];
        }

        return [null, null];
    }

    /**
     * El desglose completo: alojamiento, cupón, cargos obligatorios o elegidos e impuestos.
     *
     * @param  array<int, float>  $cargosElegidos  id del concepto => cantidad
     */
    public static function presupuesto(Unidad $unidad, CarbonImmutable $llegada, CarbonImmutable $salida, int $personas, array $cargosElegidos = [], ?Cupon $cupon = null, int $descuentoManual = 0, int $exentosPorEdad = 0, bool $canalCobraImpuestos = false, ?int $tarifaId = null): Presupuesto
    {
        $cotizacion = self::cotizar($unidad, $llegada, $salida, $personas, $tarifaId);
        $alojamiento = $cotizacion->total ?? 0;
        $noches = max(0, (int) $llegada->diffInDays($salida));
        $personas = max(1, $personas);

        $descuento = $descuentoManual;
        if ($cupon !== null) {
            $descuento += Cupones::descuento($cupon, $alojamiento);
        }
        $descuento = min($descuento, $alojamiento);

        $cargos = [];
        $conceptos = ConceptoCargo::query()->where('propiedad_id', $unidad->propiedad_id)->where('activo', true)->orderBy('orden')->orderBy('nombre')->get();
        foreach ($conceptos as $concepto) {
            $elegido = isset($cargosElegidos[$concepto->id]);
            if (! $elegido && ! $concepto->obligatorio) {
                continue;
            }
            $aplica = ReglasCargo::aplica($concepto, $unidad, $cotizacion?->tarifaId, $llegada, $salida, $personas);
            // Lo que se suma solo pasa por sus reglas; lo que alguien añadió a mano se cobra igual, por toda la estancia (D-121).
            if (! $elegido && ! $aplica) {
                continue;
            }
            $cantidad = (float) ($cargosElegidos[$concepto->id] ?? ($concepto->por_cama_extra ? ReglasCargo::camasExtra($unidad, $personas) : 1));
            $cargos[] = [
                'concepto_cargo_id' => $concepto->id,
                'nombre' => $concepto->nombre,
                'cantidad' => $cantidad,
                'importe' => (int) round($cantidad * self::importeSegunBase($concepto->base, $concepto->importe, $concepto->porcentaje, $alojamiento - $descuento, $aplica ? ReglasCargo::nochesQueCuentan($concepto, $llegada, $salida) : $noches, $personas)),
            ];
        }

        $base = $alojamiento - $descuento;
        $impuestos = [];
        foreach (self::impuestosAplicables($unidad->propiedad_id, $llegada, CarbonImmutable::now($unidad->propiedad->zona_horaria)->format('Y-m-d'), $noches) as $impuesto) {
            $impuestos[] = [
                'nombre' => $impuesto->nombre,
                'importe' => self::importeImpuesto($impuesto, $base, $noches, $personas, $exentosPorEdad, $llegada),
                'incluido' => $impuesto->incluido_en_precio,
                'canal' => $canalCobraImpuestos && ! $impuesto->incluido_en_precio,
            ];
        }

        return new Presupuesto($cotizacion, $alojamiento, $descuento, $cargos, $impuestos);
    }

    private static function precioNoche(Tarifa $tarifa, ?TemporadaTarifa $temporada, CarbonImmutable $dia, int $personas, ?float $largo): float
    {
        $base = $largo ?? $temporada->precio_noche ?? $tarifa->precio_noche;
        // Viernes y sábado: son las noches que se venden como fin de semana.
        if ($largo === null && in_array($dia->dayOfWeek, [5, 6], true)) {
            $base = $temporada !== null
                ? ($temporada->precio_fin_semana ?? $temporada->precio_noche)
                : ($tarifa->precio_fin_semana ?? $tarifa->precio_noche);
        }
        if ($tarifa->modo === ModoTarifa::PorPersona) {
            return $base * $personas;
        }
        $extra = $temporada->precio_persona_extra ?? $tarifa->precio_persona_extra;

        return $base + max(0, $personas - $tarifa->personas_incluidas) * $extra;
    }

    private static function importeSegunBase(BaseCargo $base, int $importe, ?string $porcentaje, int $alojamiento, float $noches, int $personas): float
    {
        return match ($base) {
            BaseCargo::PorEstancia => $importe,
            BaseCargo::PorNoche => $importe * $noches,
            BaseCargo::PorPersona => $importe * $personas,
            BaseCargo::PorPersonaNoche => $importe * $personas * $noches,
            BaseCargo::PorcentajeAlojamiento => $alojamiento * (float) $porcentaje / 100,
        };
    }

    /**
     * Si el impuesto va incluido en un precio porcentual, se saca del precio («hacia atrás»): del
     * total con 19 % incluido, el impuesto es total − total / 1.19.
     */
    private static function importeImpuesto(Impuesto $impuesto, int $base, int $noches, int $personas, int $exentosPorEdad, CarbonImmutable $llegada): int
    {
        if ($impuesto->base === BaseCargo::PorcentajeAlojamiento) {
            $tasa = self::tasaEfectiva($impuesto, $noches);

            return (int) round($impuesto->incluido_en_precio ? $base - $base / (1 + $tasa) : $base * $tasa);
        }
        // Sin fechas de nacimiento en la reserva, la edad mínima se toma de cuántos menores se capturaron.
        $personasGravadas = $impuesto->edad_minima === null ? $personas : max(0, $personas - $exentosPorEdad);

        return (int) round(self::importeSegunBase($impuesto->base, (int) $impuesto->importe, null, $base, self::nochesConDescuento($impuesto, $llegada, $noches), $personasGravadas));
    }

    private static function nochesGravadas(Impuesto $impuesto, int $noches): int
    {
        return $impuesto->tope_noches === null ? $noches : min($noches, $impuesto->tope_noches);
    }

    /**
     * Las noches que paga, valiendo menos las de temporada baja y las de una estancia larga (D-101). Cada noche se mira por su fecha.
     */
    private static function nochesConDescuento(Impuesto $impuesto, CarbonImmutable $llegada, int $noches): float
    {
        $gravadas = self::nochesGravadas($impuesto, $noches);
        if ($impuesto->temporada_baja_descuento === null && $impuesto->dias_largos_desde === null) {
            return $gravadas;
        }
        $suma = 0.0;
        for ($i = 0; $i < $gravadas; $i++) {
            $factor = 1.0;
            if ($impuesto->enTemporadaBaja($llegada->addDays($i))) {
                $factor *= 1 - (float) $impuesto->temporada_baja_descuento / 100;
            }
            if ($impuesto->dias_largos_desde !== null && $i + 1 >= $impuesto->dias_largos_desde) {
                $factor *= 1 - (float) $impuesto->dias_largos_descuento / 100;
            }
            $suma += $factor;
        }

        return $suma;
    }

    /**
     * Con tope de noches, un porcentaje solo cae sobre esa parte de la estancia. Se reparte parejo: el precio de cada noche ya no se sabe si
     * alguien cambió el total a mano.
     */
    private static function tasaEfectiva(Impuesto $impuesto, int $noches): float
    {
        $tasa = (float) $impuesto->tasa / 100;

        return $impuesto->tope_noches !== null && $noches > $impuesto->tope_noches ? $tasa * $impuesto->tope_noches / $noches : $tasa;
    }

    /**
     * @return Collection<int, Impuesto>
     */
    private static function impuestosAplicables(int $propiedadId, CarbonImmutable $llegada, string $reservadaEn, int $noches): Collection
    {
        return Impuesto::query()->aplicables($propiedadId, $llegada->format('Y-m-d'), $reservadaEn)->orderBy('orden')->orderBy('nombre')->get()
            ->reject(fn (Impuesto $impuesto): bool => $impuesto->exentoPara($noches))->values();
    }

    /**
     * Lo que se cobró de impuestos en una reserva ya guardada, a partir de su total: si alguien lo cambió a mano, el impuesto lo sigue. Se
     * despeja lo que quedó de alojamiento y se calcula sobre eso, con las reglas de su fecha de llegada (D-082).
     *
     * @return list<array{impuesto_id: int, nombre: string, importe: int, incluido: bool, cobrado_por_canal: bool, unidades: int, unidades_exentas: int, importe_unitario: int|null}>
     */
    public static function impuestosDeReserva(Reserva $reserva): array
    {
        $noches = max(0, (int) $reserva->llegada->diffInDays($reserva->salida));
        $personas = max(1, $reserva->personas);
        $exentos = max(0, (int) $reserva->ninos);
        $canalCobra = $reserva->origen_texto !== null
            && Canal::query()->where('propiedad_id', $reserva->propiedad_id)->where('nombre', $reserva->origen_texto)->value('cobra_impuestos');
        $reservadaEn = CarbonImmutable::parse($reserva->creado_en ?? now('UTC'))->setTimezone($reserva->propiedad->zona_horaria)->format('Y-m-d');
        $llegada = CarbonImmutable::parse($reserva->llegada->format('Y-m-d'));
        $impuestos = self::impuestosAplicables($reserva->propiedad_id, $llegada, $reservadaEn, $noches);

        $sinCargos = $reserva->total - (int) $reserva->cargos()->sum('importe');
        $fijos = 0;
        $tasas = 0.0;
        foreach ($impuestos as $impuesto) {
            if ($impuesto->incluido_en_precio || $canalCobra) {
                continue;
            }
            if ($impuesto->base === BaseCargo::PorcentajeAlojamiento) {
                $tasas += self::tasaEfectiva($impuesto, $noches);
            } else {
                $fijos += self::importeImpuesto($impuesto, 0, $noches, $personas, $exentos, $llegada);
            }
        }
        $base = (int) round(max(0, $sinCargos - $fijos) / (1 + $tasas));

        return $impuestos->map(function (Impuesto $impuesto) use ($base, $noches, $personas, $exentos, $llegada, $canalCobra): array {
            $porPersonaNoche = $impuesto->base === BaseCargo::PorPersonaNoche;
            $gravadas = self::nochesGravadas($impuesto, $noches);
            $exentosAqui = $impuesto->edad_minima === null ? 0 : min($exentos, $personas);

            return [
                'impuesto_id' => $impuesto->id,
                'nombre' => $impuesto->nombre,
                'importe' => self::importeImpuesto($impuesto, $base, $noches, $personas, $exentos, $llegada),
                'incluido' => $impuesto->incluido_en_precio,
                'cobrado_por_canal' => $canalCobra && ! $impuesto->incluido_en_precio,
                // Lo que la declaración tiene que decir: cuántas unidades de estancia, y a cuánto.
                'unidades' => $porPersonaNoche ? $gravadas * max(0, $personas - $exentosAqui) : 0,
                'unidades_exentas' => $porPersonaNoche ? $gravadas * $exentosAqui : 0,
                'importe_unitario' => $impuesto->base === BaseCargo::PorcentajeAlojamiento ? null : (int) $impuesto->importe,
            ];
        })->values()->all();
    }
}
