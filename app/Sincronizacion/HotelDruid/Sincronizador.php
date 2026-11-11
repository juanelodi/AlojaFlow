<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
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
use App\Sincronizacion\ConFallosDeEjecucion;
use App\Sincronizacion\Subordinador;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * El ciclo completo de copiar de una principal HotelDruid o AlojaFlow 1.2.x (subordinacion.md A4): pedir la copia,
 * bajarla, pedir que la borren, leerla al espejo y proyectarla. La principal nunca se cambia, y un fallo en
 * cualquier paso deja como estaban la última carga buena y su proyección.
 */
class Sincronizador implements Subordinador
{
    use ConFallosDeEjecucion;

    public const AVISO_FALLO = 'sincronizacion_fallida';

    public const AVISO_SIN_INICIO_SESION = 'principal_sin_inicio_sesion';

    public const AVISO_BORRADO = 'copia_no_borrada';

    private const SEGUNDOS_DE_CANDADO = 1800;

    /** Lo que pasa cuando otra copia pisó la nuestra en la principal, que solo tiene un archivo para todas. */
    private const COPIA_PISADA = [CodigoError::TamanoExcedido, CodigoError::GzipDanado, CodigoError::FormatoInvalido, CodigoError::CopiaIncompleta];

    private const FILAS_POR_BORRADO = 5000;

    public function __construct(
        private readonly ValidadorDestino $validador,
        private readonly Descompresor $descompresor,
        private readonly LectorCopia $lector,
        private readonly Proyector $proyector,
    ) {
    }

    public function cliente(Principal $principal): ClientePrincipal
    {
        return new ClientePrincipal($principal, app(Factory::class), $this->validador);
    }

    public function probar(Principal $principal): void
    {
        $this->cliente($principal)->probarConexion();
    }

    public function sincronizar(Principal $principal, OrigenEjecucion $origen): EjecucionSincronizacion
    {
        $ejecucion = EjecucionSincronizacion::query()->create([
            'principal_id' => $principal->id,
            'origen' => $origen,
            'estado' => EstadoEjecucion::EnCurso,
            'iniciada_en' => now(),
        ]);

        // Una principal, una sincronización a la vez.
        $candado = Cache::lock('sincronizacion:principal:'.$principal->id, self::SEGUNDOS_DE_CANDADO);
        if (! $candado->get()) {
            $ejecucion->update([
                'estado' => EstadoEjecucion::Omitida, 'codigo_error' => CodigoError::EnCurso->value,
                'detalle' => CodigoError::EnCurso->etiqueta(), 'terminada_en' => now(),
            ]);

            return $ejecucion;
        }

        // La copia trae datos personales y secretos de la principal: vive en una carpeta privada y se borra pase lo que pase.
        $carpeta = storage_path('app/private/subordinacion/'.$principal->id);
        File::ensureDirectoryExists($carpeta, 0700);
        $descarga = $carpeta.'/copia-'.$ejecucion->id.'.descarga';
        $texto = $carpeta.'/copia-'.$ejecucion->id.'.texto';
        $fallosPrevios = $principal->fallos_consecutivos;

        try {
            $this->copiar($principal, $ejecucion, $descarga, $texto);
        } catch (ErrorSincronizacion $error) {
            $this->descartarCarga($principal, $ejecucion);
            $this->anotarFallo($principal, $ejecucion, $error);
            $this->reintentarPronto($principal, $error, $origen, $fallosPrevios);
        } catch (Throwable $error) {
            // Con los valores ya ocultos en la conexión (config/database.php): no van datos de huéspedes al registro.
            report($error);
            $this->descartarCarga($principal, $ejecucion);
            $this->anotarFallo($principal, $ejecucion, new ErrorSincronizacion(CodigoError::ErrorInterno, 'interno', class_basename($error)));
        } finally {
            File::delete([$descarga, $texto]);
            $candado->release();
        }

        return $ejecucion->refresh();
    }

    private function copiar(Principal $principal, EjecucionSincronizacion $ejecucion, string $descarga, string $texto): void
    {
        $bytes = $this->bajar($principal, $descarga);

        $limite = (int) config('alojaflow.subordinacion.tamano_maximo_descomprimido');
        $copia = $this->descompresor->descomprimirSiHaceFalta($descarga, $texto, $limite) ? $texto : $descarga;
        $bytesEnClaro = (int) filesize($copia);
        if ($bytesEnClaro > $limite) {
            throw new ErrorSincronizacion(CodigoError::TamanoExcedido, 'descomprimir');
        }

        $cargador = new CargadorEspejo($principal->id, $ejecucion->id, (int) config('alojaflow.subordinacion.lote'));
        $resumen = $this->lector->leer($copia, $cargador->agregar(...));
        $cargador->vaciar();
        File::delete([$descarga, $texto]);

        // Solo con una proyección completa detrás hay con qué comparar: antes, todo parecería nuevo (D-028).
        $generarEventos = $principal->ultima_correcta_en !== null;
        $this->darPorVigente($principal, $ejecucion);
        $resultado = $this->proyector->proyectar($principal, $ejecucion, $generarEventos);

        $this->terminarBien($principal, $ejecucion, $resumen, $resultado, $bytes, $bytesEnClaro);
    }

    /**
     * Pasos 1 a 3 del protocolo. El borrado se intenta siempre que la principal haya creado la copia, aunque la
     * descarga falle: si no, se queda en su carpeta con todos sus datos.
     */
    private function bajar(Principal $principal, string $descarga): int
    {
        $cliente = $this->cliente($principal);
        $cliente->crearCopia();
        try {
            return $cliente->descargarCopia($descarga);
        } finally {
            if (! $cliente->borrarCopia()) {
                Aviso::abrir($principal->propiedad_id, self::AVISO_BORRADO, NivelAviso::Advertencia);
            }
        }
    }

    /**
     * La carga recién leída pasa a ser la que vale, en una transacción corta, y lo de las anteriores se borra.
     */
    private function darPorVigente(Principal $principal, EjecucionSincronizacion $ejecucion): void
    {
        DB::transaction(fn () => $principal->forceFill(['ejecucion_vigente_id' => $ejecucion->id])->save());
        do {
            $borradas = DB::table('hd_filas')->where('principal_id', $principal->id)->where('ejecucion_id', '!=', $ejecucion->id)->limit(self::FILAS_POR_BORRADO)->delete();
        } while ($borradas >= self::FILAS_POR_BORRADO);
    }

    /**
     * Lo que alcanzó a cargar una ejecución que falló antes de valer no se queda en el espejo.
     */
    private function descartarCarga(Principal $principal, EjecucionSincronizacion $ejecucion): void
    {
        if ($principal->ejecucion_vigente_id !== $ejecucion->id) {
            DB::table('hd_filas')->where('ejecucion_id', $ejecucion->id)->delete();
        }
    }

    private function terminarBien(Principal $principal, EjecucionSincronizacion $ejecucion, ResumenCopia $resumen, ResultadoProyeccion $resultado, int $bytes, int $bytesEnClaro): void
    {
        $ahora = now();
        $propiedad = $principal->propiedad;
        // La hora de la copia viene en la hora local de la principal, sin zona.
        $fechaCopia = (new Conversor($propiedad->zona_horaria, $propiedad->moneda))->fechaHora($resumen->fechaCopia);

        $principal->forceFill([
            'estado' => EstadoPrincipal::Activa,
            'codigo_pausa' => null,
            'pausada_hasta' => null,
            'fallos_consecutivos' => 0,
            'version_esquema' => $resumen->version,
            'idioma_principal' => $resumen->idioma,
            'inicio_sesion_activado' => $resumen->inicioSesionActivado,
            // El año que se manda en la siguiente petición: el más reciente que tiene la principal.
            'anio_consulta' => $resumen->anios === [] ? $principal->anio_consulta : max($resumen->anios),
            'fecha_copia' => $fechaCopia,
            'ultima_correcta_en' => $ahora,
            'proxima_en' => $ahora->copy()->addMinutes($principal->frecuencia_minutos),
        ])->save();

        $incidencias = $resumen->incidencias + $resultado->incidencias;
        $ejecucion->update([
            'estado' => EstadoEjecucion::Correcta,
            'terminada_en' => $ahora,
            'bytes_descargados' => $bytes,
            'bytes_descomprimidos' => $bytesEnClaro,
            'version_esquema' => $resumen->version,
            'fecha_copia' => $fechaCopia,
            'filas' => $resumen->filas + $resultado->cuentas,
            'eventos' => $resultado->eventos,
            'incidencias' => $incidencias === [] ? null : $incidencias,
        ]);

        Aviso::resolver($principal->propiedad_id, self::AVISO_FALLO);
        // Sin inicio de sesión, cualquiera que conozca la dirección puede bajar la copia de la principal.
        if ($resumen->inicioSesionActivado) {
            Aviso::resolver($principal->propiedad_id, self::AVISO_SIN_INICIO_SESION);
        } else {
            Aviso::abrir($principal->propiedad_id, self::AVISO_SIN_INICIO_SESION, NivelAviso::Advertencia, ['url' => $principal->url_base]);
        }
    }

    /**
     * La principal escribe todas sus copias en el mismo archivo: si otra subordinada o su administrador pidieron una
     * al mismo tiempo, la nuestra llega dañada. Se vuelve a intentar una vez, entre 30 y 90 segundos después y sin
     * dejar esperando a nadie: se adelanta la siguiente vuelta en lugar de dormir el proceso.
     */
    private function reintentarPronto(Principal $principal, ErrorSincronizacion $error, OrigenEjecucion $origen, int $fallosPrevios): void
    {
        if ($origen === OrigenEjecucion::Programada && $fallosPrevios === 0 && in_array($error->codigo, self::COPIA_PISADA, true)) {
            $principal->forceFill(['proxima_en' => now()->addSeconds(random_int(30, 90))])->save();
        }
    }
}
