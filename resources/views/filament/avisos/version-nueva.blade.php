{{-- AlojaFlow · Copyright (C) 2026 Juan Galeana Durán · SPDX-License-Identifier: FSL-1.1-ALv2 --}}
{{-- En el inicio, solo para quien puede instalarla (D-140). Lo que cambia se lee en Actualizaciones. --}}
<div class="af-aviso-global">
    <x-filament::callout color="info" icon="heroicon-o-arrow-up-circle" :heading="__('actualizaciones.hay_version', ['version' => $version])">
        <x-slot name="footer">
            <x-filament::button tag="a" :href="$enlace" color="info">{{ __('actualizaciones.ver_que_cambia') }}</x-filament::button>
        </x-slot>
    </x-filament::callout>
</div>
