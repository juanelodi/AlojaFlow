{{-- AlojaFlow · SPDX-License-Identifier: AGPL-3.0-or-later --}}
<x-filament-panels::page>
    <div class="af-pila">
        <p class="af-nota">{{ __('herramientas.anonimizar.ayuda') }}</p>
        <div class="af-barra-herramientas">
            @include('filament.partes.selector-propiedad')
            <label class="af-campo">
                <span>{{ __('herramientas.anonimizar.anios') }}</span>
                <x-filament::input.wrapper>
                    <x-filament::input type="number" min="1" max="50" :value="$this->anios" wire:model.live.debounce.500ms="anios" />
                </x-filament::input.wrapper>
            </label>
        </div>
        @php($total = $this->total())
        <x-filament::section :heading="trans_choice('herramientas.anonimizar.encontrados', $total, ['n' => $total])">
            @if ($total === 0)
                <p class="af-vacio">{{ __('herramientas.anonimizar.vacio') }}</p>
            @else
                <ul class="af-lista-simple">
                    @foreach ($this->huespedes() as $huesped)
                        <li wire:key="huesped-{{ $huesped->id }}">{{ $huesped->nombreCompleto() }}</li>
                    @endforeach
                </ul>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
