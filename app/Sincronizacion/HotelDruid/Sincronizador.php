<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Sincronizacion\HotelDruid;

use App\Enums\EstadoEjecucion;
use App\Enums\EstadoPrincipal;
use App\Enums\NivelAviso;
use App\Enums\OrigenEjecucion;
use App\Models\Aviso;
use App\Models\EjecucionSincronizacion;
use App\Models\Principal;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * Ciclo completo de sincronización de una principal (subordinacion.md A4 y A5).
 */
class Sincronizador
{
    public const AVISO_FALLO = 'sincronizacion_fallida';

    public const AVISO_SIN_INICIO_SESION = 'principal_sin_inicio_sesion';

    public const AVISO_BORRADO = 'copia_no_borrada';

    public function __construct(
        private readonly Factory $http,
        private readonly ValidadorDestino $validador,
        private readonly Descompresor $descompresor,
        private readonly LectorCopia $lector,
        private readonly Proyector $proyector,
    ) {
    }

    public function cliente(Principal $principal): ClientePrincipal
    {
        return new ClientePrincipal($principal, $this->http, $this->validador);
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

        $carpeta = storage_path('app/privado/subordinacion/'.$principal->id);
        File::ensureDirectoryExists($carpeta, 0700);
        $descarga = $carpeta.'/descarga-'.$ejecucion->id.'.tmp';
        $texto = $carpeta.'/copia-'.$ejecucion->id.'.tmp';
        $incidencias = [];

        try {
            $cliente = $this->cliente($principal);
            $creada = false;
            try {
                $cliente->crearCopia();
                $creada = true;
                $bytes = $cliente->descargarCopia($descarga);
            } finally {
                // Se borra siempre que se creó: la copia lleva todos los datos de la principal (A3).
                if ($creada && ! $cliente->borrarCopia()) {
                    $incidencias['copia_no_borrada'] = 1;
                }
            }

            $comprimida = $this->descompresor->descomprimirSiHaceFalta($descarga, $texto, (int) config('alojaflow.subordinacion.tamano_maximo_descomprimido'));
            $archivo = $comprimida ? $texto : $descarga;

            $cargador = new CargadorEspejo($principal->id, $ejecucion->id, (int) config('alojaflow.subordinacion.lote'));
            $resumen = $this->lector->leer($archivo, $cargador->agregar(...));
            $cargador->vaciar();

            $generarEventos = $principal->ejecucion_vigente_id !== null;
            $resultado = $this->proyector->proyectar($principal, $ejecucion, $generarEventos);

            $this->terminarBien($principal, $ejecucion, $resumen, $resultado, (int) $bytes, (int) filesize($archivo), $incidencias);
        } catch (ErrorSincronizacion $error) {
            $this->terminarMal($principal, $ejecucion, $error, $incidencias);
        } catch (Throwable $error) {
            report($error);
            $this->terminarMal($principal, $ejecucion, new ErrorSincronizacion(CodigoError::RespuestaDesconocida, 'interno', class_basename($error)), $incidencias);
        } finally {
            File::delete([$descarga, $texto]);
            $candado->release();
        }

        return $ejecucion->refresh();
    }

    /**
     * @param  array<string, int>  $incidencias
     */
    private function terminarBien(Principal $principal, EjecucionSincronizacion $ejecucion, ResumenCopia $resumen, ResultadoProyeccion $resultado, int $bytes, int $bytesTexto, array $incidencias): void
    {
        $zona = $principal->propiedad->zona_horaria;
        $fechaCopia = $resumen->fechaCopia === null ? null
            : CarbonImmutable::createFromFormat('Y-m-d H:i:s', $resumen->fechaCopia, $zona)?->utc();

        DB::transaction(function () use ($principal, $ejecucion, $resumen, $fechaCopia): void {
            $principal->forceFill([
                'ejecucion_vigente_id' => $ejecucion->id,
                'estado' => EstadoPrincipal::Activa,
                'codigo_pausa' => null,
                'pausada_hasta' => null,
                'fallos_consecutivos' => 0,
                'version_esquema' => $resumen->version,
                'idioma_principal' => $resumen->idioma,
                'inicio_sesion_activado' => $resumen->inicioSesionActivado,
                'fecha_copia' => $fechaCopia,
                'ultima_correcta_en' => now(),
                'anio_consulta' => $resumen->anios === [] ? $principal->anio_consulta : max($resumen->anios),
                'proxima_en' => now()->addMinutes($principal->frecuencia_minutos),
            ])->save();
        });
        // El espejo anterior ya no hace falta: solo se conserva la ejecución vigente.
        DB::table('hd_filas')->where('principal_id', $principal->id)->where('ejecucion_id', '!=', $ejecucion->id)->delete();

        $ejecucion->update([
            'estado' => EstadoEjecucion::Correcta,
            'terminada_en' => now(),
            'bytes_descargados' => $bytes,
            'bytes_descomprimidos' => $bytesTexto,
            'version_esquema' => $resumen->version,
            'fecha_copia' => $fechaCopia,
            'filas' => $resumen->filas + $resultado->cuentas,
            'eventos' => $resultado->eventos,
            'incidencias' => ($resumen->incidencias + $resultado->incidencias + $incidencias) ?: null,
        ]);

        $propiedadId = $principal->propiedad_id;
        Aviso::resolver($propiedadId, self::AVISO_FALLO);
        if ($resumen->inicioSesionActivado) {
            Aviso::resolver($propiedadId, self::AVISO_SIN_INICIO_SESION);
        } else {
            Aviso::abrir($propiedadId, self::AVISO_SIN_INICIO_SESION, NivelAviso::Advertencia, ['url' => $principal->url_base]);
        }
        if (isset($incidencias['copia_no_borrada'])) {
            Aviso::abrir($propiedadId, self::AVISO_BORRADO, NivelAviso::Advertencia);
        } else {
            Aviso::resolver($propiedadId, self::AVISO_BORRADO);
        }
    }

    /**
     * @param  array<string, int>  $incidencias
     */
    private function terminarMal(Principal $principal, EjecucionSincronizacion $ejecucion, ErrorSincronizacion $error, array $incidencias): void
    {
        DB::table('hd_filas')->where('ejecucion_id', $ejecucion->id)->delete();

        $ejecucion->update([
            'estado' => EstadoEjecucion::Fallida,
            'terminada_en' => now(),
            'paso_fallido' => $error->paso,
            'codigo_error' => $error->codigo->value,
            'detalle' => mb_substr($error->getMessage(), 0, 2000),
            'incidencias' => $incidencias ?: null,
        ]);

        $fallos = $principal->fallos_consecutivos + 1;
        $cambios = ['fallos_consecutivos' => $fallos, 'proxima_en' => now()->addMinutes($principal->frecuencia_minutos)];
        if ($error->codigo->pausaLaPrincipal()) {
            $cambios += [
                'estado' => EstadoPrincipal::Pausada,
                'codigo_pausa' => $error->codigo->value,
                // El límite diario se reinicia a las 00:00 de la principal; lo demás espera al administrador.
                'pausada_hasta' => $error->codigo === CodigoError::LimiteDiario
                    ? CarbonImmutable::now($principal->propiedad->zona_horaria)->addDay()->startOfDay()->utc()
                    : null,
            ];
        }
        $principal->forceFill($cambios)->save();

        if ($error->codigo->pausaLaPrincipal() || $fallos >= (int) config('alojaflow.subordinacion.fallos_para_avisar')) {
            Aviso::abrir($principal->propiedad_id, self::AVISO_FALLO, NivelAviso::Error, [
                'codigo' => $error->codigo->value,
                'mensaje' => $error->codigo->etiqueta(),
            ]);
        }
    }
}
