{{-- AlojaFlow · Copyright (C) 2026 Juan Galeana Durán · SPDX-License-Identifier: FSL-1.1-ALv2 --}}
{{-- Sin recursos de la aplicación: esta página tiene que verse aunque algo más haya fallado. --}}
@php
    $preferido = request()->getPreferredLanguage((array) config('alojaflow.idiomas'));
    if (is_string($preferido)) {
        app()->setLocale($preferido);
    }
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ __('ui.errores.'.$clave.'_titulo') }}</title>
    <style>
        :root { color-scheme: light dark; --fondo: #f8fafc; --texto: #0b1f2a; --suave: #475569; --marca: #0f766e; --sobre-marca: #fff; }
        @media (prefers-color-scheme: dark) { :root { --fondo: #0f172a; --texto: #f1f5f9; --suave: #cbd5e1; --marca: #2dd4bf; --sobre-marca: #042f2e; } }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: var(--fondo); color: var(--texto);
               font: 1rem/1.6 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
        main { max-width: 32rem; padding: 2rem 1.5rem; text-align: center; }
        .marca { font-weight: 700; letter-spacing: .02em; color: var(--marca); }
        h1 { font-size: 1.5rem; line-height: 1.25; margin: .5rem 0 1rem; text-wrap: balance; }
        p { color: var(--suave); margin: 0 0 1.5rem; }
        a { display: inline-block; min-height: 2.75rem; padding: .625rem 1.25rem; border-radius: .5rem; background: var(--marca); color: var(--sobre-marca);
            font-weight: 600; text-decoration: none; }
        a:focus-visible { outline: 3px solid var(--marca); outline-offset: 2px; }
    </style>
</head>
<body>
    <main>
        <div class="marca">AlojaFlow</div>
        <h1>{{ __('ui.errores.'.$clave.'_titulo') }}</h1>
        <p>{{ __('ui.errores.'.$clave.'_texto') }}</p>
        <a href="{{ url('/') }}">{{ __('ui.errores.volver') }}</a>
    </main>
</body>
</html>
