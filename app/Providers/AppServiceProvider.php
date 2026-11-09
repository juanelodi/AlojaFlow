<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Providers;

use App\Demo\ModoDemo;
use App\Filament\RecursosDelPanel;
use App\Filament\Tablas\OrdenAutomatico;
use App\MejoraContinua\RegistroErrores;
use App\Models\User;
use App\Programador\TareasPendientes;
use App\Seguridad\AuditoriaModelos;
use App\Seguridad\Dispositivos;
use App\Seguridad\MemoriaAcceso;
use App\Support\ClaveCliente;
use Filament\Forms\Components\Select;
use Filament\Support\Assets\AssetManager;
use Filament\Tables\Columns\Column;
use Filament\Tables\Table;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\SessionGuard;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Http\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(MemoriaAcceso::class);
        $this->app->scoped(AssetManager::class, RecursosDelPanel::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->app->terminating(fn () => $this->app->make(MemoriaAcceso::class)->vaciar());

        AuditoriaModelos::registrar();

        // Mejora continua (D-150): lo que el sistema anota como problema sin que sea una excepción (las
        // excepciones ya llegan por report()). Solo con permiso, saneado.
        Event::listen(MessageLogged::class, function (MessageLogged $registro): void {
            if (in_array($registro->level, ['warning', 'error', 'critical', 'alert', 'emergency'], true) && ! isset($registro->context['exception'])) {
                RegistroErrores::anotarProblema($registro->level, $registro->message);
            }
        });

        // Lo que corre schedule:run queda anotado: alojaflow:tareas-pendientes no lo repite (D-169).
        Event::listen(ScheduledTaskStarting::class, fn (ScheduledTaskStarting $evento) => TareasPendientes::anotar($evento->task));

        // «Mantener la sesión» usa dispositivos propios (D-076), en lugar del token único de Laravel, que no caduca.
        Event::listen(Login::class, function (Login $evento): void {
            if ($evento->remember && $evento->user instanceof User) {
                $guard = Auth::guard($evento->guard);
                if ($guard instanceof SessionGuard) {
                    Cookie::unqueue($guard->getRecallerName());
                }
                if (! ModoDemo::activo()) {
                    $this->app->make(Dispositivos::class)->recordar($evento->user, request()->userAgent(), request()->ip());
                }
            }
        });
        Event::listen(Logout::class, function (): void {
            $cookie = request()->cookie(Dispositivos::COOKIE);
            $this->app->make(Dispositivos::class)->olvidarCookie(is_string($cookie) ? $cookie : null);
        });

        // Lo mismo que pide el instalador, en el perfil y al recuperar la contraseña.
        Password::defaults(fn (): Password => Password::min(12));

        // En el teléfono, cada fila es una tarjeta con todos sus datos: una tabla ancha solo dejaba ver dos columnas.
        Table::configureUsing(fn (Table $table): Table => $table->stackedOnMobile());

        // Una X para vaciar un campo obligatorio solo lleva a un error. En uno nativo, sin esa opción vacía el navegador mostraría la primera como elegida.
        Select::configureUsing(function (Select $select): void {
            $select->selectablePlaceholder(fn (Select $component): bool => $component->isNative() || ! $component->isRequired());
        });

        // Ordenar por cualquier columna de la base, sin pedirlo una por una. Una columna puede decir lo contrario con ->sortable(false).
        Column::configureUsing(function (Column $columna): void {
            $columna->sortable(fn (Column $column): bool => OrdenAutomatico::sePuede($column));
        });

        // Con nombre: sin él, dos límites en una misma ruta comparten el contador y se suman.
        RateLimiter::for('publico', fn (Request $request): Limit => Limit::perMinute(60)->by(ClaveCliente::de($request)));
        RateLimiter::for('publico-envio', fn (Request $request): Limit => Limit::perMinute(10)->by(ClaveCliente::de($request)));
        RateLimiter::for('publico-codigo', fn (Request $request): Limit => Limit::perMinute(3)->by(ClaveCliente::de($request)));
        // Por enlace, además de por dirección: quien tenga uno filtrado no llena el disco ni manda correos sin fin.
        RateLimiter::for('llegada-envio', fn (Request $request): Limit => Limit::perHour(20)->by('llegada:'.(string) $request->route('token')));
        RateLimiter::for('llegada-codigo', fn (Request $request): Limit => Limit::perHour(6)->by('llegada:'.(string) $request->route('token')));
        // Por grupo o por reserva, además de por dirección: un enlace filtrado no llena el disco (D-097).
        RateLimiter::for('comprobante-envio', fn (Request $request): Limit => Limit::perHour(20)->by('comprobante:'.(string) ($request->route('token') ?? $request->route('publico'))));
        // Crear una reserva aparta noches: cuesta más que cualquier otro envío público.
        RateLimiter::for('motor-reserva', fn (Request $request): array => [
            Limit::perMinute(5)->by('minuto:'.ClaveCliente::de($request)),
            Limit::perHour(20)->by('hora:'.ClaveCliente::de($request)),
        ]);

        // Con la instalación en https, todos los enlaces van en https aunque un proxy hable en http.
        if ($this->app->isProduction() && str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        // La demostración pública nunca manda correos de verdad.
        if (ModoDemo::activo()) {
            config(['mail.default' => 'log']);
        }
    }
}
