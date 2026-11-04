<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Propiedades;

use App\Models\Aceptacion;
use App\Models\Factura;
use App\Models\Propiedad;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Borra una propiedad con todo lo suyo, también los archivos del disco: los documentos de identidad
 * no deben quedarse ahí después de borrar la propiedad.
 */
final class BorradorPropiedad
{
    public function tieneFacturas(Propiedad $propiedad): bool
    {
        return Factura::query()->where('propiedad_id', $propiedad->id)->exists();
    }

    /**
     * Las facturas se conservan por ley: solo se borran si se pide a propósito (la demostración).
     */
    public function borrar(Propiedad $propiedad, bool $conFacturas = false): void
    {
        if (! $conFacturas && $this->tieneFacturas($propiedad)) {
            throw new ErrorIndependizar(__('ui.propiedades.error_con_facturas'));
        }
        DB::transaction(function () use ($propiedad): void {
            // Estas filas impiden borrar lo que apuntan: van primero.
            Factura::query()->where('propiedad_id', $propiedad->id)->delete();
            Aceptacion::query()->where('propiedad_id', $propiedad->id)->delete();
            $propiedad->delete();
        });
        Storage::disk('local')->deleteDirectory('archivos/'.$propiedad->id);
    }
}
