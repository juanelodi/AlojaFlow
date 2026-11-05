{{-- AlojaFlow · SPDX-License-Identifier: AGPL-3.0-or-later --}}
@foreach ($guia as $seccion)
    <section class="tarjeta">
        <h2>{{ $seccion['titulo'] }}</h2>
        <p style="white-space:pre-line;margin:0">{{ $seccion['cuerpo'] }}</p>
    </section>
@endforeach
