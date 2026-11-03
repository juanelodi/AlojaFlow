{{-- AlojaFlow · SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Aviso en todas las pantallas, solo para el administrador que sigue dentro durante el mantenimiento. --}}
<div class="af-aviso-global">
    <x-filament::callout color="warning" icon="heroicon-o-exclamation-triangle" :heading="__('actualizaciones.pendiente_finalizar')">
        <x-slot name="footer">
            <x-filament::button tag="a" :href="$enlace" color="warning">{{ __('actualizaciones.finalizar_ahora') }}</x-filament::button>
        </x-slot>
    </x-filament::callout>
</div>
