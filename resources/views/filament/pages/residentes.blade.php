{{-- AlojaFlow · Copyright (C) 2026 Juan Galeana Durán · SPDX-License-Identifier: FSL-1.1-ALv2 --}}
@use('App\Filament\Resources\Reservas\ReservaResource')
@use('App\Support\Formato')
<x-filament-panels::page>
    <div class="af-pila">
        <p class="af-nota">{{ __('camping.residentes.ayuda') }}</p>
        <div class="af-barra-herramientas">
            @include('filament.partes.selector-propiedad')
            @if ($this->urlMensajes())
                <x-filament::button tag="a" color="gray" icon="heroicon-m-chat-bubble-left-right" :href="$this->urlMensajes()">{{ __('camping.residentes.escribir_a_todos') }}</x-filament::button>
            @endif
        </div>

        <x-filament::section>
            @php($filas = $this->filas())
            @if ($filas === [])
                <p class="af-vacio">{{ __('camping.residentes.vacio') }}</p>
            @else
                <div class="af-tabla-contenedor" tabindex="0" role="region" aria-label="{{ __('camping.residentes.titulo') }}">
                    <table class="af-tabla">
                        <caption class="af-sr">{{ __('camping.residentes.titulo') }}</caption>
                        <thead>
                            <tr>
                                <th scope="col">{{ __('ui.comun.unidad') }}</th>
                                <th scope="col">{{ __('camping.residentes.residente') }}</th>
                                <th scope="col">{{ __('ui.comun.llegada') }}</th>
                                <th scope="col">{{ __('ui.comun.salida') }}</th>
                                <th scope="col" class="af-num">{{ __('ui.comun.pendiente') }}</th>
                                <th scope="col" class="af-num">{{ __('camping.residentes.toca_pagar') }}</th>
                                <th scope="col">{{ __('camping.residentes.reglamento') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($filas as $fila)
                                <tr wire:key="residente-{{ $fila['reserva']->id }}">
                                    <td><a class="af-enlace" href="{{ ReservaResource::getUrl('view', ['record' => $fila['reserva']]) }}">{{ $fila['reserva']->unidad?->nombre }}</a></td>
                                    <td>{{ $fila['reserva']->titular?->nombreCompleto() }}</td>
                                    <td data-orden="{{ $fila['reserva']->llegada?->format('Y-m-d') }}">{{ Formato::fecha($fila['reserva']->llegada) }}</td>
                                    <td data-orden="{{ $fila['reserva']->salida?->format('Y-m-d') }}">{{ Formato::fecha($fila['reserva']->salida) }}</td>
                                    <td class="af-num">{{ Formato::dinero($fila['reserva']->pendiente, $fila['reserva']->moneda) }}</td>
                                    <td class="af-num">{{ $fila['por_pagar'] > 0 ? Formato::dinero($fila['por_pagar'], $fila['reserva']->moneda) : '—' }}</td>
                                    <td>
                                        @if ($fila['reglamento'] === 'firmado')
                                            {{ __('camping.residentes.firmado') }}
                                        @elseif ($fila['reglamento'] === 'falta')
                                            <x-filament::button tag="a" size="sm" color="gray" icon="heroicon-m-chat-bubble-left-ellipsis" :href="$fila['whatsapp']" target="_blank" rel="noopener">{{ __('camping.residentes.pedir') }}</x-filament::button>
                                        @else
                                            —
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
