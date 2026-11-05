<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Actualizacion;

use FilesystemIterator;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SensitiveParameter;
use SplFileInfo;
use ZipArchive;

/**
 * Construye el paquete de una versión (instalacion-actualizaciones.md §2): los archivos del
 * repositorio, vendor/ sin dependencias de desarrollo y los recursos que publica Filament, con su
 * manifiesto firmado. El mismo zip sirve para instalar desde cero y para actualizar.
 */
class Empaquetador
{
    /** Del repositorio no se instala: la principal simulada y demás herramientas de desarrollo. */
    private const EXCLUIR = ['desarrollo/'];

    /**
     * Recursos que no están en git y la aplicación necesita: los publican `filament:assets` y `vendor:publish`. Con Livewire en
     * public/, su JavaScript lo sirve Apache: si no, cada primera visita arranca PHP entero para mandarlo.
     */
    public const RECURSOS = ['public/css/filament', 'public/js/filament', 'public/fonts/filament', 'public/css/alojaflow', 'public/vendor/livewire'];

    /**
     * @param  callable(string): void  $informar
     * @return array{zip: string, sha256: string, archivos: int, bytes: int, instalador: string, instalador_completo: string}
     */
    public function empaquetar(
        string $raiz,
        string $version,
        #[SensitiveParameter] string $claveSecreta,
        string $clavePublica,
        string $composer,
        string $carpetaSalida,
        string $carpetaTrabajo,
        callable $informar,
    ): array {
        $raiz = rtrim($raiz, '/\\');
        if (! Manifiesto::versionValida($version)) {
            throw new RuntimeException("Versión no válida: $version");
        }
        self::comprobarClaves($claveSecreta, $clavePublica);

        $imagen = $carpetaTrabajo.'/'.Manifiesto::carpetaBase($version);
        File::deleteDirectory($carpetaTrabajo);
        File::ensureDirectoryExists($imagen);

        try {
            $informar('Archivos del repositorio…');
            foreach ($this->archivosDelRepositorio($raiz) as $ruta) {
                File::ensureDirectoryExists(dirname($imagen.'/'.$ruta));
                if (! copy($raiz.'/'.$ruta, $imagen.'/'.$ruta)) {
                    throw new RuntimeException("No se pudo copiar $ruta");
                }
            }

            $informar('Dependencias sin las de desarrollo (composer install --no-dev)…');
            $proceso = Process::path($imagen)->timeout(1800)->env(['XDEBUG_MODE' => 'off'])->run([
                PHP_BINARY, $composer, 'install', '--no-dev', '--optimize-autoloader', '--no-scripts',
                '--no-interaction', '--no-progress', '--prefer-dist',
            ]);
            if ($proceso->failed()) {
                throw new RuntimeException("composer install falló:\n".$proceso->errorOutput().$proceso->output());
            }
            // Los ejecutables de vendor/bin no hacen falta para usar AlojaFlow.
            File::deleteDirectory($imagen.'/vendor/bin');

            $informar('Recursos de Filament y Livewire…');
            foreach (self::RECURSOS as $carpeta) {
                if (! is_dir($raiz.'/'.$carpeta)) {
                    throw new RuntimeException("Falta $carpeta: ejecuta php artisan filament:assets y php artisan vendor:publish --tag=livewire:assets");
                }
                File::copyDirectory($raiz.'/'.$carpeta, $imagen.'/'.$carpeta);
            }

            $informar('Manifiesto, firma y zip…');
            File::ensureDirectoryExists($carpetaSalida);
            $zip = $carpetaSalida.'/'.Manifiesto::carpetaBase($version).'.zip';

            $extensiones = array_values((array) config('alojaflow.actualizaciones.extensiones'));
            $resultado = $this->comprimir($imagen, $version, $claveSecreta, $zip, $this->phpMinimo($raiz), $extensiones);

            $informar('Instaladores de un solo archivo…');
            // El chico baja el zip al instalar (D-071); el completo lo lleva dentro, para el hosting que no sale a internet.
            $resultado['instalador'] = $this->construirInstalador(
                $raiz.'/instalador/instalar.php',
                null,
                $version,
                $clavePublica,
                $this->phpMinimo($raiz),
                $extensiones,
                $carpetaSalida.'/instalar-alojaflow-'.$version.'.php',
            );
            $resultado['instalador_completo'] = $this->construirInstalador(
                $raiz.'/instalador/instalar.php',
                $zip,
                $version,
                $clavePublica,
                $this->phpMinimo($raiz),
                $extensiones,
                $carpetaSalida.'/instalar-alojaflow-'.$version.'-completo.php',
            );

            return $resultado;
        } finally {
            File::deleteDirectory($carpetaTrabajo);
        }
    }

    /**
     * Manifiesto, firma y zip de una carpeta que ya tiene la instalación completa. Deja también
     * «<zip>.sha256».
     *
     * @param  list<string>  $extensiones
     * @return array{zip: string, sha256: string, archivos: int, bytes: int}
     */
    public function comprimir(string $imagen, string $version, #[SensitiveParameter] string $claveSecreta, string $zip, string $phpMinimo = '8.3.0', array $extensiones = []): array
    {
        $imagen = rtrim($imagen, '/\\');
        $archivos = [];
        $bytes = 0;
        $iterador = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($imagen, FilesystemIterator::SKIP_DOTS));
        /** @var SplFileInfo $archivo */
        foreach ($iterador as $archivo) {
            $ruta = str_replace('\\', '/', substr($archivo->getPathname(), strlen($imagen) + 1));
            if ($archivo->isLink()) {
                throw new RuntimeException("Un paquete no puede llevar enlaces simbólicos: $ruta");
            }
            if (! $archivo->isFile() || in_array($ruta, [Revisor::ARCHIVO_MANIFIESTO, Revisor::ARCHIVO_FIRMA], true)) {
                continue;
            }
            if (! Rutas::valida($ruta)) {
                throw new RuntimeException("Nombre de archivo no admitido en un paquete: $ruta");
            }
            $archivos[$ruta] = (string) hash_file('sha256', $archivo->getPathname());
            $bytes += $archivo->getSize();
        }

        $manifiesto = new Manifiesto($version, now()->toDateString(), $phpMinimo, $extensiones, $archivos);
        $json = $manifiesto->aJson();
        File::put($imagen.'/'.Revisor::ARCHIVO_MANIFIESTO, $json);
        File::put($imagen.'/'.Revisor::ARCHIVO_FIRMA, Firma::firmar($json, $claveSecreta)."\n");

        $temporal = $zip.'.'.bin2hex(random_bytes(4)).'.tmp';
        $salida = new ZipArchive();
        if ($salida->open($temporal, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("No se pudo crear $zip");
        }
        $base = Manifiesto::carpetaBase($version);
        foreach ([...array_keys($archivos), Revisor::ARCHIVO_MANIFIESTO, Revisor::ARCHIVO_FIRMA] as $ruta) {
            $nombre = $base.'/'.$ruta;
            // En Windows addFile() no admite rutas de más de 260 caracteres: esos se añaden
            // desde su contenido (PHP sí los lee).
            $anadido = $salida->addFile($imagen.'/'.$ruta, $nombre)
                || $salida->addFromString($nombre, (string) file_get_contents($imagen.'/'.$ruta));
            // Archivo normal con permisos 644 al descomprimirlo en Linux.
            if (! $anadido || ! $salida->setExternalAttributesName($nombre, ZipArchive::OPSYS_UNIX, 0100644 << 16)) {
                $salida->close();
                @unlink($temporal);
                throw new RuntimeException("No se pudo añadir $ruta al zip");
            }
        }
        if (! $salida->close()) {
            @unlink($temporal);
            throw new RuntimeException("No se pudo escribir $zip");
        }
        File::move($temporal, $zip);

        $sha256 = (string) hash_file('sha256', $zip);
        File::put($zip.'.sha256', $sha256.'  '.basename($zip)."\n");

        return ['zip' => $zip, 'sha256' => $sha256, 'archivos' => count($archivos), 'bytes' => $bytes];
    }

    /**
     * El instalador: un solo archivo que subir al hosting (instalacion-actualizaciones.md §2). Con $zip, lo lleva
     * dentro, después de __halt_compiler; sin él, sabe de dónde bajarlo.
     *
     * @param  list<string>  $extensiones
     */
    public function construirInstalador(string $plantilla, ?string $zip, string $version, string $clavePublica, string $phpMinimo, array $extensiones, string $salida): string
    {
        $codigo = rtrim(strtr((string) file_get_contents($plantilla), [
            '@@VERSION@@' => $version,
            '@@CLAVE_PUBLICA@@' => trim($clavePublica),
            '@@PHP_MINIMO@@' => $phpMinimo,
            '@@EXTENSIONES@@' => implode(',', $extensiones),
            '@@URL_PAQUETE@@' => str_replace(':version', $version, (string) config('alojaflow.actualizaciones.url_paquete')),
        ]));
        if (! str_ends_with($codigo, '__halt_compiler();')) {
            throw new RuntimeException('La plantilla del instalador debe terminar en __halt_compiler();');
        }

        $destino = fopen($salida, 'wb');
        $origen = $zip === null ? null : fopen($zip, 'rb');
        if ($destino === false || $origen === false) {
            throw new RuntimeException("No se pudo escribir $salida");
        }
        fwrite($destino, $codigo);
        if ($origen !== null) {
            stream_copy_to_stream($origen, $destino);
            fclose($origen);
        }
        fclose($destino);

        return $salida;
    }

    /**
     * La clave secreta debe ser la pareja de la pública que llevan las instalaciones: si no, los
     * paquetes no se podrían instalar.
     */
    public static function comprobarClaves(#[SensitiveParameter] string $claveSecreta, string $clavePublica): void
    {
        $secreta = base64_decode(trim($claveSecreta), true);
        if ($secreta === false || strlen($secreta) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
            throw new RuntimeException('La clave secreta de firma no es válida.');
        }
        if (! hash_equals(base64_encode(sodium_crypto_sign_publickey_from_secretkey($secreta)), trim($clavePublica))) {
            throw new RuntimeException('La clave secreta no es la pareja de la clave pública de config/alojaflow.php.');
        }
    }

    /**
     * Lo que va en el paquete del repositorio: lo versionado y lo nuevo aún sin versionar (si se
     * empaqueta con cambios), salvo lo ignorado y las herramientas de desarrollo.
     *
     * @return list<string>
     */
    private function archivosDelRepositorio(string $raiz): array
    {
        $proceso = Process::path($raiz)->run(['git', 'ls-files', '-z', '--cached', '--others', '--exclude-standard']);
        if ($proceso->failed()) {
            throw new RuntimeException("git ls-files falló:\n".$proceso->errorOutput());
        }

        $archivos = [];
        foreach (explode("\0", trim($proceso->output(), "\0")) as $ruta) {
            if ($ruta === '' || ! is_file($raiz.'/'.$ruta) || in_array($ruta, [Revisor::ARCHIVO_MANIFIESTO, Revisor::ARCHIVO_FIRMA], true)) {
                continue; // borrado y aún sin confirmar, o el manifiesto de una instalación
            }
            foreach (self::EXCLUIR as $prefijo) {
                if (str_starts_with($ruta, $prefijo)) {
                    continue 2;
                }
            }
            if (! Rutas::valida($ruta)) {
                throw new RuntimeException("Nombre de archivo no admitido en un paquete: $ruta");
            }
            $archivos[] = $ruta;
        }

        return $archivos;
    }

    /**
     * La versión mínima de PHP según composer.json («^8.3» → 8.3.0).
     */
    private function phpMinimo(string $raiz): string
    {
        $composer = json_decode((string) file_get_contents($raiz.'/composer.json'), true);
        $restriccion = is_array($composer) ? (string) ($composer['require']['php'] ?? '') : '';
        if (! preg_match('/(\d+)\.(\d+)(?:\.(\d+))?/', $restriccion, $m)) {
            return '8.3.0';
        }

        return $m[1].'.'.$m[2].'.'.($m[3] ?? '0');
    }
}
