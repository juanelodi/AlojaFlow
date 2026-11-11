{{-- AlojaFlow · Copyright (C) 2026 Juan Galeana Durán · SPDX-License-Identifier: FSL-1.1-ALv2 --}}
@foreach ($guia as $seccion)
    <section class="tarjeta">
        <h2>{{ $seccion['titulo'] }}</h2>
        <p style="white-space:pre-line;margin:0">{{ $seccion['cuerpo'] }}</p>
    </section>
@endforeach
