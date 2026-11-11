<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Middleware\AutenticarConexion;
use App\Sincronizacion\AlojaFlow\Exportador;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * La lectura que hace una subordinada (D-073). Solo GET: por aquí no se puede cambiar nada.
 */
class CompartirPropiedadController extends Controller
{
    public function __construct(private readonly Exportador $exportador)
    {
    }

    public function estado(Request $request): JsonResponse
    {
        return response()->json($this->exportador->estado(AutenticarConexion::de($request)));
    }

    public function datos(Request $request, string $coleccion): JsonResponse
    {
        abort_unless(in_array($coleccion, Exportador::COLECCIONES, true), 404);

        return response()->json($this->exportador->pagina(AutenticarConexion::de($request), $coleccion, max(0, $request->integer('despues'))));
    }
}
