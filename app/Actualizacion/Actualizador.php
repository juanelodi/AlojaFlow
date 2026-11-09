<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Actualizacion;

use App\Enums\EstadoActualizacion;
use App\Instalacion\CachesArranque;
use App\Instalacion\Instancia;
use App\Models\Actualizacion;
use App\Models\RegistroAuditoria;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;
use ZipArchive;

/**
 * Actualización desde el panel o la consola, en dos fases (instalacion-actualizaciones.md):
 *  1. aplicar(), con el código viejo: extrae y comprueba los archivos sin tocar nada, entra en
 *     mantenimiento, copia la base de datos e intercambia los archivos (segundos, con diario);
 *  2. finalizar(), ya con el código nuevo y en otro proceso: migraciones, cachés y fin del
 *     mantenimiento. Si las migraciones fallan se devuelven los archivos anteriores.
 * El contrato entre fases (estados, carpeta de cada actualización con su diario y las rutas de
 * ActualizacionController) no debe cambiar entre versiones: el finalizar de una versión termina lo
 * que empezó el aplicar de la anterior.
 */
class Actualizador
{
    public const COPIA_BASE_DATOS = 'base-datos.sql.gz';

    private const CANDADO = 'alojaflow:actualizacion';

    /** Lo más que puede durar una fase: si el proceso muere, el candado se suelta pasado este tiempo. */
    private const SEGUNDOS_CANDADO = 1800;

    /** Cachés de arranque que pueden nombrar clases o rutas de la versión anterior. */
    public function __construct(
        private readonly Revisor $revisor,
        private readonly Aplicador $aplicador,
        private readonly CopiaBaseDatos $copiaBaseDatos,
        private readonly ?string $raizInstalacion = null,
        private readonly ?string $carpetaDatos = null,
    ) {
    }

    public function carpeta(): string
    {
        return $this->carpetaDatos ?? storage_path('app/private/actualizaciones');
    }

    public function carpetaEntrada(): string
    {
        return $this->carpeta().'/entrada';
    }

    public function carpetaDe(Actualizacion $actualizacion): string
    {
        return $this->carpeta().'/'.$actualizacion->id;
    }

    public function raiz(): string
    {
        return $this->raizInstalacion ?? base_path();
    }

    public function versionInstalada(): string
    {
        return (string) config('alojaflow.version');
    }

    public function esCopiaGit(): bool
    {
        return is_dir($this->raiz().'/.git');
    }

    public function bloqueadaPorGit(): bool
    {
        return $this->esCopiaGit() && ! config('alojaflow.actualizaciones.permitir_en_copia_git');
    }

    /**
     * En el servicio alojado (D-162) el código lo comparten todas las instancias: un paquete instalado desde una
     * cambiaría el de todas. Las actualiza AlojaFlow App.
     */
    public function bloqueadaPorInstancia(): bool
    {
        return Instancia::activa();
    }

    /**
     * Archivos de la versión instalada según su manifiesto firmado (manifiesto.json en la raíz,
     * que dejan la instalación desde el zip y cada actualización). Null si no hay o no es válido.
     *
     * @return array<string, string>|null
     */
    public function manifiestoInstalado(): ?array
    {
        $json = @file_get_contents($this->raiz().'/'.Revisor::ARCHIVO_MANIFIESTO);
        $firma = @file_get_contents($this->raiz().'/'.Revisor::ARCHIVO_FIRMA);
        if (! is_string($json) || ! is_string($firma) || ! Firma::verificar($json, $firma, $this->clavePublica())) {
            return null;
        }
        try {
            return Manifiesto::desdeJson($json)->archivos;
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    /**
     * @return list<string> zips en la carpeta de entrada (subidos desde el panel o por FTP)
     */
    public function paquetesDisponibles(): array
    {
        $zips = glob($this->carpetaEntrada().'/*.zip') ?: [];
        $nombres = array_map('basename', $zips);
        rsort($nombres, SORT_NATURAL);

        return $nombres;
    }

    public function rutaPaquete(string $nombre): string
    {
        if (! preg_match('/^[A-Za-z0-9._\-]{1,120}\.zip$/', $nombre) || ! is_file($this->carpetaEntrada().'/'.$nombre)) {
            throw new ErrorActualizacion(__('actualizaciones.error.no_existe'));
        }

        return $this->carpetaEntrada().'/'.$nombre;
    }

    /**
     * Nombre con el que se guarda un zip subido: solo caracteres seguros.
     */
    public static function nombreSeguro(string $nombre): string
    {
        $base = trim((string) preg_replace('/[^A-Za-z0-9._\-]+/', '-', pathinfo($nombre, PATHINFO_FILENAME)), '.-');

        return mb_substr($base !== '' ? $base : 'paquete', 0, 100).'.zip';
    }

    public function revisar(string $nombre, bool $reinstalar): Revision
    {
        $revision = $this->revisor->revisar(
            $this->rutaPaquete($nombre),
            $this->raiz(),
            $this->versionInstalada(),
            $this->clavePublica(),
            $this->manifiestoInstalado(),
            $reinstalar,
        );
        if ($this->bloqueadaPorGit()) {
            $revision->error(__('actualizaciones.error.copia_git'));
        }
        if ($this->bloqueadaPorInstancia()) {
            $revision->error(__('actualizaciones.error.instancia'));
        }

        return $revision;
    }

    /**
     * Registra una actualización revisada y lista para aplicar.
     */
    public function preparar(string $nombre, bool $reinstalar, ?User $usuario): Actualizacion
    {
        if ($this->pendiente() !== null) {
            throw new ErrorActualizacion(__('actualizaciones.error.en_curso'));
        }
        $revision = $this->revisar($nombre, $reinstalar);
        if (! $revision->valida()) {
            throw new ErrorActualizacion(implode(' ', $revision->errores));
        }

        $actualizacion = Actualizacion::query()->create([
            'version_anterior' => $this->versionInstalada(),
            'version_nueva' => $revision->version,
            'estado' => EstadoActualizacion::Preparada,
            'reinstalar' => $reinstalar,
            'paquete' => $nombre,
            'paquete_sha256' => (string) hash_file('sha256', $this->rutaPaquete($nombre)),
            'resumen' => $revision->resumen(),
            'user_id' => $usuario?->id,
        ]);
        RegistroAuditoria::anotar('actualizacion.preparar', $actualizacion, ['version' => $revision->version]);

        return $actualizacion;
    }

    /**
     * Fase 1, con el código viejo. Con $secreto, el navegador que tenga la cookie de ese secreto
     * puede seguir usando la aplicación durante el mantenimiento (el del administrador).
     */
    public function aplicar(Actualizacion $actualizacion, ?string $secreto = null): void
    {
        $candado = Cache::lock(self::CANDADO, self::SEGUNDOS_CANDADO);
        if (! $candado->get()) {
            throw new ErrorActualizacion(__('actualizaciones.error.en_curso'));
        }

        try {
            // De «preparada» a «aplicando» una sola vez, aunque lleguen dos peticiones.
            if (! $this->empezar($actualizacion)) {
                throw new ErrorActualizacion(__('actualizaciones.error.estado'));
            }
            $this->aplicarArchivos($actualizacion, $secreto);
        } finally {
            $candado->release();
        }
    }

    /**
     * Fase 2, con el código nuevo: migraciones, cachés y fin del mantenimiento.
     */
    public function finalizar(Actualizacion $actualizacion): void
    {
        $candado = Cache::lock(self::CANDADO, self::SEGUNDOS_CANDADO);
        if (! $candado->get()) {
            throw new ErrorActualizacion(__('actualizaciones.error.en_curso'));
        }

        try {
            $actualizacion->refresh();
            if ($actualizacion->estado !== EstadoActualizacion::ArchivosAplicados) {
                throw new ErrorActualizacion(__('actualizaciones.error.estado'));
            }
            try {
                $this->terminar($actualizacion);
            } finally {
                Artisan::call('up');
            }
        } finally {
            $candado->release();
        }
    }

    /**
     * Una actualización que se cortó aplicando (murió el proceso): se deshacen sus cambios según
     * el diario y se sale del mantenimiento. Solo si nadie la tiene en marcha (candado libre).
     */
    public function recuperarInterrumpida(Actualizacion $actualizacion): void
    {
        $candado = Cache::lock(self::CANDADO, self::SEGUNDOS_CANDADO);
        if (! $candado->get()) {
            throw new ErrorActualizacion(__('actualizaciones.error.en_curso'));
        }

        try {
            $actualizacion->refresh();
            if ($actualizacion->estado !== EstadoActualizacion::Aplicando) {
                throw new ErrorActualizacion(__('actualizaciones.error.estado'));
            }
            $carpeta = $this->carpetaDe($actualizacion);
            $this->aplicador->deshacer($this->raiz(), $carpeta.'/anterior', $carpeta.'/diario.log');
            File::deleteDirectory($carpeta.'/nuevo');
            $this->limpiarCachesDeArranque();
            $actualizacion->update(['estado' => EstadoActualizacion::Fallida, 'detalle' => __('actualizaciones.error.interrumpida'), 'terminada_en' => now()]);
            RegistroAuditoria::anotar('actualizacion.recuperada', $actualizacion, ['version' => $actualizacion->version_nueva]);
            Artisan::call('up');
        } finally {
            $candado->release();
        }
    }

    /**
     * La actualización en marcha o sin terminar, si hay una.
     */
    public function pendiente(): ?Actualizacion
    {
        return Actualizacion::query()
            ->whereIn('estado', [EstadoActualizacion::Aplicando, EstadoActualizacion::ArchivosAplicados])
            ->latest('id')
            ->first();
    }

    public function pendienteDeFinalizar(): ?Actualizacion
    {
        return Actualizacion::query()->where('estado', EstadoActualizacion::ArchivosAplicados)->latest('id')->first();
    }

    public function clavePublica(): string
    {
        return (string) config('alojaflow.actualizaciones.clave_publica');
    }

    private function aplicarArchivos(Actualizacion $actualizacion, ?string $secreto): void
    {
        $carpeta = $this->carpetaDe($actualizacion);
        $enMantenimiento = false;
        try {
            $ruta = $this->rutaPaquete($actualizacion->paquete);
            // El zip pudo cambiar desde que se preparó: se comprueba y se revisa otra vez.
            if (! hash_equals($actualizacion->paquete_sha256, (string) hash_file('sha256', $ruta))) {
                throw new ErrorActualizacion(__('actualizaciones.error.paquete_cambiado'));
            }
            $revision = $this->revisar($actualizacion->paquete, $actualizacion->reinstalar);
            if (! $revision->valida()) {
                throw new ErrorActualizacion(implode(' ', $revision->errores));
            }

            // Lo lento, con la aplicación funcionando: sacar y comprobar los archivos nuevos.
            File::ensureDirectoryExists($carpeta, 0700);
            $this->aplicador->extraer($revision, $ruta, $carpeta.'/nuevo');

            // Con la aplicación parada: copia coherente de la base de datos e intercambio.
            $this->entrarEnMantenimiento($secreto);
            $enMantenimiento = true;
            $this->copiaBaseDatos->volcar($carpeta.'/'.self::COPIA_BASE_DATOS, $actualizacion->version_anterior);
            $actualizacion->touch();
            $cambios = $this->aplicador->intercambiar($revision, $this->raiz(), $carpeta.'/nuevo', $carpeta.'/anterior', $carpeta.'/diario.log');
            File::put($carpeta.'/'.Revisor::ARCHIVO_MANIFIESTO, $revision->manifiestoJson);
            $this->guardarFirma($ruta, $revision, $carpeta);
            $this->limpiarCachesDeArranque();

            $actualizacion->update([
                'estado' => EstadoActualizacion::ArchivosAplicados,
                'resumen' => array_merge($revision->resumen(), $cambios),
            ]);
        } catch (Throwable $e) {
            $this->aplicador->deshacer($this->raiz(), $carpeta.'/anterior', $carpeta.'/diario.log');
            $this->limpiarCachesDeArranque();
            if ($enMantenimiento) {
                Artisan::call('up');
            }
            $actualizacion->update(['estado' => EstadoActualizacion::Fallida, 'detalle' => mb_substr($e->getMessage(), 0, 2000), 'terminada_en' => now()]);
            throw $e instanceof ErrorActualizacion ? $e : new ErrorActualizacion($e->getMessage(), 0, $e);
        } finally {
            File::deleteDirectory($carpeta.'/nuevo');
        }
    }

    private function terminar(Actualizacion $actualizacion): void
    {
        $carpeta = $this->carpetaDe($actualizacion);
        try {
            $this->ejecutar('package:discover');
            $this->ejecutar('migrate', ['--force' => true]);
        } catch (Throwable $e) {
            $this->aplicador->deshacer($this->raiz(), $carpeta.'/anterior', $carpeta.'/diario.log');
            $this->limpiarCachesDeArranque();
            $detalle = mb_substr(__('actualizaciones.error.migraciones', ['detalle' => $e->getMessage()]), 0, 2000);
            $actualizacion->update(['estado' => EstadoActualizacion::Revertida, 'detalle' => $detalle, 'terminada_en' => now()]);
            RegistroAuditoria::anotar('actualizacion.revertida', $actualizacion, ['version' => $actualizacion->version_nueva]);
            throw new ErrorActualizacion($detalle, 0, $e);
        }
        $this->limpiarCaches();

        // El manifiesto firmado de la versión instalada queda en la raíz: con él, la próxima
        // actualización sabe qué archivos obsoletos puede borrar.
        File::copy($carpeta.'/'.Revisor::ARCHIVO_MANIFIESTO, $this->raiz().'/'.Revisor::ARCHIVO_MANIFIESTO);
        if (is_file($carpeta.'/'.Revisor::ARCHIVO_FIRMA)) {
            File::copy($carpeta.'/'.Revisor::ARCHIVO_FIRMA, $this->raiz().'/'.Revisor::ARCHIVO_FIRMA);
        }
        // El zip ya instalado sale de la lista de paquetes.
        $paquete = $this->carpetaEntrada().'/'.$actualizacion->paquete;
        if (is_file($paquete) && hash_equals($actualizacion->paquete_sha256, (string) hash_file('sha256', $paquete))) {
            File::delete($paquete);
        }

        CachesArranque::rehacer();
        app(EsquemaAlDia::class)->marcarAlDia();
        $actualizacion->update(['estado' => EstadoActualizacion::Completada, 'terminada_en' => now()]);
        RegistroAuditoria::anotar('actualizacion.completada', $actualizacion, ['version' => $actualizacion->version_nueva]);
    }

    /**
     * De «preparada» a «aplicando» solo si sigue preparada (lo decide la base de datos, sin carreras).
     */
    private function empezar(Actualizacion $actualizacion): bool
    {
        $filas = Actualizacion::query()->whereKey($actualizacion->getKey())->where('estado', EstadoActualizacion::Preparada)
            ->update(['estado' => EstadoActualizacion::Aplicando, 'iniciada_en' => now()]);
        $actualizacion->refresh();

        return $filas === 1;
    }

    /**
     * Mantenimiento con la página ya generada: se muestra aunque la aplicación no arranque.
     */
    private function entrarEnMantenimiento(?string $secreto): void
    {
        $opciones = ['--retry' => 60, '--refresh' => 15, '--render' => 'errors::503'];
        if ($secreto !== null) {
            $opciones['--secret'] = $secreto;
        }
        $this->ejecutar('down', $opciones);
    }

    /**
     * @param  array<string, mixed>  $parametros
     */
    private function ejecutar(string $comando, array $parametros = []): void
    {
        if (Artisan::call($comando, $parametros) !== 0) {
            throw new ErrorActualizacion($comando.': '.trim(Artisan::output()));
        }
    }

    /**
     * Cachés de vistas, configuración y componentes: si alguna no se puede borrar, la
     * actualización sigue valiendo.
     */
    private function limpiarCaches(): void
    {
        foreach (['config:clear', 'route:clear', 'event:clear', 'view:clear', 'filament:optimize-clear'] as $comando) {
            try {
                Artisan::call($comando);
            } catch (Throwable $e) {
                Log::warning('Actualización: '.$comando.': '.$e->getMessage());
            }
        }
    }

    private function limpiarCachesDeArranque(): void
    {
        CachesArranque::quitar($this->raiz());
    }

    private function guardarFirma(string $zipRuta, Revision $revision, string $carpeta): void
    {
        $zip = new ZipArchive();
        if ($zip->open($zipRuta, ZipArchive::RDONLY) === true) {
            $firma = $zip->getFromName($revision->base.'/'.Revisor::ARCHIVO_FIRMA);
            $zip->close();
            if (is_string($firma)) {
                File::put($carpeta.'/'.Revisor::ARCHIVO_FIRMA, $firma);
            }
        }
    }
}
