{{-- AlojaFlow · Copyright (C) 2026 Juan Galeana Durán · SPDX-License-Identifier: FSL-1.1-ALv2 --}}
<x-filament-panels::page>
    <div class="af-pila">
        <x-filament::section>
            <div class="af-pila">
                <img src="{{ asset('img/logo-alojaflow.svg') }}" alt="AlojaFlow" class="af-logo af-logo--claro">
                <img src="{{ asset('img/logo-alojaflow-oscuro.svg') }}" alt="AlojaFlow" class="af-logo af-logo--oscuro">
                <p class="af-prosa"><strong>{{ __('ui.acerca.version', ['version' => config('alojaflow.version')]) }}</strong></p>
                <p class="af-prosa">{{ __('ui.acerca.licencia') }}</p>
                <div>
                    <x-filament::button tag="a" :href="route('codigo-fuente')" icon="heroicon-m-code-bracket">
                        {{ __('ui.acerca.codigo_fuente') }}
                    </x-filament::button>
                    <p class="af-nota" style="margin-top: .5rem">{{ __('ui.acerca.codigo_fuente_ayuda') }}</p>
                </div>
            </div>
        </x-filament::section>

        <x-filament::section :heading="__('ui.acerca.creditos')">
            <p class="af-prosa">{{ __('ui.acerca.hoteldruid') }}</p>
        </x-filament::section>

        @if ($terceros = $this->avisosTerceros())
            <x-filament::section :heading="__('ui.acerca.terceros')" collapsible collapsed>
                <div class="af-prosa">{!! $terceros !!}</div>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
