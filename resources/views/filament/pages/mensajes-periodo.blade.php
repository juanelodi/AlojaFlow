{{-- AlojaFlow · SPDX-License-Identifier: AGPL-3.0-or-later --}}
@use('App\Support\Formato')
<x-filament-panels::page>
    <div class="af-pila">
        <p class="af-nota">{{ __('operacion.mensajes.ayuda') }}</p>
        <div class="af-barra-herramientas">
            @include('filament.partes.selector-propiedad')
            <label class="af-campo">
                <span>{{ __('mensajes.mandar.plantilla') }}</span>
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="plantillaId">
                        <option value="">{{ $this->propiedadUnica() === null ? __('operacion.mensajes.elige_propiedad') : __('operacion.mensajes.elige_plantilla') }}</option>
                        @foreach ($this->plantillas() as $id => $nombre)
                            <option value="{{ $id }}">{{ $nombre }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </label>
            <label class="af-campo">
                <span>{{ __('operacion.mensajes.por') }}</span>
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="por">
                        <option value="llegada">{{ __('ui.comun.llegada') }}</option>
                        <option value="salida">{{ __('ui.comun.salida') }}</option>
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </label>
            <label class="af-campo">
                <span>{{ __('ui.indicadores.desde') }}</span>
                <x-filament::input.wrapper><x-filament::input type="date" :value="$this->desde" wire:model.live="desde" /></x-filament::input.wrapper>
            </label>
            <label class="af-campo">
                <span>{{ __('ui.indicadores.hasta') }}</span>
                <x-filament::input.wrapper><x-filament::input type="date" :value="$this->hasta" wire:model.live="hasta" /></x-filament::input.wrapper>
            </label>
        </div>

        @php($filas = $this->filas())
        @if ($this->plantillaId === null)
            <p class="af-vacio">{{ __('operacion.mensajes.elige_plantilla') }}</p>
        @elseif ($filas === [])
            <p class="af-vacio">{{ __('operacion.mensajes.vacio') }}</p>
        @else
            @foreach ($filas as $fila)
                @php($reserva = $fila['reserva'])
                <x-filament::section wire:key="mensaje-{{ $reserva->id }}" :heading="($reserva->titular?->nombreCompleto() ?: $reserva->numero).' · '.($reserva->unidad->nombre ?? '')" :description="Formato::fecha($reserva->llegada).' → '.Formato::fecha($reserva->salida)">
                    <p class="af-mensaje-fila">{{ $fila['texto'] }}</p>
                    <div class="af-grupo-botones" x-data>
                        <x-filament::button tag="a" size="sm" color="gray" icon="heroicon-m-chat-bubble-left-ellipsis" :href="$fila['whatsapp']" target="_blank" rel="noopener" x-on:click="$wire.anotarWhatsapp({{ $reserva->id }})">{{ __('operacion.mensajes.whatsapp') }}</x-filament::button>
                        <x-filament::button size="sm" color="gray" icon="heroicon-m-clipboard" x-on:click="navigator.clipboard.writeText(@js($fila['texto']))">{{ __('operacion.mensajes.copiar') }}</x-filament::button>
                        @if ($fila['correo'] !== null)
                            <x-filament::button size="sm" color="gray" icon="heroicon-m-envelope" wire:click="mandarCorreo({{ $reserva->id }})" wire:confirm="{{ __('operacion.mensajes.confirmar_correo', ['correo' => $fila['correo']]) }}" wire:target="mandarCorreo({{ $reserva->id }})">{{ __('operacion.mensajes.correo') }}</x-filament::button>
                        @endif
                    </div>
                </x-filament::section>
            @endforeach
        @endif
    </div>
</x-filament-panels::page>
