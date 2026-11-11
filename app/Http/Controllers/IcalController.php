<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\TipoEnlace;
use App\Ical\GestorIcal;
use App\Models\CalendarioExportado;
use App\Models\EnlacePublico;
use Symfony\Component\HttpFoundation\Response;

/**
 * El calendario de una unidad para los portales: público, con un token, y solo con fechas.
 */
class IcalController extends Controller
{
    public function __invoke(string $token, GestorIcal $ical): Response
    {
        $enlace = EnlacePublico::buscar(TipoEnlace::Ical, $token);
        abort_if($enlace === null, 404);
        $calendario = CalendarioExportado::query()->with('unidad.propiedad')->where('enlace_publico_id', $enlace->id)->where('activo', true)->first();
        abort_if($calendario === null, 404);
        $enlace->anotarUso();

        return response($ical->exportar($calendario), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="calendario.ics"',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }
}
