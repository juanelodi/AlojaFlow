{{-- AlojaFlow · SPDX-License-Identifier: AGPL-3.0-or-later --}}
@if ($errores->has($campo))
    <p class="error-campo" id="error-{{ $campo }}">{{ $errores->first($campo) }}</p>
@endif
