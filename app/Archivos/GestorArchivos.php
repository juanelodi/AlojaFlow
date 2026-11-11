<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Archivos;

use App\Enums\ClaseArchivo;
use App\Models\Archivo;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Guarda y lee archivos en `storage/app/private/archivos`, que entra en las copias de seguridad.
 * Los que llevan datos personales van cifrados con la llave de la aplicación (regla 12).
 */
final class GestorArchivos
{
    private const DISCO = 'local';

    public const CARPETA_SUBIDAS = 'subidas-temporales';

    public function guardar(string $contenido, string $nombreOriginal, string $tipoMime, ClaseArchivo $clase, ?Model $objeto, ?int $propiedadId, bool $cifrar = false, ?CarbonInterface $conservarHasta = null): Archivo
    {
        $ruta = 'archivos/'.($propiedadId ?? 'general').'/'.now('UTC')->format('Y/m').'/'.Str::uuid()->toString();
        Storage::disk(self::DISCO)->put($ruta, $cifrar ? Crypt::encryptString($contenido) : $contenido);

        $archivo = new Archivo([
            'propiedad_id' => $propiedadId,
            'ruta' => $ruta,
            'nombre_original' => mb_substr(basename($nombreOriginal), 0, 255),
            'tipo_mime' => mb_substr($tipoMime, 0, 100),
            'bytes' => strlen($contenido),
            'sha256' => hash('sha256', $contenido),
            'cifrado' => $cifrar,
            'clase' => $clase,
            'subido_por' => auth()->id(),
            'conservar_hasta' => $conservarHasta,
        ]);
        if ($objeto !== null) {
            $archivo->objeto()->associate($objeto);
        }
        $archivo->save();

        return $archivo;
    }

    /**
     * Toma un archivo que Livewire dejó en el disco privado, lo guarda con los demás y borra el temporal.
     * La ruta pasa por el navegador: fuera de la carpeta de subidas se rechaza, porque lo que se toma
     * aquí se borra después y sería una forma de leer y destruir archivos privados de otros.
     */
    public function guardarSubida(string $rutaTemporal, ClaseArchivo $clase, ?Model $objeto, ?int $propiedadId, bool $cifrar = false, ?CarbonInterface $conservarHasta = null): Archivo
    {
        if (! str_starts_with($rutaTemporal, self::CARPETA_SUBIDAS.'/') || str_contains($rutaTemporal, '..')) {
            throw new InvalidArgumentException('Ruta de subida no válida.');
        }
        $disco = Storage::disk(self::DISCO);
        $archivo = $this->guardar((string) $disco->get($rutaTemporal), basename($rutaTemporal), (string) $disco->mimeType($rutaTemporal), $clase, $objeto, $propiedadId, $cifrar, $conservarHasta);
        $disco->delete($rutaTemporal);

        return $archivo;
    }

    public function contenido(Archivo $archivo): string
    {
        $contenido = (string) Storage::disk(self::DISCO)->get($archivo->ruta);

        return $archivo->cifrado ? Crypt::decryptString($contenido) : $contenido;
    }

    public function eliminar(Archivo $archivo): void
    {
        Storage::disk(self::DISCO)->delete($archivo->ruta);
        $archivo->delete();
    }
}
