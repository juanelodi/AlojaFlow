<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

use App\Actualizacion\EsquemaAlDia;
use App\Demo\ModoDemo;
use App\Instalacion\Instancia;
use App\Modulos\Modulo;
use App\Modulos\Modulos;
use App\Programador\MantenimientoEnCadaTarea;
use App\Programador\TareasPorVisita;
use App\Seguridad\AccesoSoporte;
use Illuminate\Support\Facades\Schedule;

// Un solo cron lanza el programador (hosting compartido; CLAUDE.md). Si no hay cron, las mismas
// tareas corren al usar el sistema: App\Programador\TareasPorVisita.
// Primero, la base al día si el código cambió sin pasar por el navegador (D-171); tareas-pendientes ya lo hace antes.
Schedule::call(fn () => app(EsquemaAlDia::class)->antesDeLasTareas())->name('esquema')->everyMinute()
    ->when(fn (): bool => app(EsquemaAlDia::class)->hayQueRevisar());
Schedule::call(fn () => TareasPorVisita::anotarLatido())->name('latido')->everyMinute()->evenInMaintenanceMode();
Schedule::command('alojaflow:sincronizar')->everyMinute()->withoutOverlapping(60)->when(fn (): bool => Modulos::activo(Modulo::Subordinacion));
// El soporte de AlojaFlow sale al vencer su plazo (D-166); al entrar ya se revisa, esto lo deja desactivado.
Schedule::call(fn () => app(AccesoSoporte::class)->vencer())->name('soporte-vencer')->everyMinute()->when(fn (): bool => Instancia::activa());
Schedule::command('alojaflow:reservas-vencidas')->everyFiveMinutes()->withoutOverlapping(10);
Schedule::command('alojaflow:limpieza')->everyFifteenMinutes()->withoutOverlapping(30)->when(fn (): bool => Modulos::activo(Modulo::Limpieza));
Schedule::command('alojaflow:mensajes')->everyFiveMinutes()->withoutOverlapping(15);
Schedule::command('alojaflow:ical')->everyFifteenMinutes()->withoutOverlapping(30)->when(fn (): bool => Modulos::activo(Modulo::Ical));
Schedule::command('alojaflow:limpiar')->dailyAt('03:30');
Schedule::command('alojaflow:autoridades')->everyTenMinutes();
Schedule::command('alojaflow:grupos')->dailyAt('03:40')->when(fn (): bool => Modulos::activo(Modulo::Grupos));
Schedule::command('alojaflow:copia --programada')->dailyAt('03:10')->withoutOverlapping(60)->skip(fn (): bool => ModoDemo::activo());
// En una instancia del servicio alojado (D-162) las actualizaciones las hace AlojaFlow App.
Schedule::command('alojaflow:buscar-version')->dailyAt('03:50')->skip(fn (): bool => ModoDemo::activo() || Instancia::activa());
// Actualizaciones sin nadie delante (D-151): la urgente en cuanto se sabe; la automática, a las 3:00 de la instalación.
// En un minuto múltiplo de 10: hay hostings (DreamHost) que solo permiten el cron cada 10 minutos.
Schedule::command('alojaflow:actualizacion-automatica')->hourlyAt(20)->withoutOverlapping(120)->skip(fn (): bool => ModoDemo::activo() || Instancia::activa());
// Mejora continua (D-150): solo con permiso, salvo los reportes que alguien escribió.
Schedule::command('alojaflow:mejora-continua')->hourlyAt(40)->withoutOverlapping(30)->skip(fn (): bool => ModoDemo::activo());
Schedule::command('alojaflow:demo-reiniciar')->dailyAt('04:00')->when(fn (): bool => ModoDemo::activo());

// Una actualización que se quedó sin terminar (se cerró el navegador entre las dos fases) o que
// se cortó a medias: se termina o se deshace aquí, también en mantenimiento.
Schedule::command('alojaflow:finalizar-actualizacion --pendientes')->everyMinute()->evenInMaintenanceMode()->withoutOverlapping(60)->skip(fn (): bool => Instancia::activa());

// Al final, para todas: schedule:run revisa el mantenimiento solo al empezar la ronda, y una actualización que empieza
// a media ronda no debe encontrarse con tareas que arrancan mientras cambia los archivos (D-172).
MantenimientoEnCadaTarea::aplicar(Schedule::events());
