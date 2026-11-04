<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cabeceras de seguridad de toda la aplicación. El motor de reservas puede ir dentro de un iframe en
 * el sitio del alojamiento; lo demás, no. Alpine y Livewire necesitan 'unsafe-inline' y 'unsafe-eval',
 * así que la política sirve sobre todo para que nada se cargue ni se mande a otro sitio.
 */
class CabecerasSeguridad
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->debeIrPorHttps($request)) {
            return redirect()->to('https://'.$request->getHttpHost().$request->getRequestUri(), 301);
        }

        /** @var Response $respuesta */
        $respuesta = $next($request);
        $cabeceras = $respuesta->headers;
        $publica = $request->is('reservar/*');

        $cabeceras->set('X-Content-Type-Options', 'nosniff');
        // 'same-origin' y no 'no-referrer': con este último, los formularios mandan «Origin: null».
        $cabeceras->set('Referrer-Policy', 'same-origin');
        $cabeceras->set('Permissions-Policy', 'camera=(self), microphone=(), geolocation=(), payment=(), usb=()');
        $cabeceras->set('Content-Security-Policy', $this->politica($publica));
        if (! $publica) {
            $cabeceras->set('X-Frame-Options', 'SAMEORIGIN');
        }
        if ($this->llegoPorHttps($request)) {
            $cabeceras->set('Strict-Transport-Security', 'max-age=31536000');
        }
        // Solo la búsqueda del motor interesa a los buscadores; lo demás es privado o lleva un token.
        if (! $publica || $request->is('reservar/*/*')) {
            $cabeceras->set('X-Robots-Tag', 'noindex, nofollow');
        }
        $cabeceras->remove('X-Powered-By');
        if (! headers_sent()) {
            header_remove('X-Powered-By');
        }

        return $respuesta;
    }

    private function politica(bool $publica): string
    {
        $reglas = [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval'",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: blob:",
            "font-src 'self' data:",
            "connect-src 'self'",
            "worker-src 'self' blob:",
            "frame-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
        ];
        // El motor manda a la página de la pasarela, y form-action también se aplica a esa redirección.
        if ($publica) {
            $reglas[] = 'frame-ancestors *';
        } else {
            $reglas[] = "form-action 'self'";
            $reglas[] = "frame-ancestors 'self'";
        }

        return implode('; ', $reglas);
    }

    /**
     * Solo en producción y si la instalación es https. Un POST se deja pasar: redirigirlo perdería
     * lo que el usuario escribió.
     */
    private function debeIrPorHttps(Request $request): bool
    {
        return app()->isProduction()
            && in_array($request->getMethod(), ['GET', 'HEAD'], true)
            && str_starts_with((string) config('app.url'), 'https://')
            && ! $this->llegoPorHttps($request);
    }

    /**
     * Se revisa también la cabecera del proxy: sin esto, redirigir a quien ya llegó por https detrás
     * de un proxy deja el sitio en un bucle. Quien la falsifica solo se evita su propia redirección.
     */
    private function llegoPorHttps(Request $request): bool
    {
        return $request->isSecure()
            || strtolower((string) $request->header('X-Forwarded-Proto')) === 'https'
            || strtolower((string) $request->header('X-Forwarded-Ssl')) === 'on';
    }
}
