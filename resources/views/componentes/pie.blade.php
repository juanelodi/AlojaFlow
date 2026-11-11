{{-- AlojaFlow · Copyright (C) 2026 Juan Galeana Durán · SPDX-License-Identifier: FSL-1.1-ALv2 --}}
{{-- Enlace «Código fuente» visible en toda la aplicación, también antes de iniciar sesión (regla 8). --}}
<footer class="af-pie">
    <span>AlojaFlow {{ config('alojaflow.version') }}</span>
    <span>{{ __('ui.pie.licencia') }}</span>
    <a href="{{ route('codigo-fuente') }}">{{ __('ui.pie.codigo_fuente') }}</a>
    @auth
        <a href="{{ \App\Filament\Pages\AcercaDe::getUrl() }}">{{ __('ui.pie.acerca') }}</a>
    @endauth
</footer>
