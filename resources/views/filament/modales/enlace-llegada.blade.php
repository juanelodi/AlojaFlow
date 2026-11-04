{{-- AlojaFlow · SPDX-License-Identifier: AGPL-3.0-or-later --}}
<div class="af-pila">
    <p class="af-nota">{{ __('llegada.panel.enlace_ayuda') }}</p>
    <pre class="af-comando"><code>{{ $enlace }}</code></pre>
    <div class="af-grupo-botones">
        <x-filament::button tag="a" color="gray" target="_blank" :href="$whatsapp">{{ __('llegada.panel.enlace_whatsapp') }}</x-filament::button>
    </div>
</div>
