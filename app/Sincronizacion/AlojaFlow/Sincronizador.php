<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Sincronizacion\AlojaFlow;

use App\Enums\EstadoEjecucion;
use App\Enums\EstadoPrincipal;
use App\Enums\OrigenEjecucion;
use App\Models\Aviso;
use App\Models\EjecucionSincronizacion;
use App\Models\Principal;
use App\Sincronizacion\ConFallosDeEjecucion;
use App\Sincronizacion\HotelDruid\CodigoError;
use App\Sincronizacion\HotelDruid\ErrorSincronizacion;
use App\Sincronizacion\HotelDruid\Sincronizador as SincronizadorHotelDruid;
use App\Sincronizacion\HotelDruid\ValidadorDestino;
use App\Sincronizacion\Subordinador;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * Ciclo completo de copiar una propiedad de otra instalación de AlojaFlow (D-073): pregunta si cambió algo, baja lo que hay a archivos
 * temporales y lo proyecta en una sola transacción. Si se corta a la mitad, lo anterior sigue intacto.
 */
class Sincronizador implements Subordinador
{
    use ConFallosDeEjecucion;

    /** Sin cambios, cada cuánto se baja todo de todos modos: la revisión no ve cualquier cambio (un campo personalizado, por ejemplo). */
    private const HORAS_SIN_COMPROBAR = 6;

    public function __construct(
        private readonly Factory $http,
        private readonly ValidadorDestino $validador,
        private readonly Proyector $proyector,
    ) {
    }

    public function cliente(Principal $principal): ClienteRemoto
    {
        return new ClienteRemoto($principal, $this->http, $this->validador);
    }

    public function probar(Principal $principal): void
    {
        $this->cliente($principal)->estado();
    }

    public function sincronizar(Principal $principal, OrigenEjecucion $origen): EjecucionSincronizacion
    {
        $ejecucion = EjecucionSincronizacion::query()->create([
            'principal_id' => $principal->id,
            'origen' => $origen,
            'estado' => EstadoEjecucion::EnCurso,
            'iniciada_en' => now(),
        ]);

        $candado = Cache::lock('sincronizacion:principal:'.$principal->id, 1800);
        if (! $candado->get()) {
            $ejecucion->update([
                'estado' => EstadoEjecucion::Omitida, 'codigo_error' => CodigoError::EnCurso->value,
                'detalle' => CodigoError::EnCurso->etiqueta(), 'terminada_en' => now(),
            ]);

            return $ejecucion;
        }

        $carpeta = storage_path('app/private/subordinacion/'.$principal->id);
        File::ensureDirectoryExists($carpeta, 0700);
        $archivos = [];
        foreach (Exportador::COLECCIONES as $coleccion) {
            $archivos[$coleccion] = $carpeta.'/'.$coleccion.'-'.$ejecucion->id.'.tmp';
        }

        try {
            $cliente = $this->cliente($principal);
            $estado = $cliente->estado();
            $bytes = 0;
            if ($this->puedeSaltarse($principal, $estado['revision'], $origen)) {
                $this->terminarBien($principal, $ejecucion, $estado, false, [], 0);
            } else {
                // Una principal anterior a la 1.0.15 no dice qué ofrece, y solo tiene las cuatro primeras.
                $archivos = array_intersect_key($archivos, array_flip($estado['colecciones']));
                foreach ($archivos as $coleccion => $archivo) {
                    $bytes += $this->bajar($cliente, $coleccion, $archivo);
                }
                $generarEventos = $principal->ejecucion_vigente_id !== null;
                $resultado = $this->proyector->proyectar($principal, $ejecucion, $estado['propiedad'], $archivos, $generarEventos);
                $this->terminarBien($principal, $ejecucion, $estado, true, $resultado->cuentas, $bytes, $resultado->incidencias, $resultado->eventos);
            }
        } catch (ErrorSincronizacion $error) {
            $this->anotarFallo($principal, $ejecucion, $error);
        } catch (Throwable $error) {
            // Con los valores ya ocultos en la conexión (config/database.php): no van datos de huéspedes al registro.
            report($error);
            $this->anotarFallo($principal, $ejecucion, new ErrorSincronizacion(CodigoError::ErrorInterno, 'interno', class_basename($error)));
        } finally {
            File::delete(array_values($archivos));
            $candado->release();
        }

        return $ejecucion->refresh();
    }

    private function puedeSaltarse(Principal $principal, string $revision, OrigenEjecucion $origen): bool
    {
        return $origen === OrigenEjecucion::Programada
            && $principal->ejecucion_vigente_id !== null
            && $principal->revision_origen !== null
            && hash_equals($principal->revision_origen, $revision)
            && $principal->ultima_bajada_en !== null
            && $principal->ultima_bajada_en->gt(now()->subHours(self::HORAS_SIN_COMPROBAR));
    }

    /**
     * Toda la colección a un archivo, una fila por línea. Lleva datos personales: se borra al terminar, pase lo que pase.
     */
    private function bajar(ClienteRemoto $cliente, string $coleccion, string $archivo): int
    {
        $limite = (int) config('alojaflow.subordinacion.tamano_maximo_descomprimido');
        $salida = fopen($archivo, 'wb');
        if ($salida === false) {
            throw new ErrorSincronizacion(CodigoError::RespuestaDesconocida, 'descargar');
        }
        $bytes = 0;
        try {
            $despues = 0;
            do {
                $pagina = $cliente->pagina($coleccion, $despues);
                foreach ($pagina['filas'] as $fila) {
                    $linea = json_encode($fila, JSON_UNESCAPED_UNICODE)."\n";
                    $bytes += strlen($linea);
                    if ($bytes > $limite) {
                        throw new ErrorSincronizacion(CodigoError::TamanoExcedido, 'descargar');
                    }
                    fwrite($salida, $linea);
                }
                $despues = $pagina['siguiente'] ?? 0;
            } while ($pagina['siguiente'] !== null);
        } finally {
            fclose($salida);
        }

        return $bytes;
    }

    /**
     * @param  array{api: int, version: string, revision: string, colecciones: list<string>, propiedad: array<string, mixed>}  $estado
     * @param  array<string, int>  $cuentas
     * @param  array<string, int>  $incidencias
     */
    private function terminarBien(Principal $principal, EjecucionSincronizacion $ejecucion, array $estado, bool $bajo, array $cuentas, int $bytes, array $incidencias = [], int $eventos = 0): void
    {
        $ahora = now();
        $principal->forceFill([
            'ejecucion_vigente_id' => $ejecucion->id,
            'estado' => EstadoPrincipal::Activa,
            'codigo_pausa' => null,
            'pausada_hasta' => null,
            'fallos_consecutivos' => 0,
            'version_esquema' => mb_substr($estado['version'], 0, 10),
            'idioma_principal' => mb_substr((string) ($estado['propiedad']['idioma'] ?? ''), 0, 12) ?: null,
            'inicio_sesion_activado' => true,
            'fecha_copia' => $ahora,
            'ultima_correcta_en' => $ahora,
            'revision_origen' => $estado['revision'],
            'ultima_bajada_en' => $bajo ? $ahora : $principal->ultima_bajada_en,
            'proxima_en' => $ahora->copy()->addMinutes($principal->frecuencia_minutos),
        ])->save();

        $ejecucion->update([
            'estado' => EstadoEjecucion::Correcta,
            'terminada_en' => $ahora,
            'bytes_descargados' => $bytes,
            'version_esquema' => mb_substr($estado['version'], 0, 10),
            'fecha_copia' => $ahora,
            'filas' => $cuentas ?: null,
            'eventos' => $eventos,
            'incidencias' => $incidencias ?: null,
        ]);
        Aviso::resolver($principal->propiedad_id, SincronizadorHotelDruid::AVISO_FALLO);
    }
}
