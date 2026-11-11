{{-- AlojaFlow · Copyright (C) 2026 Juan Galeana Durán · SPDX-License-Identifier: FSL-1.1-ALv2 --}}
<x-filament-panels::page>
    @php($error = $this->error())
    @php($libres = $this->libres())
    <div class="af-pila">
        <div class="af-barra-herramientas">
            @include('filament.partes.selector-propiedad')
            <label class="af-campo">
                <span>{{ __('ui.comun.llegada') }}</span>
                <x-filament::input.wrapper><x-filament::input type="date" :value="$this->llegada" wire:model.live="llegada" data-af-rango-inicio="estancia" data-af-rango-dias="1" data-af-rango-llenar="1" /></x-filament::input.wrapper>
            </label>
            <label class="af-campo">
                <span>{{ __('ui.comun.salida') }}</span>
                <x-filament::input.wrapper><x-filament::input type="date" :value="$this->salida" wire:model.live="salida" data-af-rango-fin="estancia" /></x-filament::input.wrapper>
            </label>
            <label class="af-campo" style="min-width: 6rem">
                <span>{{ __('ui.disponibilidad.personas') }}</span>
                <x-filament::input.wrapper><x-filament::input type="number" min="1" max="100" :value="$this->personas" wire:model.live.debounce.400ms="personas" /></x-filament::input.wrapper>
            </label>
        </div>

        @if ($error)
            <p class="af-aviso" role="alert">{{ $error }}</p>
        @else
            <p class="af-nota">{{ __('ui.disponibilidad.resultado', ['unidades' => trans_choice('ui.disponibilidad.n_unidades', $libres->flatten()->count()), 'noches' => trans_choice('ui.comun.n_noches', $this->noches())]) }}</p>
            @forelse ($libres as $unidades)
                <x-filament::section :heading="$unidades->first()->propiedad->nombre">
                    <div class="af-lista-libres">
                        @foreach ($unidades as $unidad)
                            <div class="af-libre">
                                <strong>{{ $unidad->nombre }}</strong>
                                <span class="af-secundario">
                                    {{ $unidad->capacidad > 0 ? __('ui.disponibilidad.capacidad', ['personas' => $unidad->capacidad]) : __('ui.disponibilidad.capacidad_desconocida') }}
                                </span>
                                @if (in_array($unidad->propiedad_id, $this->propiedadesGestionables(), true))
                                    <x-filament::button size="sm" tag="a" icon="heroicon-m-plus"
                                        :href="\App\Filament\Resources\Reservas\ReservaResource::getUrl('create', ['unidad' => $unidad->id, 'llegada' => $this->llegada, 'salida' => $this->salida, 'personas' => $this->personas])">
                                        {{ __('ui.disponibilidad.reservar') }}
                                    </x-filament::button>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </x-filament::section>
            @empty
                <x-filament::empty-state :heading="__('ui.disponibilidad.ninguna_libre')" icon="heroicon-o-no-symbol" />
            @endforelse
        @endif
    </div>
</x-filament-panels::page>
