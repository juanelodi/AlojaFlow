{{-- AlojaFlow · SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Quien actualizó desde una versión anterior decide aquí (D-150). Mientras no decida, no se manda nada. --}}
{{-- Sin <form>: dentro del contenido, un formulario lo oculta en el teléfono hasta que arranca Alpine (D-067). --}}
<div class="af-aviso-global" x-data="{
    decidir(decision) {
        fetch(@js(route('mejora-continua.decidir')), {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': @js(csrf_token()), 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ decision }),
        }).then(() => window.location.reload())
    },
}">
    <x-filament::callout color="info" icon="heroicon-o-chart-bar" :heading="__('mejora.aviso.titulo')" :description="__('mejora.aviso.texto')">
        <x-slot name="footer">
            <div style="display: flex; flex-wrap: wrap; gap: .75rem; align-items: center">
                <x-filament::button color="info" x-on:click="decidir('si')">{{ __('mejora.aviso.si') }}</x-filament::button>
                <x-filament::button color="gray" x-on:click="decidir('no')">{{ __('mejora.aviso.no') }}</x-filament::button>
                <x-filament::link :href="\App\Filament\Pages\MejoraContinua::getUrl()">{{ __('mejora.aviso.detalle') }}</x-filament::link>
            </div>
        </x-slot>
    </x-filament::callout>
</div>
