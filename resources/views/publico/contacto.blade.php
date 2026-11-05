{{-- AlojaFlow · SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Cómo hablar con el alojamiento y dónde está. --}}
@if ($pagina->whatsapp() || $propiedad->telefono || $propiedad->correo || $pagina->mapaUrl())
    <p style="display:flex;flex-wrap:wrap;gap:8px;margin:12px 0 0">
        @if ($pagina->whatsapp())
            <a class="boton" href="{{ $pagina->whatsapp() }}" target="_blank" rel="noopener">{{ __('portal.whatsapp') }}</a>
        @endif
        @if ($propiedad->telefono)
            <a class="boton secundario" href="tel:{{ preg_replace('/[^0-9+]/', '', $propiedad->telefono) }}">{{ __('portal.llamar') }}</a>
        @endif
        @if ($propiedad->correo)
            <a class="boton secundario" href="mailto:{{ $propiedad->correo }}">{{ __('portal.escribir') }}</a>
        @endif
        @if ($pagina->mapaUrl())
            <a class="boton secundario" href="{{ $pagina->mapaUrl() }}" target="_blank" rel="noopener">{{ __('portal.mapa') }}</a>
        @endif
    </p>
@endif
