{{-- AlojaFlow · SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Descripción y cómo hablar con el alojamiento. --}}
@if ($pagina->descripcion() || $pagina->ubicacion() || $pagina->servicios())
    <section class="tarjeta">
        @if ($pagina->descripcion())
            <p style="white-space:pre-line;margin:0 0 8px">{{ $pagina->descripcion() }}</p>
        @endif
        @if ($pagina->servicios())
            <h2>{{ __('portal.servicios') }}</h2>
            <p class="gris" style="white-space:pre-line;margin:0 0 8px">{{ $pagina->servicios() }}</p>
        @endif
        @if ($pagina->ubicacion())
            <h2>{{ __('portal.ubicacion') }}</h2>
            <p class="gris" style="white-space:pre-line;margin:0">{{ $pagina->ubicacion() }}</p>
        @endif
        @include('publico.contacto')
    </section>
@elseif ($pagina->whatsapp() || $pagina->mapaUrl())
    <section class="tarjeta" style="padding-top:6px">@include('publico.contacto')</section>
@endif
