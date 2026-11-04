{{-- AlojaFlow · SPDX-License-Identifier: AGPL-3.0-or-later --}}
<x-filament-panels::page>
    @php($datos = $this->datos())
    <div class="af-pila">
        <div class="af-barra-herramientas">
            @include('filament.partes.selector-propiedad')
            <label class="af-campo">
                <span>{{ __('informes.informe') }}</span>
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="informe">
                        @foreach (\App\Servicios\Informes::INFORMES as $clave)
                            <option value="{{ $clave }}">{{ __('informes.nombres.'.$clave) }}</option>
                        @endforeach
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
            <x-filament::button icon="heroicon-m-arrow-down-tray" wire:click="descargarCsv">{{ __('ui.indicadores.descargar_csv') }}</x-filament::button>
        </div>

        @if ($this->rango() === null)
            <p class="af-aviso" role="alert">{{ __('ui.indicadores.rango_invalido') }}</p>
        @endif

        <x-filament::section :heading="__('informes.nombres.'.$informe)">
            @if ($datos['filas'] === [])
                <p class="af-vacio">{{ __('informes.vacio') }}</p>
            @else
                <div class="af-tabla-contenedor" tabindex="0" role="region" aria-label="{{ $this->getTitle() }}">
                    <table class="af-tabla">
                        <thead>
                            <tr>
                                @foreach ($datos['columnas'] as $columna)
                                    <th scope="col">{{ $columna }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($datos['filas'] as $fila)
                                <tr>
                                    @foreach ($fila as $celda)
                                        <td>{{ $celda }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
