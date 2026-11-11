<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Limpieza;

use App\Enums\EstadoLimpieza;
use App\Enums\EstadoReserva;
use App\Enums\EstadoTarea;
use App\Enums\LugarOmision;
use App\Enums\TipoReserva;
use App\Enums\TipoTarea;
use App\Models\CambioEstadoUnidad;
use App\Models\EstadoUnidad;
use App\Models\ListaComprobacion;
use App\Models\MantenimientoRecurrente;
use App\Models\Propiedad;
use App\Models\ReglaLimpieza;
use App\Models\Reserva;
use App\Models\Tarea;
use App\Models\Unidad;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Estado de las unidades y sus tareas (M4). Funciona igual en independientes y subordinadas: lee
 * las reservas y escribe solo en sus propias tablas.
 */
final class GestorLimpieza
{
    /** Hasta cuántos días por delante se crean las tareas automáticas. */
    public const DIAS_POR_DELANTE = 7;

    /**
     * Una unidad sin fila está limpia y en servicio.
     */
    public function estado(Unidad $unidad): EstadoUnidad
    {
        return EstadoUnidad::query()->firstOrNew(['unidad_id' => $unidad->id], ['limpieza' => EstadoLimpieza::Limpia, 'fuera_de_servicio' => false]);
    }

    public function cambiar(Unidad $unidad, ?EstadoLimpieza $limpieza = null, ?bool $fueraDeServicio = null, ?string $nota = null): EstadoUnidad
    {
        return DB::transaction(function () use ($unidad, $limpieza, $fueraDeServicio, $nota): EstadoUnidad {
            $estado = $this->estado($unidad);
            $antes = ['limpieza' => $estado->limpieza->value, 'fuera_de_servicio' => $estado->fuera_de_servicio ? '1' : '0'];
            if ($limpieza !== null) {
                $estado->limpieza = $limpieza;
            }
            if ($fueraDeServicio !== null) {
                $estado->fuera_de_servicio = $fueraDeServicio;
            }
            if ($nota !== null) {
                $estado->nota = $nota === '' ? null : $nota;
            }
            $estado->actualizado_por = auth()->id();
            $estado->actualizado_en = now('UTC');
            $estado->save();

            $despues = ['limpieza' => $estado->limpieza->value, 'fuera_de_servicio' => $estado->fuera_de_servicio ? '1' : '0'];
            foreach ($despues as $campo => $valor) {
                if ($antes[$campo] !== $valor) {
                    CambioEstadoUnidad::query()->create(['unidad_id' => $unidad->id, 'campo' => $campo, 'antes' => $antes[$campo], 'despues' => $valor, 'user_id' => auth()->id()]);
                }
            }

            return $estado;
        });
    }

    /**
     * Al salir el huésped la unidad queda sucia. Un cambio posterior a la salida no se pisa: si
     * alguien ya la marcó limpia después, se queda así.
     */
    public function salidaRegistrada(Reserva $reserva): void
    {
        $unidad = $reserva->unidad;
        if ($this->tocaEnsuciar($this->estado($unidad), $reserva)) {
            $this->cambiar($unidad, EstadoLimpieza::Sucia);
        }
    }

    private function tocaEnsuciar(EstadoUnidad $estado, Reserva $reserva): bool
    {
        return ! $estado->exists || $estado->actualizado_en === null
            || $reserva->salida_registrada_en === null || $estado->actualizado_en->lt($reserva->salida_registrada_en);
    }

    /**
     * Crea las tareas automáticas de los próximos días y cancela las que ya no tocan: la reserva
     * cambió de fechas o se canceló. Se puede repetir: la clave impide duplicados.
     *
     * @return int las tareas nuevas
     */
    public function generarTareas(Propiedad $propiedad): int
    {
        $hoy = $propiedad->hoy();
        $hasta = $hoy->addDays(self::DIAS_POR_DELANTE);
        $vigentes = [];
        $nuevas = 0;
        // Esta pantalla lo pide en cada visita: lo que ya existe no se vuelve a intentar insertar.
        $existentes = Tarea::query()->where('propiedad_id', $propiedad->id)->where('fecha', '>=', $hoy->format('Y-m-d'))
            ->whereNotNull('clave_generacion')->pluck('clave_generacion')->flip();

        $reservas = Omisiones::excluir(Reserva::query()->with('unidad'), 'titular_id', [$propiedad->id], LugarOmision::Limpieza)
            ->where('propiedad_id', $propiedad->id)->where('tipo', TipoReserva::Reserva->value)
            ->where('estado', '!=', EstadoReserva::Cancelada->value)->whereNull('eliminada_en_principal_en')
            ->where('salida', '>=', $hoy->format('Y-m-d'))->where('llegada', '<=', $hasta->format('Y-m-d'))
            ->get();
        $llegadas = $reservas->groupBy(fn (Reserva $r): string => $r->unidad_id.':'.$r->llegada->format('Y-m-d'));
        $reglas = ReglaLimpieza::query()->whereIn('reserva_id', $reservas->pluck('id'))->get()->keyBy('reserva_id');

        foreach ($reservas as $reserva) {
            $nombreUnidad = $reserva->unidad->nombre ?? '';
            if ($reserva->salida->format('Y-m-d') <= $hasta->format('Y-m-d')) {
                $fecha = $reserva->salida->format('Y-m-d');
                $clave = 'salida:'.$reserva->unidad_id.':'.$fecha.':'.$reserva->id;
                $vigentes[] = $clave;
                // Si alguien llega ese mismo día a esa unidad, corre prisa.
                $prioridad = $llegadas->has($reserva->unidad_id.':'.$fecha) ? 1 : 0;
                $nuevas += $existentes->has($clave) ? 0 : $this->crearAutomatica($clave, $propiedad, $reserva, TipoTarea::LimpiezaSalida, $reserva->salida, $prioridad, __('limpieza.tarea_salida', ['unidad' => $nombreUnidad], $propiedad->idioma));
            }

            $regla = $reglas->get($reserva->id);
            if ($regla === null) {
                continue;
            }
            for ($dia = $reserva->llegada->addDay(); $dia->lt($reserva->salida); $dia = $dia->addDay()) {
                if ($dia->lt($hoy) || $dia->gt($hasta) || ! $regla->tocaEl($dia)) {
                    continue;
                }
                $clave = 'estancia:'.$reserva->unidad_id.':'.$dia->format('Y-m-d').':'.$reserva->id;
                $vigentes[] = $clave;
                $nuevas += $existentes->has($clave) ? 0 : $this->crearAutomatica($clave, $propiedad, $reserva, TipoTarea::LimpiezaEstancia, $dia, 0, __('limpieza.tarea_estancia', ['unidad' => $nombreUnidad], $propiedad->idioma));
            }
        }

        $nuevas += $this->generarRecurrentes($propiedad, $hoy, $hasta);

        // Las recurrentes no dependen de ninguna reserva: no hay nada que las deje sin efecto.
        Tarea::query()->where('propiedad_id', $propiedad->id)->whereNotNull('clave_generacion')
            ->where('clave_generacion', 'not like', 'recurrente:%')
            ->where('estado', EstadoTarea::Pendiente->value)->where('fecha', '>=', $hoy->format('Y-m-d'))
            ->when($vigentes !== [], fn ($q) => $q->whereNotIn('clave_generacion', $vigentes))
            ->update(['estado' => EstadoTarea::Cancelada->value]);
        // Una tarea cancelada puede volver a tocar si la reserva regresó a esas fechas.
        if ($vigentes !== []) {
            Tarea::query()->where('propiedad_id', $propiedad->id)->whereIn('clave_generacion', $vigentes)
                ->where('estado', EstadoTarea::Cancelada->value)->update(['estado' => EstadoTarea::Pendiente->value]);
        }

        return $nuevas;
    }

    /**
     * Una tarea por cada mantenimiento que se acerca. Si la fecha ya pasó (el módulo estuvo apagado), no se acumulan: toca la siguiente.
     */
    private function generarRecurrentes(Propiedad $propiedad, CarbonImmutable $hoy, CarbonImmutable $hasta): int
    {
        $nuevas = 0;
        foreach (MantenimientoRecurrente::query()->where('propiedad_id', $propiedad->id)->where('activo', true)->where('proxima_fecha', '<=', $hasta->format('Y-m-d'))->get() as $trabajo) {
            $cada = max(1, $trabajo->cada_dias);
            $fecha = $trabajo->proxima_fecha;
            while ($fecha->format('Y-m-d') < $hoy->format('Y-m-d')) {
                $fecha = $fecha->addDays($cada);
            }
            while ($fecha->format('Y-m-d') <= $hasta->format('Y-m-d')) {
                $nuevas += Tarea::query()->insertOrIgnore([
                    'propiedad_id' => $propiedad->id,
                    'unidad_id' => $trabajo->unidad_id,
                    'tipo' => TipoTarea::Mantenimiento->value,
                    'titulo' => $trabajo->titulo,
                    'descripcion' => $trabajo->descripcion,
                    'puntos' => $this->puntosDe($propiedad, TipoTarea::Mantenimiento),
                    'fecha' => $fecha->format('Y-m-d'),
                    'prioridad' => 0,
                    'estado' => EstadoTarea::Pendiente->value,
                    'clave_generacion' => 'recurrente:'.$trabajo->id.':'.$fecha->format('Y-m-d'),
                    'creado_en' => now('UTC'),
                    'actualizado_en' => now('UTC'),
                ]);
                $fecha = $fecha->addDays($cada);
            }
            $trabajo->update(['proxima_fecha' => $fecha->format('Y-m-d')]);
        }

        return $nuevas;
    }

    /**
     * Cada tarea guarda su copia de la lista: cambiarla después no altera lo ya revisado.
     */
    private function puntosDe(Propiedad $propiedad, TipoTarea $tipo): ?string
    {
        $lista = ListaComprobacion::query()->where('propiedad_id', $propiedad->id)->where('tipo_tarea', $tipo->value)->where('activa', true)->first();
        if ($lista === null) {
            return null;
        }

        return (string) json_encode(array_map(fn (string $punto): array => ['texto' => $punto, 'hecho' => false], array_values($lista->puntos)), JSON_UNESCAPED_UNICODE);
    }

    public function marcarPunto(Tarea $tarea, int $indice): void
    {
        $puntos = (array) $tarea->puntos;
        if (! isset($puntos[$indice])) {
            return;
        }
        $puntos[$indice]['hecho'] = ! $puntos[$indice]['hecho'];
        $tarea->update(['puntos' => $puntos]);
    }

    /**
     * Marca sucias las unidades de las salidas registradas en la principal: en una subordinada
     * la salida no pasa por AlojaFlow.
     */
    public function atenderSalidas(Propiedad $propiedad): void
    {
        $hoy = $propiedad->hoy();
        Reserva::query()->with('unidad')->where('propiedad_id', $propiedad->id)
            ->whereNotNull('salida_registrada_en')->where('salida_registrada_en', '>=', now('UTC')->subDays(2))
            ->where('salida', '<=', $hoy->addDay()->format('Y-m-d'))
            ->each(fn (Reserva $reserva) => $this->salidaRegistrada($reserva));
    }

    public function empezar(Tarea $tarea): void
    {
        if ($tarea->estado === EstadoTarea::Pendiente) {
            $tarea->update(['estado' => EstadoTarea::EnCurso, 'asignada_a' => $tarea->asignada_a ?? auth()->id()]);
        }
    }

    /**
     * Terminar una limpieza deja la unidad limpia, o por revisar si el alojamiento inspecciona.
     */
    public function terminar(Tarea $tarea): void
    {
        DB::transaction(function () use ($tarea): void {
            $tarea->update(['estado' => EstadoTarea::Hecha, 'hecha_por' => auth()->id(), 'hecha_en' => now('UTC')]);
            $unidad = $tarea->unidad;
            if ($unidad === null) {
                return;
            }
            if ($tarea->tipo->esLimpieza()) {
                $this->cambiar($unidad, $this->pideInspeccion($tarea->propiedad) ? EstadoLimpieza::Inspeccion : EstadoLimpieza::Limpia);
            } elseif ($tarea->tipo === TipoTarea::Inspeccion) {
                $this->cambiar($unidad, EstadoLimpieza::Limpia);
            }
        });
    }

    public function pideInspeccion(Propiedad $propiedad): bool
    {
        return (bool) (((array) $propiedad->ajustes)['limpieza']['inspeccion'] ?? false);
    }

    public function cancelar(Tarea $tarea): void
    {
        $tarea->update(['estado' => EstadoTarea::Cancelada]);
    }

    public function reportarIncidencia(Unidad $unidad, string $titulo, ?string $descripcion, bool $urgente, bool $fueraDeServicio): Tarea
    {
        return DB::transaction(function () use ($unidad, $titulo, $descripcion, $urgente, $fueraDeServicio): Tarea {
            $tarea = Tarea::query()->create([
                'propiedad_id' => $unidad->propiedad_id,
                'unidad_id' => $unidad->id,
                'tipo' => TipoTarea::Mantenimiento,
                'titulo' => $titulo,
                'descripcion' => $descripcion,
                'fecha' => $unidad->propiedad->hoy(),
                'prioridad' => $urgente ? 1 : 0,
                'estado' => EstadoTarea::Pendiente,
                'creada_por' => auth()->id(),
            ]);
            if ($fueraDeServicio) {
                $this->cambiar($unidad, fueraDeServicio: true, nota: $titulo);
            }

            return $tarea;
        });
    }

    /**
     * Enlace de WhatsApp con la lista del día, listo para mandarlo a quien limpia.
     */
    public function enlaceAvisoDia(Propiedad $propiedad, CarbonImmutable $fecha): string
    {
        $tareas = Tarea::query()->with('unidad')->where('propiedad_id', $propiedad->id)
            ->where('fecha', $fecha->format('Y-m-d'))
            ->whereIn('estado', [EstadoTarea::Pendiente->value, EstadoTarea::EnCurso->value])
            ->orderByDesc('prioridad')->orderBy('id')->get();

        $lineas = [__('limpieza.aviso_titulo', ['propiedad' => $propiedad->nombre, 'fecha' => $fecha->locale(app()->getLocale())->isoFormat('dddd D MMM')])];
        foreach ($tareas as $tarea) {
            $lineas[] = ($tarea->prioridad > 0 ? '❗ ' : '• ').$tarea->titulo;
        }
        if ($tareas->isEmpty()) {
            $lineas[] = __('limpieza.aviso_vacio');
        }

        return 'https://wa.me/?text='.rawurlencode(implode("\n", $lineas));
    }

    private function crearAutomatica(string $clave, Propiedad $propiedad, Reserva $reserva, TipoTarea $tipo, CarbonImmutable $fecha, int $prioridad, string $titulo): int
    {
        return Tarea::query()->insertOrIgnore([
            'propiedad_id' => $propiedad->id,
            'unidad_id' => $reserva->unidad_id,
            'reserva_id' => $reserva->id,
            'tipo' => $tipo->value,
            'titulo' => mb_substr($titulo, 0, 150),
            'puntos' => $this->puntosDe($propiedad, $tipo),
            'fecha' => $fecha->format('Y-m-d'),
            'prioridad' => $prioridad,
            'estado' => EstadoTarea::Pendiente->value,
            'clave_generacion' => $clave,
            'creado_en' => now('UTC'),
            'actualizado_en' => now('UTC'),
        ]);
    }
}
