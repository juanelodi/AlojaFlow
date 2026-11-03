{{-- AlojaFlow · SPDX-License-Identifier: AGPL-3.0-or-later --}}
@php($opciones = $this->opcionesPropiedad())
@if (count($opciones) > 1)
    <label class="af-campo">
        <span>{{ __('ui.comun.propiedad') }}</span>
        <x-filament::input.wrapper>
            <x-filament::input.select wire:model.live="propiedadId">
                <option value="">{{ __('ui.comun.todas') }}</option>
                @foreach ($opciones as $id => $nombre)
                    <option value="{{ $id }}">{{ $nombre }}</option>
                @endforeach
            </x-filament::input.select>
        </x-filament::input.wrapper>
    </label>
@endif
