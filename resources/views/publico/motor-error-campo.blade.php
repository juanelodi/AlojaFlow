{{-- AlojaFlow · Copyright (C) 2026 Juan Galeana Durán · SPDX-License-Identifier: FSL-1.1-ALv2 --}}
@if ($errores->has($campo))
    <p class="error-campo" id="error-{{ $campo }}">{{ $errores->first($campo) }}</p>
@endif
