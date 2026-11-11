<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Http\Middleware;

use App\MejoraContinua\ArmadorLatido;
use App\MejoraContinua\Permiso;
use App\Support\Ajustes;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Cuántas veces se abre cada pantalla al día (D-150): solo el nombre de la ruta, sin quién ni qué datos.
 * Solo con permiso de mejora continua, y solo las pantallas del panel que se abren (GET que responde una
 * página), no las peticiones internas de Livewire. Una escritura por página.
 */
class ContarPantallas
{
    public function handle(Request $request, Closure $next): Response
    {
        $respuesta = $next($request);

        try {
            $ruta = $request->route()?->getName();
            // Solo pantallas del panel que se abren como página: no calendarios iCal, archivos ni peticiones de Livewire.
            if ($request->isMethod('GET') && $respuesta->getStatusCode() === 200 && is_string($ruta)
                && str_starts_with($ruta, 'filament.app.') && $request->header('X-Livewire') === null
                && str_starts_with((string) $respuesta->headers->get('Content-Type'), 'text/html') && Permiso::activa()) {
                $this->contar($ruta);
                $this->anotarServidor($request);
            }
        } catch (Throwable) {
            // Contar nunca debe romper una página.
        }

        return $respuesta;
    }

    private function contar(string $ruta): void
    {
        $pantalla = mb_substr(preg_replace('/^filament\.app\./', '', $ruta) ?? $ruta, 0, 150);
        DB::table('mejora_pantallas')->upsert(
            [['fecha' => now()->toDateString(), 'pantalla' => $pantalla, 'visitas' => 1]],
            ['fecha', 'pantalla'],
            ['visitas' => DB::raw('visitas + 1')],
        );
    }

    /**
     * Solo la familia del servidor web (Apache, LiteSpeed, nginx…), que en consola no se conoce.
     */
    private function anotarServidor(Request $request): void
    {
        $servidor = (string) $request->server('SERVER_SOFTWARE', '');
        $familia = preg_match('/^(apache|litespeed|nginx|microsoft-iis|openresty|caddy)/i', $servidor, $coincide) === 1
            ? strtolower($coincide[1])
            : ($servidor === '' ? '' : 'otro');
        if ($familia !== '' && Ajustes::obtener(ArmadorLatido::AJUSTE_SERVIDOR_WEB) !== $familia) {
            Ajustes::guardar(ArmadorLatido::AJUSTE_SERVIDOR_WEB, $familia);
        }
    }
}
