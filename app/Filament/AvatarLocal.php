<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Iniciales en una imagen generada aquí. Filament las pide de fábrica a ui-avatars.com, y eso le manda
 * a un tercero el nombre de cada usuario en cada visita; además, todo se sirve desde la instalación.
 */
class AvatarLocal implements AvatarProvider
{
    public function get(Model $record): string
    {
        $iniciales = Str::of(Filament::getNameForDefaultAvatar($record))
            ->trim()
            ->explode(' ')
            ->take(2)
            ->map(fn (string $parte): string => Str::upper(Str::substr($parte, 0, 1)))
            ->join('');

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><rect width="64" height="64" fill="#0f766e"/>'
            .'<text x="32" y="33" dominant-baseline="central" text-anchor="middle" font-family="system-ui,sans-serif" font-size="26" font-weight="600" fill="#fff">'
            .e($iniciales).'</text></svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
