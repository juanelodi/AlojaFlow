{{-- AlojaFlow · Copyright (C) 2026 Juan Galeana Durán · SPDX-License-Identifier: FSL-1.1-ALv2 --}}
{{-- Una actualización urgente ordenada por el equipo de AlojaFlow (D-151): se instala sola si hay cron; si no, a mano. --}}
<div class="af-aviso-global">
    <x-filament::callout color="danger" icon="heroicon-o-shield-exclamation"
        :heading="__('actualizaciones.automaticas.urgente_titulo', ['version' => $version])"
        :description="trim($mensaje.' '.($sola ? __('actualizaciones.automaticas.urgente_sola') : __('actualizaciones.automaticas.urgente_manual')))">
        <x-slot name="footer">
            <x-filament::button tag="a" :href="$enlace" color="danger">{{ __('actualizaciones.automaticas.ir') }}</x-filament::button>
        </x-slot>
    </x-filament::callout>
</div>
