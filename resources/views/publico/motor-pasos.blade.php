{{-- AlojaFlow · SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Dónde va el huésped y cuánto le falta. --}}
<ol class="pasos" aria-label="{{ __('motor.pasos.titulo') }}">
    @foreach (['elegir', 'datos', 'listo'] as $numero => $nombre)
        <li @if ($paso === $nombre) aria-current="step" @endif>{{ $numero + 1 }}. {{ __('motor.pasos.'.$nombre) }}</li>
    @endforeach
</ol>
