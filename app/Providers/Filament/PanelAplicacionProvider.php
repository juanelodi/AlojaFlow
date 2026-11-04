<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Actualizacion\Actualizador;
use App\Demo\ModoDemo;
use App\Filament\AvatarLocal;
use App\Filament\Pages\Actualizaciones;
use App\Filament\Pages\EditarPerfil;
use App\Filament\Pages\IniciarSesion;
use App\Filament\Pages\PedirRecuperacion;
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
use Filament\Tables\Table;
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
        Table::configureUsing(fn (Table $tabla) => $tabla->defaultDateDisplayFormat('j M Y')->defaultDateTimeDisplayFormat('j M Y H:i'));
    }

    /**
     * Filament pinta los botones con el tono 600 y texto blanco, y varios de esos tonos no llegan a 4.5:1
     * con el blanco. Un tono más oscuro lo arregla. En el teal, además, deja el 600 exactamente en
     * #0F766E, el color de la marca: con uno generado desde el hexadecimal, los botones salían aguamarina.
     *
     * @param  array<int, string>  $paleta
     * @return array<int, string>
     */
    private static function recorrerTono(array $paleta): array
    {
        return [
            50 => $paleta[50], 100 => $paleta[100], 200 => $paleta[200], 300 => $paleta[300], 400 => $paleta[400],
            500 => $paleta[600], 600 => $paleta[700], 700 => $paleta[800], 800 => $paleta[900], 900 => $paleta[950], 950 => $paleta[950],
        ];
    }

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('app')
            ->path('')
            ->login(IniciarSesion::class)
            ->passwordReset(PedirRecuperacion::class)
            ->profile(EditarPerfil::class, isSimple: false)
            // En la demostración el usuario es de todos: nadie debe poder dejar fuera a los demás.
            ->multiFactorAuthentication(ModoDemo::activo() ? [] : [AppAuthentication::make()->recoverable()], isRequired: (bool) config('alojaflow.exigir_dos_pasos'))
            ->defaultAvatarProvider(AvatarLocal::class)
            ->brandName('AlojaFlow')
            ->brandLogo(asset('img/logo-alojaflow.svg'))
            ->darkModeBrandLogo(asset('img/logo-alojaflow-oscuro.svg'))
            ->brandLogoHeight('2.25rem')
            ->favicon(asset('img/icono-alojaflow.svg'))
            ->colors([
                'primary' => self::recorrerTono(Color::Teal),
                'warning' => Color::Amber,
                'danger' => self::recorrerTono(Color::Rose),
                'success' => self::recorrerTono(Color::Emerald),
                'info' => self::recorrerTono(Color::Sky),
                'gray' => Color::Slate,
            ])
            ->navigationGroups([
                NavigationGroup::make()->label(fn (): string => __('ui.nav.operacion')),
                NavigationGroup::make()->label(fn (): string => __('ui.nav.reservas')),
                NavigationGroup::make()->label(fn (): string => __('ui.nav.dinero')),
                // Lo que no se toca a diario empieza plegado: cerca de cuarenta entradas abiertas no se leen.
                NavigationGroup::make()->label(fn (): string => __('ui.nav.comunicacion'))->collapsed(),
                NavigationGroup::make()->label(fn (): string => __('ui.nav.configuracion'))->collapsed(),
                NavigationGroup::make()->label(fn (): string => __('ui.nav.sistema'))->collapsed(),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->sidebarCollapsibleOnDesktop()
            ->renderHook(PanelsRenderHook::BODY_START, fn (): View|string => ModoDemo::activo() ? view('componentes.banner-demo') : '')
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
