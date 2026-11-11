{{-- AlojaFlow · Copyright (C) 2026 Juan Galeana Durán · SPDX-License-Identifier: FSL-1.1-ALv2 --}}
<div class="af-pila">
    <p class="af-nota">{{ __('llegada.panel.enlace_ayuda') }}</p>
    <pre class="af-comando"><code>{{ $enlace }}</code></pre>
    <div class="af-grupo-botones">
        <x-filament::button tag="a" color="gray" target="_blank" :href="$whatsapp">{{ __('llegada.panel.enlace_whatsapp') }}</x-filament::button>
    </div>
</div>
