{{-- AlojaFlow · SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Página de mantenimiento. Al actualizar se genera de antemano (down --render): no puede usar
     recursos de la aplicación, porque se muestra aunque la aplicación no arranque. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ __('ui.mantenimiento.titulo') }}</title>
    <style>
        :root { color-scheme: light dark; --fondo: #f8fafc; --texto: #0f172a; --suave: #475569; --marca: #0f766e; }
        @media (prefers-color-scheme: dark) { :root { --fondo: #0f172a; --texto: #f1f5f9; --suave: #cbd5e1; --marca: #2dd4bf; } }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: var(--fondo); color: var(--texto);
               font: 1rem/1.6 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
        main { max-width: 32rem; padding: 2rem 1.5rem; text-align: center; }
        .marca { font-weight: 700; letter-spacing: .02em; color: var(--marca); }
        h1 { font-size: 1.5rem; margin: .5rem 0 1rem; }
        p { color: var(--suave); margin: 0 0 .75rem; }
    </style>
</head>
<body>
    <main>
        <div class="marca">AlojaFlow</div>
        <h1>{{ __('ui.mantenimiento.titulo') }}</h1>
        <p>{{ __('ui.mantenimiento.texto') }}</p>
        <p>{{ __('ui.mantenimiento.recarga') }}</p>
    </main>
</body>
</html>
