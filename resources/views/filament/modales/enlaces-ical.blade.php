{{-- AlojaFlow · Copyright (C) 2026 Juan Galeana Durán · SPDX-License-Identifier: FSL-1.1-ALv2 --}}
<div class="af-pila">
    <p class="af-nota">{{ __('ical.exportar.ayuda') }}</p>
    @forelse ($enlaces as $fila)
        <div>
            <div class="af-tarea__titulo">{{ $fila['unidad'] }}</div>
            <pre class="af-comando"><code>{{ $fila['url'] }}</code></pre>
        </div>
    @empty
        <p class="af-vacio">{{ __('ical.exportar.vacio') }}</p>
    @endforelse
</div>
