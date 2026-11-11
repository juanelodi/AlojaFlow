{{-- AlojaFlow · Copyright (C) 2026 Juan Galeana Durán · SPDX-License-Identifier: FSL-1.1-ALv2 --}}
<div class="af-pila" x-data="{ copiado: false }">
    <p class="af-prosa">{{ __('sincronizacion.compartir.codigo_texto') }}</p>
    <div class="af-comando">
        <code x-ref="codigo">{{ $codigo }}</code>
        <x-filament::button size="sm" color="gray" x-on:click="navigator.clipboard?.writeText($refs.codigo.textContent).then(() => { copiado = true })">
            <span x-show="! copiado">{{ __('modulos.pasos.copiar') }}</span>
            <span x-show="copiado" x-cloak>{{ __('modulos.pasos.copiado') }}</span>
        </x-filament::button>
    </div>
</div>
