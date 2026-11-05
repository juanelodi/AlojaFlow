{{-- AlojaFlow · SPDX-License-Identifier: AGPL-3.0-or-later --}}
<x-filament-panels::page>
    <div class="af-pila">
        @php($opciones = $this->opcionesPropiedad())
        @if (count($opciones) > 1)
            <div class="af-barra-herramientas">
                <label class="af-campo">
                    <span>{{ __('ui.comun.propiedad') }}</span>
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="propiedadId">
                            @foreach ($opciones as $id => $nombre)
                                <option value="{{ $id }}">{{ $nombre }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </label>
            </div>
        @endif

        @if ($enlace = $this->enlace())
            <x-filament::section :heading="__('motor.panel.enlace')">
                <p class="af-prosa"><a class="af-enlace" href="{{ $enlace }}" target="_blank" rel="noopener">{{ $enlace }}</a></p>
                <p class="af-nota" style="margin-top: .5rem">{{ __('portal.panel.enlace_ayuda') }}</p>
            </x-filament::section>
        @endif

        <form wire:submit="guardar" class="af-pila">
            {{ $this->form }}
            <div>
                <x-filament::button type="submit">{{ __('portal.panel.guardar') }}</x-filament::button>
            </div>
        </form>
    </div>
</x-filament-panels::page>
