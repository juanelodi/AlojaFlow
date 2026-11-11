<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Autoridades;

use App\Enums\EstadoEnvioAutoridad;
use App\Enums\EstadoReserva;
use App\Enums\NivelAviso;
use App\Enums\TipoReserva;
use App\Models\Aviso;
use App\Models\EnvioAutoridad;
use App\Models\Propiedad;
use App\Models\Reserva;
use App\Paises\Paises;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Que cada reserva y cada llegada termine comunicada, pase lo que pase con ella (D-100). No se engancha a cada forma de crear o cambiar una reserva, que son muchas:
 * cada vez que corre compara lo que hay con lo que se comunicó, y hace lo que falte.
 */
final class GestorEnvios
{
    private const POR_LOTE = 100;

    private const LOTES_POR_CONSULTA = 10;

    /** No se manda nada de lo que ya pasó hace mucho: se conecta hoy, no hace un año. */
    private const DIAS_ATRAS = 7;

    private const PLAZO_HORAS = 24;

    /** @var list<int> minutos antes de volver a intentarlo */
    private const ESPERAS = [10, 30, 60, 180, 360];

    /**
     * @return array{conciliadas: int, enviadas: int, respondidas: int}
     */
    public function correr(Propiedad $propiedad): array
    {
        $registro = Paises::de($propiedad)->registroViajeros();
        $desde = $registro?->conectadaDesde($propiedad);
        if ($registro === null || $desde === null) {
            return ['conciliadas' => 0, 'enviadas' => 0, 'respondidas' => 0];
        }
        $candado = Cache::lock('autoridad:propiedad:'.$propiedad->id, 600);
        if (! $candado->get()) {
            return ['conciliadas' => 0, 'enviadas' => 0, 'respondidas' => 0];
        }
        try {
            $conciliadas = $this->conciliar($propiedad, $registro, $desde);
            $enviadas = $this->enviar($propiedad, $registro);
            $respondidas = $this->consultar($propiedad, $registro);
            $this->avisar($propiedad);

            return ['conciliadas' => $conciliadas, 'enviadas' => $enviadas, 'respondidas' => $respondidas];
        } finally {
            $candado->release();
        }
    }

    public function conciliar(Propiedad $propiedad, RegistroViajeros $registro, Carbon $desde): int
    {
        $tocadas = 0;
        $reservas = Reserva::query()->with('propiedad')->where('propiedad_id', $propiedad->id)->where('tipo', TipoReserva::Reserva->value)
            ->where('salida', '>=', $propiedad->hoy()->subDays(self::DIAS_ATRAS)->format('Y-m-d'))
            ->where(fn ($consulta) => $consulta->where('creado_en', '>=', $desde)->orWhere('entrada_registrada_en', '>=', $desde)
                ->orWhereIn('id', EnvioAutoridad::query()->where('propiedad_id', $propiedad->id)->select('reserva_id')))
            ->get();
        foreach ($reservas as $reserva) {
            $cancelada = $reserva->estado === EstadoReserva::Cancelada;
            $conEntrada = $reserva->entrada_registrada_en !== null;
            $tocadas += $this->conciliarTipo($reserva, $registro, EnvioAutoridad::RESERVA, ! $cancelada && $reserva->creado_en >= $desde && ($reserva->estado === EstadoReserva::Confirmada || $conEntrada));
            $tocadas += $this->conciliarTipo($reserva, $registro, EnvioAutoridad::PARTE, ! $cancelada && $conEntrada && $reserva->entrada_registrada_en >= $desde);
        }

        return $tocadas;
    }

    private function conciliarTipo(Reserva $reserva, RegistroViajeros $registro, string $tipo, bool $hacePorComunicar): int
    {
        $vigente = EnvioAutoridad::query()->where('reserva_id', $reserva->id)->where('tipo', $tipo)->whereIn('estado', EstadoEnvioAutoridad::vigentes())->orderByDesc('id')->first();

        if (! $hacePorComunicar) {
            if ($vigente === null) {
                return 0;
            }
            if ($vigente->estado === EstadoEnvioAutoridad::Aceptado) {
                $vigente->update(['estado' => EstadoEnvioAutoridad::PorAnular, 'proximo_intento_en' => null]);

                return 1;
            }
            // Lo que no llegó a aceptarse no hay que anularlo. Lo que está en camino se resuelve la próxima vez, cuando se sepa.
            if ($vigente->estado !== EstadoEnvioAutoridad::Enviado) {
                $vigente->delete();

                return 1;
            }

            return 0;
        }

        if ($vigente !== null && $vigente->estado === EstadoEnvioAutoridad::Aceptado
            && $registro->armar($reserva, $tipo, (array) $vigente->correcciones)->huella !== $vigente->huella) {
            // El servicio no modifica: se anula la anterior y se manda otra.
            $vigente->update(['estado' => EstadoEnvioAutoridad::PorAnular, 'proximo_intento_en' => null]);
            $vigente = null;
        }
        if ($vigente !== null) {
            return 0;
        }

        // Una reserva se comunica al formalizarse, que puede ser días después de crearla: lo que cuenta es que ahora toca.
        $base = $tipo === EnvioAutoridad::PARTE ? $reserva->entrada_registrada_en : now('UTC');
        EnvioAutoridad::query()->create([
            'propiedad_id' => $reserva->propiedad_id,
            'reserva_id' => $reserva->id,
            'servicio' => $registro->clave(),
            'tipo' => $tipo,
            'estado' => EstadoEnvioAutoridad::Pendiente,
            'correcciones' => EnvioAutoridad::query()->where('reserva_id', $reserva->id)->orderByDesc('id')->first()?->correcciones,
            'vence_en' => Carbon::parse($base)->addHours(self::PLAZO_HORAS),
        ]);

        return 1;
    }

    public function enviar(Propiedad $propiedad, RegistroViajeros $registro): int
    {
        $enviadas = 0;
        foreach ([EnvioAutoridad::RESERVA, EnvioAutoridad::PARTE] as $tipo) {
            $listas = [];
            $envios = EnvioAutoridad::query()->with('reserva.propiedad')->where('propiedad_id', $propiedad->id)->where('tipo', $tipo)
                ->whereIn('estado', [EstadoEnvioAutoridad::Pendiente->value, EstadoEnvioAutoridad::FaltanDatos->value])
                ->where(fn ($consulta) => $consulta->whereNull('proximo_intento_en')->orWhere('proximo_intento_en', '<=', now('UTC')))
                ->orderBy('id')->get();
            foreach ($envios as $envio) {
                if ($envio->reserva === null) {
                    $envio->delete();

                    continue;
                }
                $comunicacion = $registro->armar($envio->reserva, $tipo, (array) $envio->correcciones);
                if (! $comunicacion->completa()) {
                    $envio->update(['estado' => EstadoEnvioAutoridad::FaltanDatos, 'faltan' => $comunicacion->faltan]);

                    continue;
                }
                $listas[] = [$envio, $comunicacion];
            }
            foreach (array_chunk($listas, self::POR_LOTE) as $trozo) {
                $enviadas += $this->enviarLote($propiedad, $registro, $tipo, $trozo);
            }
        }

        return $enviadas + $this->enviarAnulaciones($propiedad, $registro);
    }

    /**
     * @param  list<array{0: EnvioAutoridad, 1: Comunicacion}>  $trozo
     */
    private function enviarLote(Propiedad $propiedad, RegistroViajeros $registro, string $tipo, array $trozo): int
    {
        try {
            $lote = $registro->enviar($propiedad, $tipo, array_map(fn (array $fila): Comunicacion => $fila[1], $trozo));
        } catch (ErrorAutoridad $e) {
            foreach ($trozo as [$envio]) {
                $this->fallo($envio, $e);
            }

            return 0;
        }
        foreach ($trozo as $i => [$envio, $comunicacion]) {
            $envio->update([
                'estado' => EstadoEnvioAutoridad::Enviado,
                'lote' => $lote,
                'orden' => $i + 1,
                'huella' => $comunicacion->huella,
                'faltan' => null,
                'ultimo_error' => null,
                'intentos' => $envio->intentos + 1,
                'proximo_intento_en' => null,
                'enviado_en' => now('UTC'),
            ]);
        }
        Aviso::resolver($propiedad->id, 'autoridad_sin_conexion');

        return count($trozo);
    }

    private function enviarAnulaciones(Propiedad $propiedad, RegistroViajeros $registro): int
    {
        $envios = EnvioAutoridad::query()->where('propiedad_id', $propiedad->id)->where('estado', EstadoEnvioAutoridad::PorAnular->value)
            ->where(fn ($consulta) => $consulta->whereNull('proximo_intento_en')->orWhere('proximo_intento_en', '<=', now('UTC')))->orderBy('id')->get();
        $enviadas = 0;
        foreach ($envios->chunk(self::POR_LOTE) as $trozo) {
            $sinCodigo = $trozo->filter(fn (EnvioAutoridad $envio): bool => blank($envio->codigo_comunicacion));
            $sinCodigo->each(fn (EnvioAutoridad $envio) => $envio->update(['estado' => EstadoEnvioAutoridad::Anulado]));
            $conCodigo = $trozo->diff($sinCodigo);
            if ($conCodigo->isEmpty()) {
                continue;
            }
            try {
                $lote = $registro->anular($propiedad, $conCodigo->map(fn (EnvioAutoridad $envio): string => (string) $envio->codigo_comunicacion)->values()->all());
            } catch (ErrorAutoridad $e) {
                $conCodigo->each(fn (EnvioAutoridad $envio) => $this->fallo($envio, $e));

                continue;
            }
            $conCodigo->each(fn (EnvioAutoridad $envio) => $envio->update(['estado' => EstadoEnvioAutoridad::Anulando, 'anulacion_lote' => $lote, 'ultimo_error' => null, 'proximo_intento_en' => null]));
            $enviadas += $conCodigo->count();
        }

        return $enviadas;
    }

    private function fallo(EnvioAutoridad $envio, ErrorAutoridad $error): void
    {
        $intentos = $envio->intentos + 1;
        $envio->update([
            'intentos' => $intentos,
            'ultimo_error' => $error->getMessage(),
            'proximo_intento_en' => now('UTC')->addMinutes(self::ESPERAS[min($intentos, count(self::ESPERAS)) - 1]),
        ]);
        if ($intentos >= 3) {
            Aviso::abrir($envio->propiedad_id, 'autoridad_sin_conexion', NivelAviso::Error);
        }
    }

    public function consultar(Propiedad $propiedad, RegistroViajeros $registro): int
    {
        $respondidas = 0;
        $altas = EnvioAutoridad::query()->where('propiedad_id', $propiedad->id)->where('estado', EstadoEnvioAutoridad::Enviado->value)->whereNotNull('lote')->get()->groupBy('lote');
        $anulaciones = EnvioAutoridad::query()->where('propiedad_id', $propiedad->id)->where('estado', EstadoEnvioAutoridad::Anulando->value)->whereNotNull('anulacion_lote')->get()->groupBy('anulacion_lote');
        $lotes = array_values(array_unique([...$altas->keys()->all(), ...$anulaciones->keys()->all()]));
        foreach (array_chunk($lotes, self::LOTES_POR_CONSULTA) as $trozo) {
            try {
                $resultados = $registro->consultar($propiedad, array_map(fn (mixed $lote): string => (string) $lote, $trozo));
            } catch (ErrorAutoridad) {
                continue;
            }
            foreach ($resultados as $lote => $resultado) {
                if (! $resultado->terminado) {
                    continue;
                }
                foreach ($altas->get($lote) ?? [] as $envio) {
                    $this->aplicarAlta($envio, $resultado);
                    $respondidas++;
                }
                foreach ($anulaciones->get($lote) ?? [] as $envio) {
                    $envio->update($resultado->errorGeneral === null
                        ? ['estado' => EstadoEnvioAutoridad::Anulado, 'respondido_en' => now('UTC')]
                        : ['estado' => EstadoEnvioAutoridad::PorAnular, 'ultimo_error' => $resultado->errorGeneral, 'proximo_intento_en' => now('UTC')->addHours(1)]);
                    $respondidas++;
                }
            }
        }

        return $respondidas;
    }

    private function aplicarAlta(EnvioAutoridad $envio, ResultadoLote $resultado): void
    {
        $fila = $resultado->comunicaciones[(int) $envio->orden] ?? null;
        $codigo = $fila['codigo'] ?? null;
        if (filled($codigo)) {
            $envio->update(['estado' => EstadoEnvioAutoridad::Aceptado, 'codigo_comunicacion' => $codigo, 'ultimo_error' => null, 'respondido_en' => now('UTC')]);

            return;
        }
        $envio->update([
            'estado' => EstadoEnvioAutoridad::Rechazado,
            'ultimo_error' => mb_substr((string) ($resultado->errorGeneral ?? $fila['error'] ?? __('autoridad.error.sin_detalle')), 0, 1000),
            'respondido_en' => now('UTC'),
        ]);
    }

    /**
     * Abre o cierra los avisos según lo que quede por resolver. Un plazo de 24 horas vencido es una infracción: no puede quedar escondido en una lista.
     */
    private function avisar(Propiedad $propiedad): void
    {
        $base = EnvioAutoridad::query()->where('propiedad_id', $propiedad->id);
        $rechazadas = (clone $base)->where('estado', EstadoEnvioAutoridad::Rechazado->value)->exists();
        $rechazadas ? Aviso::abrir($propiedad->id, 'autoridad_rechazadas', NivelAviso::Error) : Aviso::resolver($propiedad->id, 'autoridad_rechazadas');
        $faltan = (clone $base)->where('estado', EstadoEnvioAutoridad::FaltanDatos->value)->exists();
        $faltan ? Aviso::abrir($propiedad->id, 'autoridad_faltan_datos', NivelAviso::Advertencia) : Aviso::resolver($propiedad->id, 'autoridad_faltan_datos');
        $atrasadas = (clone $base)->whereIn('estado', [EstadoEnvioAutoridad::Pendiente->value, EstadoEnvioAutoridad::FaltanDatos->value])->where('vence_en', '<', now('UTC'))->exists();
        $atrasadas ? Aviso::abrir($propiedad->id, 'autoridad_atrasadas', NivelAviso::Error) : Aviso::resolver($propiedad->id, 'autoridad_atrasadas');
    }
}
