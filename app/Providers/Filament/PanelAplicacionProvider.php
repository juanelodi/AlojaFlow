<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Actualizacion\Actualizador;
use App\Filament\Pages\Actualizaciones;
use App\Filament\Pages\EditarPerfil;
use App\Http\Middleware\AplicarIdioma;
use App\Seguridad\Acceso;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Assets\Css;
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentAsset;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\URL;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * El panel de AlojaFlow: inicio de sesión con verificación en dos pasos, pantallas operativas
 * propias y administración con Filament (D-010, D-026).
 */
class PanelAplicacionProvider extends PanelProvider
{
    public function boot(): void
    {
        FilamentAsset::register([Css::make('alojaflow', resource_path('css/alojaflow.css'))], 'alojaflow');
    }

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('app')
            ->path('')
            ->login()
            ->profile(EditarPerfil::class, isSimple: false)
            ->multiFactorAuthentication([AppAuthentication::make()->recoverable()])
            ->brandName('AlojaFlow')
            ->brandLogo(asset('img/logo-alojaflow.svg'))
            ->darkModeBrandLogo(asset('img/logo-alojaflow-oscuro.svg'))
            ->brandLogoHeight('2.25rem')
            ->favicon(asset('img/icono-alojaflow.svg'))
            ->colors([
                'primary' => Color::hex('#0f766e'),
                'warning' => Color::Amber,
                'danger' => Color::Rose,
                'success' => Color::Emerald,
                'info' => Color::Sky,
                'gray' => Color::Slate,
            ])
            ->navigationGroups([
                NavigationGroup::make()->label(fn (): string => __('ui.nav.operacion')),
                NavigationGroup::make()->label(fn (): string => __('ui.nav.datos')),
                NavigationGroup::make()->label(fn (): string => __('ui.nav.configuracion')),
                NavigationGroup::make()->label(fn (): string => __('ui.nav.sistema')),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->sidebarCollapsibleOnDesktop()
            ->renderHook(PanelsRenderHook::FOOTER, fn () => view('componentes.pie'))
            ->renderHook(PanelsRenderHook::CONTENT_START, fn (): View|string => self::avisoActualizacionSinTerminar())
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                AplicarIdioma::class,
            ]);
    }

    /**
     * Durante el mantenimiento de una actualización solo entra el administrador que la empezó (con
     * su cookie): si la segunda fase no terminó, ve en todas las pantallas el enlace para terminarla.
     */
    public static function avisoActualizacionSinTerminar(): View|string
    {
        if (! app()->isDownForMaintenance() || ! Acceso::usuarioActual()?->es_administrador
            || request()->routeIs(Actualizaciones::getRouteName())) {
            return '';
        }
        $pendiente = app(Actualizador::class)->pendienteDeFinalizar();

        return $pendiente === null ? '' : view('filament.avisos.actualizacion-sin-terminar', [
            'enlace' => URL::temporarySignedRoute('actualizaciones.finalizar', now()->addHour(), ['actualizacion' => $pendiente->id]),
        ]);
    }
}
