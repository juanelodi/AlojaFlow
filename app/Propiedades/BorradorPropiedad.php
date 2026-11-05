<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Propiedades;

use App\Models\Aceptacion;
use App\Models\Propiedad;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Borra una propiedad con todo lo suyo, también los archivos del disco: los documentos de identidad
 * no deben quedarse ahí después de borrar la propiedad.
 */
final class BorradorPropiedad
{
    public function borrar(Propiedad $propiedad): void
    {
        DB::transaction(function () use ($propiedad): void {
            // Estas filas impiden borrar lo que apuntan: van primero.
            Aceptacion::query()->where('propiedad_id', $propiedad->id)->delete();
            $propiedad->delete();
        });
        Storage::disk('local')->deleteDirectory('archivos/'.$propiedad->id);
    }
}
