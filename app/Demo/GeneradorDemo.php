<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Demo;

use App\Enums\EstadoReserva;
use App\Enums\MetodoPago;
use App\Enums\ModoPropiedad;
use App\Enums\PaquetePais;
use App\Enums\TipoContacto;
use App\Enums\TipoReserva;
use App\Models\Huesped;
use App\Models\Propiedad;
use App\Models\RegistroAuditoria;
use App\Models\Reserva;
use App\Models\Tarifa;
use App\Models\Unidad;
use App\Reservas\Cotizador;
use App\Reservas\GestorReservas;
use App\Support\Contactos;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use LogicException;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Propiedad independiente de demostración con datos inventados, alrededor de hoy: siempre hay
 * llegadas, salidas y huéspedes alojados para probar. Usa correos @example.com y números de
 * teléfono de ficción; nada viene de copias reales (regla 12). Se crea y se quita entera (D-037).
 */
final class GeneradorDemo
{
    private const DIAS_ATRAS = 60;

    private const DIAS_ADELANTE = 90;

    private const CANTIDAD_HUESPEDES = 60;

    private const NOMBRES = ['Ana', 'Luis', 'Carmen', 'Jorge', 'Elena', 'Marco', 'Lucía', 'Diego', 'Sofía', 'Andrés', 'Paula', 'Tomás', 'Julia', 'Raúl', 'Inés', 'Emma', 'Noah', 'Grace', 'Oliver', 'Claire'];

    private const APELLIDOS = ['Ejemplo', 'Prueba', 'Muestra', 'Ficticio', 'Modelo', 'Ensayo', 'Sample', 'Tester', 'Demo', 'Simulado', 'Figurado', 'Supuesto'];

    private const PAISES = [['México', '+52 55 5550', 'es'], ['Estados Unidos', '+1 202 555', 'en'], ['Canadá', '+1 613 555', 'en'], ['España', '+34 600 000', 'es']];

    private const COMENTARIOS = ['Piden cuna', 'Aniversario de bodas', 'Llegan tarde, después de las 22:00', 'Piso alto, si es posible', 'Viajan con mascota pequeña', 'Alergia a las plumas'];

    private const MOTIVOS_BLOQUEO = ['Mantenimiento', 'Pintura', 'Uso del propietario'];

    /**
     * Precio base en pesos y unidades de cada tarifa: nombre, capacidad, piso y edificio.
     */
    private const CATALOGO = [
        'Doble' => [1200, [['101', 2, '1', 'Hotel'], ['102', 2, '1', 'Hotel'], ['103', 2, '1', 'Hotel'], ['104', 2, '1', 'Hotel']]],
        'King' => [1500, [['105', 2, '1', 'Hotel'], ['106', 2, '1', 'Hotel']]],
        'Familiar' => [1900, [['201', 4, '2', 'Hotel'], ['202', 4, '2', 'Hotel'], ['203', 4, '2', 'Hotel']]],
        'Suite' => [2400, [['Suite 301', 3, '3', 'Hotel']]],
        'Cabaña' => [2800, [['Cabaña A', 5, null, 'Jardín'], ['Cabaña B', 5, null, 'Jardín']]],
    ];

    private GestorReservas $gestor;

    private Randomizer $azar;

    public function __construct()
    {
        $this->gestor = new GestorReservas(registrar: false);
    }

    public static function existente(): ?Propiedad
    {
        return Propiedad::query()->where('es_demo', true)->first();
    }

    public function crear(): Propiedad
    {
        if (self::existente() !== null) {
            throw new LogicException('Ya hay una propiedad de demostración.');
        }
        $this->azar = new Randomizer(new Mt19937(2026));

        $propiedad = DB::transaction(function (): Propiedad {
            $propiedad = Propiedad::query()->create([
                'nombre' => __('ui.demo.nombre_propiedad'),
                'codigo' => $this->codigoLibre(),
                'color' => '#b45309',
                'modo' => ModoPropiedad::Nativa,
                'paquete_pais' => PaquetePais::Mexico,
                'pais' => 'MX',
                'zona_horaria' => 'America/Mexico_City',
                'moneda' => 'MXN',
                'idioma' => 'es',
                'activa' => true,
                'es_demo' => true,
            ]);
            $unidades = $this->crearCatalogo($propiedad);
            $huespedes = $this->crearHuespedes($propiedad);
            foreach ($unidades as $indice => $unidad) {
                $this->llenarUnidad($propiedad, $unidad, $indice, $huespedes);
            }

            return $propiedad;
        });
        RegistroAuditoria::anotar('demo.crear', $propiedad, [], $propiedad->id);

        return $propiedad;
    }

    public function eliminar(): bool
    {
        $propiedad = self::existente();
        if ($propiedad === null) {
            return false;
        }
        RegistroAuditoria::anotar('demo.eliminar', $propiedad);
        $propiedad->delete();

        return true;
    }

    private function codigoLibre(): string
    {
        $codigo = 'demo';
        for ($i = 2; Propiedad::query()->where('codigo', $codigo)->exists(); $i++) {
            $codigo = 'demo-'.$i;
        }

        return $codigo;
    }

    /**
     * @return list<Unidad>
     */
    private function crearCatalogo(Propiedad $propiedad): array
    {
        $anio = (int) $propiedad->hoy()->format('Y');
        $unidades = [];
        $orden = 0;
        foreach (self::CATALOGO as $nombre => [$pesos, $filas]) {
            $tarifa = Tarifa::query()->create(['propiedad_id' => $propiedad->id, 'nombre' => $nombre, 'precio_noche' => $pesos * 100, 'activa' => true]);
            foreach ([$anio - 1, $anio, $anio + 1] as $a) {
                $tarifa->temporadas()->createMany([
                    ['nombre' => __('ui.demo.temporada_primavera'), 'desde' => $a.'-03-20', 'hasta' => $a.'-04-10', 'precio_noche' => $this->conAumento($pesos, 40)],
                    ['nombre' => __('ui.demo.temporada_verano'), 'desde' => $a.'-07-10', 'hasta' => $a.'-08-20', 'precio_noche' => $this->conAumento($pesos, 30)],
                    ['nombre' => __('ui.demo.temporada_invierno'), 'desde' => $a.'-12-19', 'hasta' => ($a + 1).'-01-04', 'precio_noche' => $this->conAumento($pesos, 60)],
                ]);
            }
            foreach ($filas as [$unidad, $capacidad, $piso, $edificio]) {
                $unidades[] = Unidad::query()->create([
                    'propiedad_id' => $propiedad->id, 'tarifa_id' => $tarifa->id, 'codigo' => $unidad, 'nombre' => $unidad,
                    'capacidad' => $capacidad, 'piso' => $piso, 'edificio' => $edificio, 'orden' => ++$orden, 'activa' => true,
                ]);
            }
        }

        return $unidades;
    }

    /**
     * Sube un porcentaje y redondea a 50 pesos; el resultado va en centavos.
     */
    private function conAumento(int $pesos, int $porcentaje): int
    {
        return (int) (round($pesos * (100 + $porcentaje) / 100 / 50) * 50 * 100);
    }

    /**
     * @return list<int>
     */
    private function crearHuespedes(Propiedad $propiedad): array
    {
        $ids = [];
        for ($i = 1; $i <= self::CANTIDAD_HUESPEDES; $i++) {
            $nombre = self::NOMBRES[$this->azar->getInt(0, count(self::NOMBRES) - 1)];
            $apellido = self::APELLIDOS[$this->azar->getInt(0, count(self::APELLIDOS) - 1)];
            [$pais, $prefijo, $idioma] = self::PAISES[$this->azar->getInt(0, count(self::PAISES) - 1)];
            $huesped = Huesped::query()->create([
                'propiedad_id' => $propiedad->id,
                'nombre' => $nombre,
                'apellido1' => $apellido,
                'idioma' => $idioma,
                'nacionalidad_texto' => $pais,
                'domicilio_pais_texto' => $pais,
                'documento_tipo_texto' => __('ui.demo.documento'),
                'documento_numero' => 'DEMO'.str_pad((string) $i, 6, '0', STR_PAD_LEFT),
            ]);
            $telefono = $prefijo.' '.str_pad((string) (100 + $i), 4, '0', STR_PAD_LEFT);
            $correo = mb_strtolower(strtr($nombre.'.'.$apellido, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n'])).'.'.$i.'@example.com';
            $huesped->contactos()->createMany([
                ['tipo' => TipoContacto::Telefono, 'valor' => $telefono, 'valor_normalizado' => Contactos::normalizar('telefono', $telefono), 'orden' => 0],
                ['tipo' => TipoContacto::Correo, 'valor' => $correo, 'valor_normalizado' => Contactos::normalizar('correo', $correo), 'orden' => 1],
            ]);
            $ids[] = $huesped->id;
        }

        return $ids;
    }

    /**
     * Una estancia ancla según la unidad (llega hoy, sale hoy, sigue alojada o libre) y de ahí se
     * llena el calendario hacia atrás y hacia adelante, sin solapes por construcción.
     *
     * @param  list<int>  $huespedes
     */
    private function llenarUnidad(Propiedad $propiedad, Unidad $unidad, int $indice, array $huespedes): void
    {
        $hoy = $propiedad->hoy();
        [$inicio, $fin] = match ($indice % 4) {
            0 => [$hoy, $hoy->addDays($this->azar->getInt(2, 5))],
            1 => [$hoy->subDays($this->azar->getInt(2, 5)), $hoy],
            2 => [$hoy->subDays($this->azar->getInt(1, 3)), $hoy->addDays($this->azar->getInt(1, 4))],
            default => [$hoy->addDays(1), $hoy->addDays($this->azar->getInt(3, 6))],
        };
        $this->crearEstancia($propiedad, $unidad, $inicio, $fin, $huespedes, ancla: true);

        for ($cursor = $fin->addDays($this->azar->getInt(0, 4)); $cursor->lt($hoy->addDays(self::DIAS_ADELANTE));) {
            $salida = $cursor->addDays($this->azar->getInt(1, 7));
            $this->crearEstancia($propiedad, $unidad, $cursor, $salida, $huespedes);
            $cursor = $salida->addDays($this->azar->getInt(0, 6));
        }
        for ($cursor = $inicio->subDays($this->azar->getInt(0, 2)); $cursor->gt($hoy->subDays(self::DIAS_ATRAS));) {
            $llegada = $cursor->subDays($this->azar->getInt(1, 7));
            $this->crearEstancia($propiedad, $unidad, $llegada, $cursor, $huespedes);
            $cursor = $llegada->subDays($this->azar->getInt(0, 3));
        }
    }

    /**
     * @param  list<int>  $huespedes
     */
    private function crearEstancia(Propiedad $propiedad, Unidad $unidad, CarbonImmutable $llegada, CarbonImmutable $salida, array $huespedes, bool $ancla = false): void
    {
        $hoy = $propiedad->hoy();
        if (! $ancla && $this->azar->getInt(1, 100) <= 4) {
            $this->gestor->crear($propiedad, [
                'tipo' => TipoReserva::Bloqueo->value, 'unidad_id' => $unidad->id, 'llegada' => $llegada->format('Y-m-d'), 'salida' => $salida->format('Y-m-d'),
                'origen_texto' => self::MOTIVOS_BLOQUEO[$this->azar->getInt(0, count(self::MOTIVOS_BLOQUEO) - 1)],
            ]);

            return;
        }

        [$origen, $porcentajeComision] = $this->origen();
        $total = Cotizador::cotizar($unidad, $llegada, $salida)->total ?? 0;
        $futura = $llegada->gt($hoy);
        $estado = $futura && $this->azar->getInt(1, 100) <= 20 ? EstadoReserva::SinConfirmar : EstadoReserva::Confirmada;
        $reserva = $this->gestor->crear($propiedad, [
            'tipo' => TipoReserva::Reserva->value,
            'unidad_id' => $unidad->id,
            'titular_id' => $huespedes[$this->azar->getInt(0, count($huespedes) - 1)],
            'llegada' => $llegada->format('Y-m-d'),
            'salida' => $salida->format('Y-m-d'),
            'personas' => $this->azar->getInt(1, max(1, $unidad->capacidad)),
            'estado' => $estado->value,
            'total' => $total,
            'deposito' => (int) round($total * 0.3),
            'comision' => (int) round($total * $porcentajeComision / 100),
            'origen_texto' => $origen,
            'comentario' => $this->azar->getInt(1, 100) <= 15 ? self::COMENTARIOS[$this->azar->getInt(0, count(self::COMENTARIOS) - 1)] : null,
        ]);

        if (! $ancla && $this->azar->getInt(1, 100) <= 8) {
            $this->gestor->cancelar($reserva);

            return;
        }
        $this->marcarEstado($reserva, $llegada, $salida, $hoy, $estado);
    }

    /**
     * Entrada, salida y cobros según lo que ya tendría que haber pasado a la fecha de hoy.
     */
    private function marcarEstado(Reserva $reserva, CarbonImmutable $llegada, CarbonImmutable $salida, CarbonImmutable $hoy, EstadoReserva $estado): void
    {
        $zona = $reserva->propiedad->zona_horaria;
        $total = $reserva->total;
        $anticipo = (int) round($total * 0.3);
        $metodo = MetodoPago::cases()[$this->azar->getInt(0, 2)];

        if ($llegada->lt($hoy)) {
            $reserva->entrada_registrada_en = Carbon::parse($llegada->format('Y-m-d').' 15:00:00', $zona)->utc();
            if ($salida->lt($hoy)) {
                $reserva->salida_registrada_en = Carbon::parse($salida->format('Y-m-d').' 11:00:00', $zona)->utc();
            }
            $reserva->save();
        }

        if ($salida->lte($hoy)) {
            $this->cobrar($reserva, $total, $metodo, $llegada);
        } elseif ($llegada->lte($hoy)) {
            $this->cobrar($reserva, $this->azar->getInt(0, 1) === 1 ? $total : $anticipo, $metodo, $llegada);
        } elseif ($estado === EstadoReserva::Confirmada && $this->azar->getInt(1, 100) <= 60) {
            $this->cobrar($reserva, $anticipo, $metodo, $hoy->subDays($this->azar->getInt(1, 20)));
        }
    }

    private function cobrar(Reserva $reserva, int $importe, MetodoPago $metodo, CarbonImmutable $fecha): void
    {
        if ($importe > 0) {
            $this->gestor->registrarPago($reserva, $importe, $metodo, $fecha);
        }
    }

    /**
     * @return array{0: string, 1: int} origen y su comisión en porcentaje
     */
    private function origen(): array
    {
        $tirada = $this->azar->getInt(1, 100);

        return match (true) {
            $tirada <= 40 => ['Directo', 0],
            $tirada <= 70 => ['Booking', 15],
            $tirada <= 90 => ['Airbnb', 3],
            default => ['Teléfono', 0],
        };
    }
}
