{{-- AlojaFlow · Copyright (C) 2026 Juan Galeana Durán · SPDX-License-Identifier: FSL-1.1-ALv2 --}}
{{-- Lo que la ley pide que se vea (D-091): el número de registro, quién responde y ante quién quejarse. Va en páginas públicas y en PDF: sin clases del panel. --}}
@foreach ($registros as $registro)
    <div>
        {{ $registro->tipo }}: {{ $registro->numero }}
        @if ($registro->unidad)
            ({{ $registro->unidad->nombre }})
        @endif
    </div>
@endforeach
@if ($propiedad->responsable)
    <div>{{ __('herramientas.registro.responsable') }}: {{ $propiedad->responsable }}</div>
@endif
@if ($propiedad->autoridad_quejas)
    <div>{{ __('herramientas.registro.autoridad_quejas') }}: {{ $propiedad->autoridad_quejas }}</div>
@endif
