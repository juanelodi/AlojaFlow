<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\MejoraContinua\RegistroErrores;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Un error de JavaScript del panel (D-150). Sin permiso de mejora continua no se guarda nada.
 */
class ErrorNavegadorController
{
    public function __invoke(Request $request): Response
    {
        $datos = $request->validate([
            'tipo' => ['nullable', 'string', 'max:40'],
            'mensaje' => ['required', 'string', 'max:1000'],
            'archivo' => ['nullable', 'string', 'max:500'],
            'linea' => ['nullable', 'integer', 'min:0'],
            'pila' => ['nullable', 'string', 'max:4000'],
            'pantalla' => ['nullable', 'string', 'max:300'],
        ]);
        RegistroErrores::anotarNavegador([
            'tipo' => (string) ($datos['tipo'] ?? 'Error'),
            'mensaje' => (string) $datos['mensaje'],
            'archivo' => isset($datos['archivo']) ? (string) $datos['archivo'] : null,
            'linea' => isset($datos['linea']) ? (int) $datos['linea'] : null,
            'pila' => isset($datos['pila']) ? (string) $datos['pila'] : null,
            'pantalla' => isset($datos['pantalla']) ? (string) $datos['pantalla'] : null,
        ]);

        return response()->noContent();
    }
}
