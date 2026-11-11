<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Publico;

use App\Archivos\GestorArchivos;
use App\Comprobantes\ImagenComprobante;
use App\Enums\ClaseArchivo;
use App\Models\Foto;
use App\Models\Propiedad;
use Illuminate\Support\Facades\DB;

/**
 * Las fotos de la página pública. Se vuelven a dibujar, igual que los comprobantes: de una foto de teléfono no se publica ni su ubicación ni lo que traiga escondido
 * (D-104). Se guardan dos tamaños, porque en un teléfono no debe bajar una imagen de varios megas.
 */
final class GestorFotos
{
    private const LADO_MINIATURA = 640;

    public function __construct(private readonly GestorArchivos $archivos)
    {
    }

    /**
     * @throws \App\Comprobantes\ComprobanteRechazado si no es una imagen que se pueda usar
     */
    public function recibir(string $contenido, Propiedad $propiedad, ?int $tarifaId, ?string $texto = null): Foto
    {
        $limpia = ImagenComprobante::limpiar($contenido);
        $miniatura = $this->reducir($limpia);

        return DB::transaction(function () use ($limpia, $miniatura, $propiedad, $tarifaId, $texto): Foto {
            $foto = Foto::query()->create([
                'propiedad_id' => $propiedad->id,
                'tarifa_id' => $tarifaId,
                'texto_alternativo' => filled($texto) ? mb_substr((string) $texto, 0, 200) : null,
                'orden' => (int) Foto::query()->where('propiedad_id', $propiedad->id)->where('tarifa_id', $tarifaId)->max('orden') + 1,
            ]);
            $foto->update([
                'archivo_id' => $this->archivos->guardar($limpia, 'foto-'.$foto->id.'.jpg', 'image/jpeg', ClaseArchivo::FotoPublica, $foto, $propiedad->id)->id,
                'miniatura_id' => $this->archivos->guardar($miniatura, 'foto-'.$foto->id.'-chica.jpg', 'image/jpeg', ClaseArchivo::FotoPublica, $foto, $propiedad->id)->id,
            ]);

            return $foto;
        });
    }

    public function eliminar(Foto $foto): void
    {
        foreach ([$foto->archivo, $foto->miniatura] as $archivo) {
            if ($archivo !== null) {
                $this->archivos->eliminar($archivo);
            }
        }
        $foto->delete();
    }

    private function reducir(string $jpeg): string
    {
        $imagen = imagecreatefromstring($jpeg);
        if ($imagen === false) {
            return $jpeg;
        }
        $ancho = imagesx($imagen);
        $alto = imagesy($imagen);
        $escala = self::LADO_MINIATURA / max($ancho, $alto);
        if ($escala >= 1) {
            return $jpeg;
        }
        $chica = imagescale($imagen, max(1, (int) round($ancho * $escala)), max(1, (int) round($alto * $escala)));
        if ($chica === false) {
            return $jpeg;
        }
        ob_start();
        imagejpeg($chica, null, 80);

        return (string) ob_get_clean();
    }
}
