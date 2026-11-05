{{-- AlojaFlow · SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Se queda en pantalla mientras el navegador espera las dos fases de la actualización. Sin nada que cargar: la aplicación entra en mantenimiento. --}}
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ __('actualizaciones.instalando_titulo', ['version' => $version]) }}</title>
    <style>
        :root { color-scheme: light dark; --verde: #0f766e; --fondo: #f1f5f9; --tarjeta: #fff; --texto: #0b1f2a; --suave: #475569; --borde: #cbd5e1; }
        @media (prefers-color-scheme: dark) { :root { --verde: #14b8a6; --fondo: #020617; --tarjeta: #0f172a; --texto: #e2e8f0; --suave: #94a3b8; --borde: #334155; } }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 16px; background: var(--fondo); color: var(--texto); font: 16px/1.5 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
        main { max-width: 32rem; background: var(--tarjeta); border: 1px solid var(--borde); border-radius: 12px; padding: 24px; }
        h1 { font-size: 1.25rem; margin: 0 0 8px; display: flex; gap: 12px; align-items: center; }
        p { margin: 8px 0 0; }
        .nota { color: var(--suave); font-size: .875rem; }
        .giro { flex: none; width: 24px; height: 24px; border-radius: 50%; border: 3px solid var(--borde); border-top-color: var(--verde); animation: giro 1s linear infinite; }
        @keyframes giro { to { transform: rotate(360deg); } }
        @media (prefers-reduced-motion: reduce) { .giro { animation-duration: 4s; } }
        a { color: var(--verde); }
    </style>
</head>
<body>
    <main role="status">
        <h1><span class="giro" aria-hidden="true"></span>{{ __('actualizaciones.instalando_titulo', ['version' => $version]) }}</h1>
        <p>{{ __('actualizaciones.instalando_texto') }}</p>
        <p class="nota">{{ __('actualizaciones.instalando_sin_js') }} <a href="{{ \App\Filament\Pages\Actualizaciones::getUrl() }}">{{ __('actualizaciones.titulo') }}</a></p>
    </main>
    <script>
        // Con la página ya pintada: el navegador la deja a la vista hasta que llegue la respuesta, que tarda minutos.
        setTimeout(function () { window.location.replace(@js($enlace)); }, 150);
    </script>
    <noscript><p><a href="{{ $enlace }}">{{ __('actualizaciones.instalando_titulo', ['version' => $version]) }}</a></p></noscript>
</body>
</html>
