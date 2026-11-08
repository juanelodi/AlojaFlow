<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Filament\Pages\MejoraContinua;
use App\MejoraContinua\Permiso;
use App\Models\RegistroAuditoria;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Los botones del aviso «Ayuda a mejorar AlojaFlow» (D-150): sí o no, y de vuelta a donde estaba.
 */
class DecidirMejoraContinuaController
{
    public function __invoke(Request $request): RedirectResponse|Response
    {
        abort_unless(MejoraContinua::canAccess(), 403);
        $decision = $request->validate(['decision' => ['required', 'in:si,no']])['decision'];
        $permiso = $decision === 'si';
        Permiso::guardar($permiso);
        RegistroAuditoria::anotar('mejora.permiso', null, ['permiso' => $permiso, 'identificada' => false]);

        // Los botones del aviso lo piden con fetch y recargan la página.
        return $request->expectsJson() ? response()->noContent() : redirect()->back(fallback: MejoraContinua::getUrl());
    }
}
