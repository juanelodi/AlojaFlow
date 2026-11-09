{{-- AlojaFlow · SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Para instalarla en el teléfono (D-090): sin service worker, porque datos viejos en una recepción son peores que no abrir sin conexión. --}}
<link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
<meta name="theme-color" content="#0f766e">
{{-- Mejora continua (D-150): adónde mandar los errores de JavaScript, solo con permiso. --}}
@if (\App\MejoraContinua\Permiso::activa())
    <meta name="af-errores-url" content="{{ route('mejora-continua.error-navegador') }}">
@endif
<link rel="apple-touch-icon" href="{{ asset('img/icono-apple-180.png') }}">
{{-- Al imprimir, siempre claro. --}}
<script>
    window.addEventListener('beforeprint', () => {
        const raiz = document.documentElement;
        raiz.dataset.afOscuro = raiz.classList.contains('dark') ? '1' : '';
        raiz.classList.remove('dark');
    });
    window.addEventListener('afterprint', () => {
        const raiz = document.documentElement;
        if (raiz.dataset.afOscuro === '1') {
            raiz.classList.add('dark');
        }
    });
</script>
@php($colores = \App\Support\ColoresEstado::css(\App\Seguridad\Acceso::usuarioActual()))
@if ($colores !== '')
    <style>{!! $colores !!}</style>
@endif
