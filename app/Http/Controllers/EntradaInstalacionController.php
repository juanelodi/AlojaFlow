<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Filament\Pages\CrearContrasena;
use App\Filament\Pages\Resumen;
use App\Instalacion\EnlaceEntrada;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * La primera entrada después de instalar (D-071). Si el enlace ya se usó o caducó, al inicio de sesión de siempre.
 */
class EntradaInstalacionController extends Controller
{
    public function __invoke(Request $request, string $token): RedirectResponse
    {
        $usuario = EnlaceEntrada::usar($token);
        if ($usuario !== null) {
            Auth::login($usuario);
            $request->session()->regenerate();
            // Instalada sin navegador (D-164): lo primero es crear su contraseña.
            if ($usuario->debe_crear_contrasena) {
                return redirect()->to(CrearContrasena::getUrl());
            }
        }

        return redirect()->to(Resumen::getUrl());
    }
}
