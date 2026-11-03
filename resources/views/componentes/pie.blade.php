{{-- AlojaFlow · SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Enlace «Código fuente» visible en toda la aplicación, también antes de iniciar sesión (regla 8). --}}
<footer class="af-pie">
    <span>AlojaFlow {{ config('alojaflow.version') }}</span>
    <span>{{ __('ui.pie.licencia') }}</span>
    <a href="{{ route('codigo-fuente') }}">{{ __('ui.pie.codigo_fuente') }}</a>
    @auth
        <a href="{{ \App\Filament\Pages\AcercaDe::getUrl() }}">{{ __('ui.pie.acerca') }}</a>
    @endauth
</footer>
