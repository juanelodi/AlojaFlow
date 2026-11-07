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
                            <option value="{{ $clave }}" @selected($this->informe === $clave)>{{ __('informes.nombres.'.$clave) }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </label>
            <label class="af-campo">
                <span>{{ __('ui.indicadores.desde') }}</span>
                <x-filament::input.wrapper><x-filament::input type="date" :value="$this->desde" wire:model.live="desde" data-af-rango-inicio="periodo" /></x-filament::input.wrapper>
            </label>
            <label class="af-campo">
                <span>{{ __('ui.indicadores.hasta') }}</span>
                <x-filament::input.wrapper><x-filament::input type="date" :value="$this->hasta" wire:model.live="hasta" data-af-rango-fin="periodo" /></x-filament::input.wrapper>
            </label>
            <div class="af-grupo-botones">
                <x-filament::button icon="heroicon-m-arrow-down-tray" wire:click="descargarCsv">{{ __('ui.indicadores.descargar_csv') }}</x-filament::button>
                <x-filament::button color="gray" icon="heroicon-m-document-arrow-down" wire:click="descargarPdf">{{ __('informes.descargar_pdf') }}</x-filament::button>
                <x-filament::button color="gray" icon="heroicon-m-printer" x-on:click="window.print()">{{ __('informes.imprimir') }}</x-filament::button>
            </div>
        </div>

        @if ($this->rango() === null)
            <p class="af-aviso" role="alert">{{ __('ui.indicadores.rango_invalido') }}</p>
        @endif

        <x-filament::section :heading="__('informes.nombres.'.$informe)">
            @if (\Illuminate\Support\Facades\Lang::has('informes.ayudas.'.$informe))
                <p class="af-nota" style="margin-block-end: .75rem">{{ __('informes.ayudas.'.$informe) }}</p>
            @endif
            @if ($datos['filas'] === [])
                <p class="af-vacio">{{ __('informes.vacio') }}</p>
            @else
                @if (! empty($datos['grafica']))
                    @include('filament.partes.grafica-barras', ['grafica' => $datos['grafica']])
                @endif
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
                            @php($naturales = array_keys(array_intersect($datos['columnas'], \App\Servicios\Informes::titulos('mes', 'dia', 'llegada'))))
                            @foreach ($datos['filas'] as $f => $fila)
                                <tr>
                                    @foreach ($fila as $c => $celda)
                                        {{-- «noviembre» va después de «octubre», no de «abril»: las filas ya vienen en orden de tiempo. --}}
                                        <td @if (in_array($c, $naturales, true)) data-orden="{{ sprintf('%06d', $f) }}" @endif>{{ $celda }}</td>
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
