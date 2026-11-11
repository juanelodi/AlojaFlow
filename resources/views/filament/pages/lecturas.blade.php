{{-- AlojaFlow · Copyright (C) 2026 Juan Galeana Durán · SPDX-License-Identifier: FSL-1.1-ALv2 --}}
@use('App\Enums\ServicioMedido')
@use('App\Filament\Resources\Reservas\ReservaResource')
@use('App\Support\Formato')
<x-filament-panels::page>
    <div class="af-pila">
        <p class="af-nota">{{ __('camping.lecturas.ayuda') }}</p>
        <div class="af-barra-herramientas">
            @include('filament.partes.selector-propiedad')
            <label class="af-campo">
                <span>{{ __('camping.lecturas.servicio') }}</span>
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="servicio">
                        @foreach (ServicioMedido::opciones() as $valor => $etiqueta)
                            <option value="{{ $valor }}" @selected($servicio === $valor)>{{ $etiqueta }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </label>
            <label class="af-campo">
                <span>{{ __('camping.lecturas.fecha') }}</span>
                <x-filament::input.wrapper>
                    <x-filament::input type="date" wire:model.live="fecha" :value="$fecha" />
                </x-filament::input.wrapper>
            </label>
        </div>

        @if ($this->propiedad() === null)
            <p class="af-vacio">{{ __('camping.lecturas.elige_propiedad') }}</p>
        @else
            @php($medida = $this->servicioElegido()->medida())
            @php($moneda = $this->propiedad()->moneda)
            @if ($this->tarifaActual() === null)
                <p class="af-nota" role="status">{{ __('camping.lecturas.sin_precio') }}</p>
            @endif
            <x-filament::section :heading="__('camping.lecturas.capturar')">
                @php($filas = $this->filas())
                @if ($filas === [])
                    <p class="af-vacio">{{ __('camping.lecturas.sin_lugares') }}</p>
                @else
                    <form wire:submit="guardar">
                        <div class="af-tabla-contenedor" tabindex="0" role="region" aria-label="{{ __('camping.lecturas.capturar') }}">
                            <table class="af-tabla">
                                <caption class="af-sr">{{ __('camping.lecturas.capturar') }}</caption>
                                <thead>
                                    <tr>
                                        <th scope="col">{{ __('ui.comun.unidad') }}</th>
                                        <th scope="col">{{ __('ui.comun.huesped') }}</th>
                                        <th scope="col">{{ __('camping.lecturas.anterior') }}</th>
                                        <th scope="col">{{ __('camping.lecturas.actual', ['medida' => $medida]) }}</th>
                                        <th scope="col">{{ __('camping.lecturas.medidor_nuevo') }}</th>
                                        <th scope="col" class="af-num">{{ __('camping.lecturas.a_cobrar') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($filas as $fila)
                                        @php($id = $fila['unidad']->id)
                                        <tr wire:key="lectura-{{ $id }}">
                                            <td>{{ $fila['unidad']->nombre }}</td>
                                            <td>{{ $fila['huesped'] ?? '—' }}</td>
                                            <td>
                                                @if ($fila['anterior'])
                                                    {{ (float) $fila['anterior']->actual }} <span class="af-nota">· {{ Formato::fecha($fila['anterior']->leida_en) }}</span>
                                                @else
                                                    —
                                                @endif
                                            </td>
                                            <td>
                                                <x-filament::input.wrapper>
                                                    <x-filament::input type="number" step="any" min="0" inputmode="decimal" wire:model.live.debounce.500ms="capturas.{{ $id }}.actual" :value="$capturas[$id]['actual'] ?? ''" aria-label="{{ __('camping.lecturas.actual', ['medida' => $medida]) }} · {{ $fila['unidad']->nombre }}" />
                                                </x-filament::input.wrapper>
                                            </td>
                                            <td><input type="checkbox" class="fi-checkbox-input" wire:model.live="capturas.{{ $id }}.inicial" aria-label="{{ __('camping.lecturas.medidor_nuevo') }} · {{ $fila['unidad']->nombre }}"></td>
                                            <td class="af-num">
                                                @if ($fila['importe'] !== null)
                                                    {{ Formato::dinero($fila['importe'], $moneda) }}
                                                @elseif ($fila['huesped'] && ! $fila['cobrable'])
                                                    <span class="af-nota">{{ __('camping.lecturas.punto_de_partida') }}</span>
                                                @else
                                                    —
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <x-filament::button type="submit" wire:target="guardar" style="margin-top: .75rem">{{ __('camping.lecturas.guardar') }}</x-filament::button>
                    </form>
                @endif
            </x-filament::section>

            <x-filament::section :heading="__('camping.lecturas.historial')" :description="__('camping.lecturas.historial_ayuda')">
                @php($historial = $this->historial())
                @if ($historial->isEmpty())
                    <p class="af-vacio">{{ __('camping.lecturas.sin_historial') }}</p>
                @else
                    <div class="af-tabla-contenedor" tabindex="0" role="region" aria-label="{{ __('camping.lecturas.historial') }}">
                        <table class="af-tabla">
                            <caption class="af-sr">{{ __('camping.lecturas.historial') }}</caption>
                            <thead>
                                <tr>
                                    <th scope="col">{{ __('camping.lecturas.fecha') }}</th>
                                    <th scope="col">{{ __('ui.comun.unidad') }}</th>
                                    <th scope="col">{{ __('camping.lecturas.lectura') }}</th>
                                    <th scope="col">{{ __('camping.lecturas.consumo', ['medida' => $medida]) }}</th>
                                    <th scope="col" class="af-num">{{ __('camping.lecturas.cobrado') }}</th>
                                    <th scope="col">{{ __('informes.columnas.reserva') }}</th>
                                    <th scope="col"><span class="af-sr">{{ __('permisos.sesiones.acciones') }}</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($historial as $lectura)
                                    <tr wire:key="historial-{{ $lectura->id }}">
                                        <td data-orden="{{ $lectura->leida_en?->format('Y-m-d') }}">{{ Formato::fecha($lectura->leida_en) }}</td>
                                        <td>{{ $lectura->unidad->nombre }}</td>
                                        <td data-orden="{{ (float) $lectura->actual }}">{{ (float) $lectura->actual }}</td>
                                        <td data-orden="{{ $lectura->inicial ? '' : (float) $lectura->consumo }}">{{ $lectura->inicial ? __('camping.lecturas.punto_de_partida') : (float) $lectura->consumo }}</td>
                                        <td class="af-num">{{ $lectura->importe > 0 ? Formato::dinero($lectura->importe, $moneda) : '—' }}</td>
                                        <td>
                                            @if ($lectura->reserva)
                                                <a class="af-enlace" href="{{ ReservaResource::getUrl('view', ['record' => $lectura->reserva]) }}">{{ $lectura->reserva->numero }}</a>
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td><x-filament::button size="sm" color="gray" wire:click="quitar({{ $lectura->id }})" wire:confirm="{{ __('camping.lecturas.quitar_confirma') }}">{{ __('camping.lecturas.quitar') }}</x-filament::button></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
