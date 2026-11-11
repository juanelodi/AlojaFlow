{{-- AlojaFlow · Copyright (C) 2026 Juan Galeana Durán · SPDX-License-Identifier: FSL-1.1-ALv2 --}}
{{-- Dónde va el huésped y cuánto le falta. --}}
<ol class="pasos" aria-label="{{ __('motor.pasos.titulo') }}">
    @foreach (['elegir', 'datos', 'listo'] as $numero => $nombre)
        <li @if ($paso === $nombre) aria-current="step" @endif>{{ $numero + 1 }}. {{ __('motor.pasos.'.$nombre) }}</li>
    @endforeach
</ol>
