<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament;

use Filament\Support\Assets\AssetManager;
use Filament\Support\Assets\Js;

/**
 * Filament manda echo.js en todas las pantallas, aunque solo sirve para recibir avisos en vivo y AlojaFlow no los tiene
 * configurados. Son 92 KB que un teléfono baja e interpreta sin usarlos (docs/rendimiento-movil.md).
 */
final class RecursosDelPanel extends AssetManager
{
    /**
     * @param  array<string>|null  $packages
     * @return array<Js>
     */
    public function getScripts(?array $packages = null, bool $withCore = true): array
    {
        $scripts = parent::getScripts($packages, $withCore);

        return filled(config('filament.broadcasting.echo'))
            ? $scripts
            : array_filter($scripts, fn (Js $script): bool => $script->getId() !== 'echo');
    }
}
