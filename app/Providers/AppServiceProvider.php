<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Providers;

use App\Demo\ModoDemo;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Con nombre: sin él, dos límites en una misma ruta comparten el contador y se suman.
        RateLimiter::for('publico', fn (Request $request): Limit => Limit::perMinute(60)->by((string) $request->ip()));
        RateLimiter::for('publico-envio', fn (Request $request): Limit => Limit::perMinute(10)->by((string) $request->ip()));
        RateLimiter::for('publico-codigo', fn (Request $request): Limit => Limit::perMinute(3)->by((string) $request->ip()));

        // La demostración pública nunca manda correos de verdad.
        if (ModoDemo::activo()) {
            config(['mail.default' => 'log']);
        }
    }
}
