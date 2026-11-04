{{-- AlojaFlow · SPDX-License-Identifier: AGPL-3.0-or-later --}}
<x-filament-panels::page>
    @php($listas = $this->listas())
    @php($variasPropiedades = count($this->propiedadesElegidas()) > 1)
    <div class="af-pila">
        <div class="af-barra-herramientas">
            @include('filament.partes.selector-propiedad')
            <label class="af-campo">
                <span>{{ __('ui.hoy.fecha') }}</span>
                <x-filament::input.wrapper>
                    <x-filament::input type="date" wire:model.live="fecha" />
                </x-filament::input.wrapper>
            </label>
            <div class="af-grupo-botones">
                <x-filament::button color="gray" icon="heroicon-m-chevron-left" wire:click="moverDia(-1)" :label-sr-only="true" :tooltip="__('ui.comun.anterior')">
                    {{ __('ui.comun.anterior') }}
                </x-filament::button>
                <x-filament::button color="gray" wire:click="irAHoy">{{ __('ui.comun.hoy') }}</x-filament::button>
                <x-filament::button color="gray" icon="heroicon-m-chevron-right" wire:click="moverDia(1)" :label-sr-only="true" :tooltip="__('ui.comun.siguiente')">
                    {{ __('ui.comun.siguiente') }}
                </x-filament::button>
            </div>
        </div>

        <p class="af-nota">{{ \App\Support\Formato::fechaLarga($this->fechaElegida()) }}@if ($this->haySubordinadas()) · {{ __('ui.comun.solo_consulta') }}@endif</p>

        @foreach (['llegan', 'salen', 'continuan'] as $lista)
            <x-filament::section :heading="__('ui.hoy.'.$lista).' ('.$listas[$lista]->count().')'">
                <x-tabla-reservas
                    :reservas="$listas[$lista]"
                    :titulo="__('ui.hoy.'.$lista)"
                    :vacio="__('ui.hoy.vacio_'.$lista)"
                    :mostrar-propiedad="$variasPropiedades"
                    :gestionables="$this->propiedadesGestionables()"
                />
            </x-filament::section>
        @endforeach
    </div>
</x-filament-panels::page>
