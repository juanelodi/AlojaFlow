<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Motor;

use App\Camping\AtributosLugar;
use App\Camping\CupoExtras;
use App\Camping\FiltroLugares;
use App\Comunicacion\Mensajero;
use App\Dinero\GestorPoliticas;
use App\Enums\EstadoReserva;
use App\Enums\NivelAviso;
use App\Enums\TipoContacto;
use App\Models\Aviso;
use App\Models\ConceptoCargo;
use App\Models\ContactoHuesped;
use App\Models\Cupon;
use App\Models\Huesped;
use App\Models\Plano;
use App\Models\PlanoUnidad;
use App\Models\Propiedad;
use App\Models\Reserva;
use App\Models\Tarifa;
use App\Models\Unidad;
use App\Modulos\Modulo;
use App\Modulos\Modulos;
use App\Reservas\Cotizador;
use App\Reservas\Cupones;
use App\Reservas\GestorReservas;
use App\Reservas\Presupuesto;
use App\Reservas\ReglasCargo;
use App\Reservas\ReservaRechazada;
use App\Reservas\Restricciones;
use App\Servicios\Disponibilidad;
use App\Support\Contactos;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Reservas directas desde el sitio del alojamiento (D-042). El huésped ve tarifas con unidades
 * libres; la unidad concreta la asigna el sistema, según el orden que eligió el alojamiento. Una
 * misma unidad puede salir en varias tarifas (D-118).
 */
final class MotorReservas
{
    /** Hasta cuántos días adelante se reserva desde el sitio. */
    public const DIAS_HORIZONTE = 730;

    /** Reservas vigentes de un mismo correo: un huésped real rara vez tiene más. */
    private const VIGENTES_POR_CORREO = 3;

    /** Reservas nuevas por hora en una propiedad: más de esto en un hotel pequeño es abuso. */
    private const NUEVAS_POR_HORA = 30;

    public function __construct(private readonly GestorReservas $reservas)
    {
    }

    /**
     * Por qué no se pueden pedir esas fechas, o null si se puede. Va antes de calcular nada.
     */
    public function motivoFechas(Propiedad $propiedad, CarbonImmutable $llegada, CarbonImmutable $salida): ?string
    {
        if (! $this->rangoCotizable($propiedad, $llegada, $salida)) {
            return $salida->gt($llegada) && $llegada->diffInDays($salida) <= GestorReservas::NOCHES_MAXIMAS ? __('motor.error.fecha_lejana') : __('motor.error.fechas');
        }
        if ($llegada->format('Y-m-d') < $propiedad->hoy()->addDays((new AjustesMotor($propiedad))->diasMinimosAntelacion())->format('Y-m-d')) {
            return __('motor.error.fecha_pasada');
        }

        return Restricciones::deLaPropiedad($propiedad->id, $llegada, $salida, $propiedad->hoy())[0] ?? null;
    }

    public function rangoCotizable(Propiedad $propiedad, CarbonImmutable $llegada, CarbonImmutable $salida): bool
    {
        return $salida->gt($llegada)
            && $llegada->diffInDays($salida) <= GestorReservas::NOCHES_MAXIMAS
            && $llegada->format('Y-m-d') <= $propiedad->hoy()->addDays(self::DIAS_HORIZONTE)->format('Y-m-d');
    }

    /**
     * @return list<Oferta>
     */
    public function ofertas(Propiedad $propiedad, CarbonImmutable $llegada, CarbonImmutable $salida, int $personas, ?FiltroLugares $filtro = null, int $cantidad = 1): array
    {
        $ofertas = [];
        $reparto = self::reparto($personas, $cantidad);
        $libres = $this->libres($propiedad, $llegada, $salida, $reparto[0], $filtro);
        $ajustes = new AjustesMotor($propiedad);
        $compuestas = $cantidad > 1 ? self::compuestas($propiedad) : [];

        foreach (Tarifa::query()->with(['politica', 'base.politica'])->where('propiedad_id', $propiedad->id)->where('activa', true)->where('visible_motor', true)->get() as $tarifa) {
            $unidades = $libres->filter(fn (Unidad $u): bool => in_array($tarifa->id, $u->tarifasIds(), true) && ($cantidad === 1 || ! in_array($u->id, $compuestas, true)));
            if ($unidades->count() < $cantidad || ! $tarifa->admitePersonas($reparto[0])) {
                continue;
            }
            /** @var Unidad $primera */
            $primera = $unidades->first();
            // Una por unidad, con las personas que le tocan (D-158).
            $partes = array_map(fn (int $suyas): Presupuesto => Cotizador::presupuesto($primera, $llegada, $salida, $suyas, tarifaId: $tarifa->id), $reparto);
            if (array_filter($partes, fn (Presupuesto $parte): bool => $parte->cotizacion === null) !== []) {
                continue;
            }
            $presupuesto = count($partes) === 1 ? $partes[0] : Presupuesto::sumar($partes);
            $politica = $tarifa->politicaPropia();
            $ofertas[] = new Oferta(
                $tarifa,
                $unidades->count(),
                min((int) $unidades->max(fn (Unidad $u): int => $u->capacidadMaxima()), $tarifa->personas_maximas ?? PHP_INT_MAX),
                $presupuesto,
                $presupuesto->cotizacion->estanciaMinima,
                $presupuesto->cotizacion->cumpleEstanciaMinima(),
                Modulos::activo(Modulo::Camping) ? AtributosLugar::comunes($unidades) : [],
                $politica === null ? null : app(GestorPoliticas::class)->textoDe($politica),
                $politica?->nombre,
                $this->anticipo($tarifa, $ajustes),
                $primera,
                $cantidad,
            );
        }
        // La más barata primero. Lo de cada noche ya está calculado: el precio base no dice nada de una que sale de otra.
        usort($ofertas, fn (Oferta $a, Oferta $b): int => $a->presupuesto->total() <=> $b->presupuesto->total());

        return $ofertas;
    }

    /**
     * Las que se componen de otras, como la casa completa (D-155). En un pedido de varias unidades no entran: sus partes nunca
     * chocan entre sí, y la casa con un cuarto nunca cabe junta.
     *
     * @return list<int>
     */
    private static function compuestas(Propiedad $propiedad): array
    {
        return Unidad::query()->where('propiedad_id', $propiedad->id)->whereHas('partes')->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();
    }

    /**
     * Las personas de cada unidad, lo más parejo posible y las que sobran en las primeras: 5 en 2 son 3 y 2 (D-158).
     *
     * @return non-empty-list<int>
     */
    public static function reparto(int $personas, int $cantidad): array
    {
        $cantidad = max(1, min($cantidad, $personas));
        $reparto = [];
        for ($i = 0; $i < $cantidad; $i++) {
            $reparto[] = intdiv($personas, $cantidad) + ($i < $personas % $cantidad ? 1 : 0);
        }

        return $reparto;
    }

    /**
     * El anticipo de esa tarifa: el depósito de su propia política o, si no tiene, el del motor.
     */
    public function anticipo(Tarifa $tarifa, AjustesMotor $ajustes): float
    {
        $politica = $tarifa->politicaPropia();

        return $politica === null ? (float) $ajustes->anticipoPorcentaje() : (float) $politica->deposito_porcentaje;
    }

    /**
     * Los lugares que se pueden reservar en esas fechas.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Unidad>
     */
    public function libres(Propiedad $propiedad, CarbonImmutable $llegada, CarbonImmutable $salida, int $personas, ?FiltroLugares $filtro = null): \Illuminate\Database\Eloquent\Collection
    {
        return Disponibilidad::libres([$propiedad->id], $llegada->format('Y-m-d'), $salida->format('Y-m-d'), $personas)->load('otrasTarifas:id')
            ->filter(fn (Unidad $u): bool => $u->tarifasIds() !== [] && Restricciones::deUnidad($u, $llegada, $salida, $propiedad->hoy()) === [] && ($filtro === null || $filtro->cumple($u)));
    }

    /**
     * Los planos publicados, con cada lugar y, si está libre, lo que cuesta. De los demás solo se dice que no se pueden elegir: nunca quién está (D-110).
     *
     * @param  list<Oferta>  $ofertas
     * @return list<array{plano: Plano, lugares: list<array{unidad: Unidad, x: float, y: float, oferta: Oferta|null}>}>
     */
    public function mapas(Propiedad $propiedad, array $ofertas, CarbonImmutable $llegada, CarbonImmutable $salida, int $personas, ?FiltroLugares $filtro = null): array
    {
        if (! (new AjustesMotor($propiedad))->elegirLugar()) {
            return [];
        }
        $libres = $this->libres($propiedad, $llegada, $salida, $personas, $filtro)->keyBy('id');
        $vendibles = collect($ofertas)->filter(fn (Oferta $o): bool => $o->cumpleEstancia);
        $mapas = [];
        foreach (Plano::query()->with('posiciones.unidad.propiedad')->where('propiedad_id', $propiedad->id)->whereNotNull('archivo_id')->orderBy('orden')->orderBy('nombre')->get() as $plano) {
            $lugares = [];
            foreach ($plano->posiciones as $posicion) {
                /** @var PlanoUnidad $posicion */
                $libre = $libres->get($posicion->unidad_id);
                $lugares[] = [
                    'unidad' => $posicion->unidad,
                    'x' => (float) $posicion->x,
                    'y' => (float) $posicion->y,
                    // Con varias tarifas, la más barata: las ofertas ya vienen en ese orden.
                    'oferta' => $libre === null ? null : $vendibles->first(fn (Oferta $o): bool => in_array($o->tarifa->id, $libre->tarifasIds(), true)),
                ];
            }
            if ($lugares !== []) {
                $mapas[] = ['plano' => $plano, 'lugares' => $lugares];
            }
        }

        return $mapas;
    }

    /**
     * La misma oferta con el cargo de elegir un lugar concreto, si lo tiene.
     */
    public function conLugar(Oferta $oferta, Unidad $unidad, CarbonImmutable $llegada, CarbonImmutable $salida, int $personas): Oferta
    {
        $cargo = (new AjustesMotor($unidad->propiedad))->cargoLugarId();
        if ($cargo === null) {
            return $oferta;
        }

        return new Oferta($oferta->tarifa, $oferta->libres, $oferta->capacidad, Cotizador::presupuesto($unidad, $llegada, $salida, $personas, [$cargo => 1.0], tarifaId: $oferta->tarifa->id), $oferta->estanciaMinima, $oferta->cumpleEstancia, AtributosLugar::etiquetas($unidad), $oferta->politica, $oferta->politicaNombre, $oferta->anticipo, $unidad);
    }

    /**
     * Cargos opcionales que el huésped puede añadir a esa estancia: los que cumplen sus reglas (D-121).
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, ConceptoCargo>
     */
    public function cargosOpcionales(Propiedad $propiedad, Unidad $unidad, int $tarifaId, CarbonImmutable $llegada, CarbonImmutable $salida, int $personas): \Illuminate\Database\Eloquent\Collection
    {
        return ConceptoCargo::query()->where('propiedad_id', $propiedad->id)->where('activo', true)
            ->where('obligatorio', false)->where('visible_motor', true)->when((new AjustesMotor($propiedad))->cargoLugarId(), fn ($q, int $cargo) => $q->whereKeyNot($cargo))
            ->orderBy('orden')->orderBy('nombre')->get()
            ->filter(fn (ConceptoCargo $concepto): bool => ReglasCargo::aplica($concepto, $unidad, $tarifaId, $llegada, $salida, $personas))->values();
    }

    /**
     * Cuántos quedan de los extras con cupo, para esas noches.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, ConceptoCargo>  $cargos
     * @return array<int, int>
     */
    public function cupos(\Illuminate\Database\Eloquent\Collection $cargos, CarbonImmutable $llegada, CarbonImmutable $salida): array
    {
        if (! Modulos::activo(Modulo::Camping)) {
            return [];
        }
        $cupos = [];
        foreach ($cargos->whereNotNull('cupo_diario') as $cargo) {
            $cupos[$cargo->id] = (int) CupoExtras::libres($cargo, $llegada, $salida);
        }

        return $cupos;
    }

    /**
     * Crea la reserva. Si otra petición se lleva la unidad en el último momento, prueba la siguiente.
     *
     * @param  array{tarifa_id: int, llegada: string, salida: string, personas: int, nombre: string, apellido: string|null, correo: string, telefono: string|null, comentario: string|null, cupon: string|null, cargos: list<int>, idioma: string, filtro?: FiltroLugares|null, unidad_id?: int, cantidades?: array<int, int>, equipo?: array<string, mixed>|null, unidades?: int}  $datos
     */
    public function reservar(Propiedad $propiedad, array $datos): Reserva
    {
        $ajustes = new AjustesMotor($propiedad);
        if (! $ajustes->activo()) {
            throw new ReservaRechazada(__('motor.error.cerrado'));
        }
        $llegada = CarbonImmutable::parse($datos['llegada'])->startOfDay();
        $salida = CarbonImmutable::parse($datos['salida'])->startOfDay();
        $motivo = $this->motivoFechas($propiedad, $llegada, $salida);
        if ($motivo !== null) {
            throw new ReservaRechazada($motivo);
        }
        $this->comprobarLimites($propiedad, $datos['correo']);

        $tarifa = Tarifa::query()->where('propiedad_id', $propiedad->id)->where('activa', true)->where('visible_motor', true)->find($datos['tarifa_id']);
        if ($tarifa === null) {
            throw new ReservaRechazada(__('motor.error.sin_lugar'));
        }
        // Varias unidades en un solo pedido (D-158): sin cupón, sin extras y sin elegir lugar; eso es de una sola. La tarifa se
        // mide con las personas de cada unidad.
        $reparto = self::reparto($datos['personas'], min((int) ($datos['unidades'] ?? 1), $ajustes->unidadesMaximas()));
        $varias = count($reparto) > 1;
        if ($varias) {
            $datos['cupon'] = null;
            $datos['cargos'] = [];
            $datos['cantidades'] = [];
        }
        if (! $tarifa->admitePersonas($reparto[0])) {
            throw new ReservaRechazada(__('motor.error.sin_lugar'));
        }
        $compuestas = $varias ? self::compuestas($propiedad) : [];
        $candidatas = $this->libres($propiedad, $llegada, $salida, $reparto[0], $datos['filtro'] ?? null)
            ->filter(fn (Unidad $u): bool => in_array($tarifa->id, $u->tarifasIds(), true) && ! in_array($u->id, $compuestas, true));
        if ($candidatas->count() < count($reparto)) {
            throw new ReservaRechazada(__('motor.error.sin_lugar'));
        }
        // El lugar que eligió en el plano, y ningún otro: si ya se ocupó, decide él.
        $elegida = $ajustes->elegirLugar() && ! $varias ? (int) ($datos['unidad_id'] ?? 0) : 0;
        if ($elegida > 0) {
            $candidatas = $candidatas->where('id', $elegida);
            if ($candidatas->isEmpty()) {
                throw new ReservaRechazada(__('camping.motor.lugar_ocupado'));
            }
        }

        $cupon = null;
        if (filled($datos['cupon']) && Modulos::activo(Modulo::Cupones)) {
            $cupon = Cupones::buscar($propiedad->id, $datos['cupon']);
            // Un solo mensaje: el formulario es público y no debe servir para probar qué códigos existen.
            if ($cupon === null || Cupones::motivoRechazo($cupon, $llegada, (int) $llegada->diffInDays($salida)) !== null) {
                throw new ReservaRechazada(__('ui.cupones.error_no_valido'), 'cupon');
            }
        }

        /** @var Unidad $candidata */
        $candidata = $candidatas->first();
        $permitidos = $this->cargosOpcionales($propiedad, $candidata, $tarifa->id, $llegada, $salida, $datos['personas'])->pluck('id')->all();
        $elegidos = array_fill_keys(array_values(array_intersect($datos['cargos'], $permitidos)), 1.0);
        foreach ((array) ($datos['cantidades'] ?? []) as $concepto => $cantidad) {
            if ($cantidad > 0 && in_array((int) $concepto, $permitidos, true)) {
                $elegidos[(int) $concepto] = (float) $cantidad;
            }
        }
        if ($elegida > 0 && $ajustes->cargoLugarId() !== null) {
            $elegidos[$ajustes->cargoLugarId()] = 1.0;
        }

        $reservas = DB::transaction(function () use ($propiedad, $ajustes, $datos, $tarifa, $candidatas, $llegada, $salida, $cupon, $elegidos, $reparto): array {
            if (Modulos::activo(Modulo::Camping)) {
                CupoExtras::apartar($elegidos, $llegada, $salida);
            }
            // Con la fila bloqueada, dos reservas a la vez no rebasan los usos permitidos del cupón.
            if ($cupon !== null) {
                Cupon::query()->whereKey($cupon->id)->lockForUpdate()->first();
            }
            $huesped = $this->huesped($propiedad, $datos);
            $hechas = [];
            $libres = $candidatas->values();
            foreach ($reparto as $personas) {
                /** @var Unidad $primera */
                $primera = $libres->first();
                $presupuesto = Cotizador::presupuesto($primera, $llegada, $salida, $personas, $elegidos, $cupon, tarifaId: $tarifa->id);
                if ($presupuesto->cotizacion === null || ! $presupuesto->cotizacion->cumpleEstanciaMinima()) {
                    throw new ReservaRechazada(__('motor.error.estancia_minima', ['noches' => $presupuesto->cotizacion->estanciaMinima ?? 1]));
                }
                $hecha = $this->crearEnAlguna($propiedad, $ajustes, $libres, $datos + ['huesped' => $huesped], $tarifa, $presupuesto, $personas, $llegada, $salida, $cupon, $elegidos);
                $hechas[] = $hecha;
                $libres = $libres->reject(fn (Unidad $unidad): bool => $unidad->id === $hecha->unidad_id)->values();
            }
            // Las demás apuntan a la primera, que es la que se paga por todas (D-158).
            if (count($hechas) > 1) {
                DB::table('reservas')->whereIn('id', array_map(fn (Reserva $r): int => $r->id, array_slice($hechas, 1)))->update(['reserva_principal_id' => $hechas[0]->id]);
            }

            return $hechas;
        });
        foreach ($reservas as $reserva) {
            app(Mensajero::class)->avisarNuevaReserva($reserva, $ajustes->correoAviso());
        }

        return $reservas[0];
    }

    /**
     * Crea la reserva en la primera de esas unidades que la acepte: otra petición pudo ocupar la que iba a tocarle.
     *
     * @param  \Illuminate\Support\Collection<int, Unidad>  $candidatas
     * @param  array<string, mixed>  $datos
     * @param  array<int, float>  $elegidos
     */
    private function crearEnAlguna(Propiedad $propiedad, AjustesMotor $ajustes, \Illuminate\Support\Collection $candidatas, array $datos, Tarifa $tarifa, Presupuesto $presupuesto, int $personas, CarbonImmutable $llegada, CarbonImmutable $salida, ?Cupon $cupon, array $elegidos): Reserva
    {
        $huesped = $datos['huesped'];
        $total = $presupuesto->total();
        $anticipo = (int) round($total * $this->anticipo($tarifa, $ajustes) / 100);
        $pagaraEnLinea = $anticipo > 0 && (new Pasarelas($propiedad))->disponibles() !== [];
        $confirmada = ! $pagaraEnLinea && $ajustes->confirmacionInmediata();

        foreach ($candidatas as $unidad) {
            /** @var Unidad $unidad */
            try {
                return $this->reservas->crear($propiedad, [
                    'unidad_id' => $unidad->id,
                    'tarifa_id' => $tarifa->id,
                    'titular_id' => $huesped->id,
                    'llegada' => $llegada->format('Y-m-d'),
                    'salida' => $salida->format('Y-m-d'),
                    'personas' => $personas,
                    'estado' => ($confirmada ? EstadoReserva::Confirmada : EstadoReserva::SinConfirmar)->value,
                    'total' => $total,
                    'deposito' => $anticipo,
                    'descuento' => $presupuesto->descuento,
                    'origen_texto' => __('motor.origen'),
                    'comentario' => $datos['comentario'],
                    'cupon_codigo' => $cupon->codigo ?? '',
                    'idioma' => $datos['idioma'],
                    // Nada retiene noches para siempre desde un formulario público: si no paga
                    // o nadie la revisa a tiempo, las noches vuelven a venderse.
                    'expira_en' => match (true) {
                        $pagaraEnLinea => now('UTC')->addMinutes($ajustes->minutosRetencion()),
                        ! $confirmada && $ajustes->horasRevision() > 0 => now('UTC')->addHours($ajustes->horasRevision()),
                        default => null,
                    },
                    'cargos' => array_map(fn (int $id): array => ['concepto_cargo_id' => $id, 'cantidad' => $elegidos[$id]], array_keys($elegidos)),
                    'equipo' => $datos['equipo'] ?? null,
                ]);
            } catch (ReservaRechazada $e) {
                if ($e->campo === 'cupon_codigo') {
                    throw new ReservaRechazada(__('ui.cupones.error_no_valido'), 'cupon');
                }
                if ($e->campo !== 'unidad_id') {
                    throw $e;
                }
            }
        }

        throw new ReservaRechazada(__('motor.error.sin_lugar'));
    }

    /**
     * Frena a quien llena el formulario una y otra vez para dejar a la propiedad sin lugar.
     */
    private function comprobarLimites(Propiedad $propiedad, string $correo): void
    {
        $normalizado = Contactos::normalizar(TipoContacto::Correo->value, $correo);
        $vigentes = Reserva::query()->where('propiedad_id', $propiedad->id)->where('estado', '!=', EstadoReserva::Cancelada->value);
        $delCorreo = (clone $vigentes)->where('salida', '>=', $propiedad->hoy()->format('Y-m-d'))
            ->whereHas('titular.contactos', fn ($q) => $q->where('valor_normalizado', $normalizado))->count();
        if ($delCorreo >= self::VIGENTES_POR_CORREO) {
            throw new ReservaRechazada(__('motor.error.demasiadas'));
        }
        // Solo las del formulario: recepción puede cargar un grupo entero sin detener el motor.
        $delMotor = array_map(fn (string $idioma): string => __('motor.origen', [], $idioma), (array) config('alojaflow.idiomas'));
        if ((clone $vigentes)->whereIn('origen_texto', $delMotor)->where('creado_en', '>=', now('UTC')->subHour())->count() >= self::NUEVAS_POR_HORA) {
            Aviso::abrir($propiedad->id, 'motor_saturado', NivelAviso::Advertencia);
            throw new ReservaRechazada(__('motor.error.saturado'));
        }
    }

    /**
     * Se reutiliza una ficha solo si coinciden el correo y el nombre. Con el correo nada más, quien lo
     * escriba vería el nombre real de la persona y le dejaría reservas falsas en su historial. Si es
     * la misma persona con otro nombre escrito, el personal puede fusionar las dos fichas.
     *
     * @param  array<string, mixed>  $datos
     */
    private function huesped(Propiedad $propiedad, array $datos): Huesped
    {
        $correo = Contactos::normalizar(TipoContacto::Correo->value, (string) $datos['correo']);
        $contactos = ContactoHuesped::query()->with('huesped')->where('valor_normalizado', $correo)
            ->whereHas('huesped', fn ($q) => $q->where('propiedad_id', $propiedad->id)->where('sincronizada', false))->get();
        foreach ($contactos as $contacto) {
            if ($this->mismoNombre($contacto->huesped, (string) $datos['nombre'], (string) $datos['apellido'])) {
                return $contacto->huesped;
            }
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

    private function mismoNombre(Huesped $huesped, string $nombre, string $apellido): bool
    {
        $normal = fn (?string $texto): string => Str::lower(Str::ascii(trim((string) $texto)));

        return $normal($huesped->nombre) === $normal($nombre)
            && in_array($normal($apellido), [$normal($huesped->apellido1), $normal(trim($huesped->apellido1.' '.$huesped->apellido2))], true);
    }
}
