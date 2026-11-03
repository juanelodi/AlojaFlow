<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Str;

/**
 * Versión, licencia, créditos y código fuente (reglas 8 y 9).
 */
class AcercaDe extends Page
{
    protected string $view = 'filament.pages.acerca-de';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInformationCircle;

    protected static ?int $navigationSort = 90;

    protected static ?string $slug = 'acerca-de';

    public static function getNavigationGroup(): ?string
    {
        return __('ui.nav.sistema');
    }

    public static function getNavigationLabel(): string
    {
        return __('ui.acerca.titulo');
    }

    public function getTitle(): string|Htmlable
    {
        return __('ui.acerca.titulo');
    }

    public function avisosTerceros(): string
    {
        $ruta = base_path('THIRD-PARTY-NOTICES.md');

        return is_file($ruta)
            ? Str::markdown((string) file_get_contents($ruta), ['html_input' => 'strip', 'allow_unsafe_links' => false])
            : '';
    }
}
