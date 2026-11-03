{{-- AlojaFlow · SPDX-License-Identifier: AGPL-3.0-or-later --}}
@props(['reservas', 'vacio', 'titulo', 'mostrarPropiedad' => false])
@use('App\Support\Formato')
@use('App\Enums\EstadoReserva')
<div class="af-tabla-contenedor">
    @if ($reservas->isEmpty())
        <p class="af-vacio-tabla">{{ $vacio }}</p>
    @else
        <table class="af-tabla">
            <caption class="af-sr">{{ $titulo }}</caption>
            <thead>
                <tr>
                    <th scope="col">{{ __('ui.comun.unidad') }}</th>
                    <th scope="col">{{ __('ui.comun.huesped') }}</th>
                    <th scope="col">{{ __('ui.comun.llegada') }} → {{ __('ui.comun.salida') }}</th>
                    <th scope="col" class="af-num">{{ __('ui.comun.personas') }}</th>
                    <th scope="col">{{ __('ui.comun.origen') }}</th>
                    <th scope="col" class="af-num">{{ __('ui.comun.pendiente') }}</th>
                    <th scope="col">{{ __('ui.comun.estado') }}</th>
                    <th scope="col">{{ __('ui.comun.contacto') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($reservas as $reserva)
                    @php($telefono = $reserva->titular?->contactos->first(fn ($c) => $c->tipo === \App\Enums\TipoContacto::Telefono))
                    <tr wire:key="reserva-{{ $reserva->id }}">
                        <td>
                            {{ $reserva->unidad->nombre }}
                            @if ($mostrarPropiedad)<span class="af-secundario">{{ $reserva->propiedad->nombre }}</span>@endif
                        </td>
                        <td>
                            <a href="{{ \App\Filament\Resources\Reservas\ReservaResource::getUrl('view', ['record' => $reserva]) }}">
                                {{ $reserva->titular?->nombreCompleto() ?? '—' }}
                            </a>
                            <span class="af-secundario">{{ $reserva->numero }}</span>
                        </td>
                        <td>
                            {{ Formato::fechaCorta($reserva->llegada) }} → {{ Formato::fechaCorta($reserva->salida) }}
                            <span class="af-secundario">{{ trans_choice('ui.comun.n_noches', $reserva->noches) }}</span>
                        </td>
                        <td class="af-num">{{ $reserva->personas }}</td>
                        <td>{{ $reserva->origen_texto ?? '—' }}</td>
                        <td class="af-num">
                            @if ($reserva->pendiente > 0)
                                <strong>{{ Formato::dinero($reserva->pendiente, $reserva->moneda) }}</strong>
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            @if ($reserva->estado === EstadoReserva::SinConfirmar)
                                <x-filament::badge color="warning">{{ __('ui.hoy.sin_confirmar') }}</x-filament::badge>
                            @elseif ($reserva->salida_registrada_en)
                                <x-filament::badge color="gray">{{ __('ui.comun.salio') }}</x-filament::badge>
                            @elseif ($reserva->entrada_registrada_en)
                                <x-filament::badge color="success">{{ __('ui.comun.en_casa') }}</x-filament::badge>
                            @else
                                <x-filament::badge color="info">{{ $reserva->estado->etiqueta() }}</x-filament::badge>
                            @endif
                        </td>
                        <td>
                            @if ($telefono)
                                <span class="af-iconos">
                                    <a href="tel:{{ $telefono->valor_normalizado }}" title="{{ __('ui.comun.llamar') }}" aria-label="{{ __('ui.comun.llamar') }}: {{ $telefono->valor }}">
                                        <x-filament::icon icon="heroicon-o-phone" class="h-5 w-5" />
                                    </a>
                                    @if ($telefono->enlaceWhatsapp())
                                        <a href="{{ $telefono->enlaceWhatsapp() }}" target="_blank" rel="noopener noreferrer" title="{{ __('ui.comun.whatsapp') }}" aria-label="{{ __('ui.comun.whatsapp') }}">
                                            <x-filament::icon icon="heroicon-o-chat-bubble-left-ellipsis" class="h-5 w-5" />
                                        </a>
                                    @endif
                                </span>
                                <span class="af-secundario">{{ $telefono->valor }}</span>
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
