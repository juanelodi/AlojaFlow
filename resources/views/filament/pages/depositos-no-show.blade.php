{{-- AlojaFlow · Copyright (C) 2026 Juan Galeana Durán · SPDX-License-Identifier: FSL-1.1-ALv2 --}}
@use('App\Filament\Resources\Reservas\ReservaResource')
@use('App\Support\Formato')
<x-filament-panels::page>
    <div class="af-pila">
        <p class="af-nota">{{ __('operacion.depositos.ayuda') }}</p>
        <div class="af-barra-herramientas">
            @include('filament.partes.selector-propiedad')
        </div>

        <x-filament::section :heading="__('operacion.depositos.pendientes')">
            @php($depositos = $this->depositos())
            @if ($depositos === [])
                <p class="af-vacio">{{ __('operacion.depositos.sin_pendientes') }}</p>
            @else
                <div class="af-tabla-contenedor" tabindex="0" role="region" aria-label="{{ __('operacion.depositos.pendientes') }}">
                    <table class="af-tabla">
                        <caption class="af-sr">{{ __('operacion.depositos.pendientes') }}</caption>
                        <thead>
                            <tr>
                                <th scope="col">{{ __('informes.columnas.reserva') }}</th>
                                <th scope="col">{{ __('ui.comun.huesped') }}</th>
                                <th scope="col">{{ __('ui.comun.llegada') }}</th>
                                <th scope="col">{{ __('operacion.depositos.falta') }}</th>
                                <th scope="col">{{ __('operacion.depositos.vence') }}</th>
                                <th scope="col"><span class="af-sr">{{ __('permisos.sesiones.acciones') }}</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($depositos as $d)
                                <tr wire:key="deposito-{{ $d['reserva']->id }}" x-data>
                                    <td><a class="af-enlace" href="{{ ReservaResource::getUrl('view', ['record' => $d['reserva']]) }}">{{ $d['reserva']->numero }}</a></td>
                                    <td>{{ $d['reserva']->titular?->nombreCompleto() }}</td>
                                    <td data-orden="{{ $d['reserva']->llegada?->format('Y-m-d') }}">{{ Formato::fecha($d['reserva']->llegada) }}</td>
                                    <td>{{ Formato::dinero($d['importe'], $d['reserva']->moneda) }}</td>
                                    <td @class(['af-vencido' => $d['vencido']]) data-orden="{{ $d['vence']?->format('Y-m-d') }}">{{ Formato::fecha($d['vence']) }}@if ($d['vencido']) · {{ __('operacion.depositos.vencido') }}@endif</td>
                                    <td><x-filament::button tag="a" size="sm" color="gray" icon="heroicon-m-chat-bubble-left-ellipsis" :href="$d['whatsapp']" target="_blank" rel="noopener" x-on:click="$wire.anotarRecordatorio({{ $d['reserva']->id }})">{{ __('operacion.depositos.recordar') }}</x-filament::button></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>

        <x-filament::section :heading="__('operacion.depositos.no_shows')" :description="__('operacion.depositos.no_shows_ayuda')">
            @php($noShows = $this->noShows())
            @if ($noShows === [])
                <p class="af-vacio">{{ __('operacion.depositos.sin_no_shows') }}</p>
            @else
                <div class="af-tabla-contenedor" tabindex="0" role="region" aria-label="{{ __('operacion.depositos.no_shows') }}">
                    <table class="af-tabla">
                        <caption class="af-sr">{{ __('operacion.depositos.no_shows') }}</caption>
                        <thead>
                            <tr>
                                <th scope="col">{{ __('informes.columnas.reserva') }}</th>
                                <th scope="col">{{ __('ui.comun.huesped') }}</th>
                                <th scope="col">{{ __('ui.comun.llegada') }}</th>
                                <th scope="col">{{ __('operacion.depositos.penalizacion') }}</th>
                                <th scope="col"><span class="af-sr">{{ __('permisos.sesiones.acciones') }}</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($noShows as $n)
                                <tr wire:key="no-show-{{ $n['reserva']->id }}">
                                    <td><a class="af-enlace" href="{{ ReservaResource::getUrl('view', ['record' => $n['reserva']]) }}">{{ $n['reserva']->numero }}</a></td>
                                    <td>{{ $n['reserva']->titular?->nombreCompleto() }}</td>
                                    <td data-orden="{{ $n['reserva']->llegada?->format('Y-m-d') }}">{{ Formato::fecha($n['reserva']->llegada) }}</td>
                                    <td>{{ Formato::dinero($n['penalizacion'], $n['reserva']->moneda) }}</td>
                                    <td><x-filament::button size="sm" color="warning" wire:click="marcarNoShow({{ $n['reserva']->id }})" wire:confirm="{{ __('dinero.politicas.no_show_ayuda') }}">{{ __('dinero.politicas.no_show_accion') }}</x-filament::button></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>

        <x-filament::section :heading="__('operacion.depositos.calculadora')" :description="__('operacion.depositos.calculadora_ayuda')">
            <form wire:submit="calcular" class="af-barra-herramientas">
                <label class="af-campo">
                    <span>{{ __('informes.columnas.reserva') }}</span>
                    <x-filament::input.wrapper><x-filament::input type="text" wire:model="numero" /></x-filament::input.wrapper>
                </label>
                <x-filament::button type="submit" color="gray" wire:target="calcular">{{ __('operacion.depositos.calcular') }}</x-filament::button>
            </form>
            @if ($calculo !== null)
                <p style="margin-top: .75rem" role="status">{{ $calculo }}</p>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
