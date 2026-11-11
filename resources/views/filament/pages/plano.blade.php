{{-- AlojaFlow · Copyright (C) 2026 Juan Galeana Durán · SPDX-License-Identifier: FSL-1.1-ALv2 --}}
<x-filament-panels::page>
    @php($planos = $this->planos())
    @php($plano = $this->plano())
    <div class="af-pila">
        <div class="af-barra-herramientas">
            @include('filament.partes.selector-propiedad')
            @if ($planos->count() > 1)
                <label class="af-campo">
                    <span>{{ __('plano.nombre') }}</span>
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="planoId">
                            @foreach ($planos as $opcion)
                                <option value="{{ $opcion->id }}" @selected($this->planoId === $opcion->id)>{{ $opcion->nombre }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </label>
            @endif
        </div>

        @if ($plano === null)
            <p class="af-vacio">{{ __('plano.vacio') }}</p>
        @else
            <ul class="af-leyenda">
                @foreach (['libre', 'llega', 'sale', 'ocupada'] as $estado)
                    <li><span class="af-plano-marca af-plano-unidad--{{ $estado }}" aria-hidden="true"></span> {{ __('plano.estados.'.$estado) }}</li>
                @endforeach
            </ul>
            @if ($this->editando)
                <p class="af-aviso">{{ __('plano.editar_ayuda') }}</p>
            @endif
            <div class="af-plano"
                 @if ($plano->archivo_id) style="background-image: url('{{ route('archivos.ver', $plano->archivo_id) }}')" @endif
                 x-data="{
                     moviendo: null,
                     mover(evento) {
                         if (! this.moviendo) return;
                         const caja = $el.getBoundingClientRect();
                         this.moviendo.style.left = Math.max(0, Math.min(100, (evento.clientX - caja.left) / caja.width * 100)) + '%';
                         this.moviendo.style.top = Math.max(0, Math.min(100, (evento.clientY - caja.top) / caja.height * 100)) + '%';
                     },
                     soltar() {
                         if (! this.moviendo) return;
                         $wire.moverUnidad(Number(this.moviendo.dataset.unidad), parseFloat(this.moviendo.style.left), parseFloat(this.moviendo.style.top));
                         this.moviendo = null;
                     },
                 }"
                 x-on:pointermove="mover($event)"
                 x-on:pointerup="soltar()">
                @foreach ($this->unidades() as $unidad)
                    <div wire:key="plano-unidad-{{ $unidad['id'] }}"
                         data-unidad="{{ $unidad['id'] }}"
                         @class(['af-plano-unidad', 'af-plano-unidad--'.$unidad['estado'], 'af-plano-unidad--editando' => $this->editando])
                         style="left: {{ $unidad['x'] }}%; top: {{ $unidad['y'] }}%"
                         @if ($this->editando) x-on:pointerdown.prevent="moviendo = $el" @endif>
                        <strong>{{ $unidad['nombre'] }}</strong>
                        <span>{{ __('plano.estados.'.$unidad['estado']) }}</span>
                        @if ($unidad['fuera'])
                            <span>{{ __('limpieza.fuera_de_servicio') }}</span>
                        @elseif ($unidad['limpieza'] !== \App\Enums\EstadoLimpieza::Limpia)
                            <span>{{ $unidad['limpieza']->etiqueta() }}</span>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-filament-panels::page>
