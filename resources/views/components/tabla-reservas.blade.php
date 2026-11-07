{{-- AlojaFlow · SPDX-License-Identifier: AGPL-3.0-or-later --}}
@props(['reservas', 'vacio', 'titulo', 'mostrarPropiedad' => false, 'gestionables' => []])
@use('App\Support\Formato')
@use('App\Enums\EstadoReserva')
@use('App\Seguridad\Acceso')
{{-- Teléfono, saldo y ficha, solo con permiso de datos: limpieza y mantenimiento no los necesitan. --}}
@php($conDatos = Acceso::propiedades(Acceso::usuarioActual(), Acceso::DATOS))
<div class="af-tabla-contenedor" tabindex="0" role="region" aria-label="{{ $titulo }}">
    @if ($reservas->isEmpty())
        <p class="af-vacio-tabla">{{ $vacio }}</p>
    @else
        <table class="af-tabla af-tabla--apilable">
            <caption class="af-sr">{{ $titulo }}</caption>
            <thead>
                <tr>
                    <th scope="col">{{ __('ui.comun.unidad') }}</th>
                    <th scope="col">{{ __('ui.comun.huesped') }}</th>
                    <th scope="col">{{ __('ui.comun.llegada') }} → {{ __('ui.comun.salida') }}</th>
                    <th scope="col" class="af-num">{{ __('ui.comun.personas') }}</th>
                    <th scope="col">{{ __('ui.comun.origen') }}</th>
                    @if ($conDatos !== [])<th scope="col" class="af-num">{{ __('ui.comun.pendiente') }}</th>@endif
                    <th scope="col">{{ __('ui.comun.estado') }}</th>
                    @if ($conDatos !== [])<th scope="col">{{ __('ui.comun.contacto') }}</th>@endif
                </tr>
            </thead>
            <tbody>
                @foreach ($reservas as $reserva)
                    @php($verDatos = in_array($reserva->propiedad_id, $conDatos, true))
                    @php($telefono = $verDatos ? $reserva->titular?->contactos->first(fn ($c) => $c->tipo === \App\Enums\TipoContacto::Telefono) : null)
                    <tr wire:key="reserva-{{ $reserva->id }}">
                        <td>
                            {{ $reserva->unidad->nombre }}
                            @if ($mostrarPropiedad)<span class="af-secundario">{{ $reserva->propiedad->nombre }}</span>@endif
                        </td>
                        <td>
                            @if ($verDatos)
                                <a href="{{ \App\Filament\Resources\Reservas\ReservaResource::getUrl('view', ['record' => $reserva]) }}">
                                    {{ $reserva->titular?->nombreCompleto() ?? '—' }}
                                </a>
                            @else
                                {{ $reserva->titular?->nombreCompleto() ?? '—' }}
                            @endif
                            <span class="af-secundario">{{ $reserva->numero }}</span>
                            @if (in_array($reserva->propiedad_id, $gestionables, true))
                                {{-- Junto al nombre: es lo primero que busca recepción y no queda fuera de la pantalla. --}}
                                <div class="af-accion-fila">
                                    @if ($reserva->entrada_registrada_en === null)
                                        <x-filament::button size="xs" color="success" wire:click="registrarEntrada({{ $reserva->id }})" wire:loading.attr="disabled">{{ __('ui.reservas.registrar_entrada') }}</x-filament::button>
                                    @elseif ($reserva->salida_registrada_en === null)
                                        <x-filament::button size="xs" color="info" wire:click="registrarSalida({{ $reserva->id }})" wire:loading.attr="disabled">{{ __('ui.reservas.registrar_salida') }}</x-filament::button>
                                    @endif
                                </div>
                            @endif
                        </td>
                        <td data-etiqueta="{{ __('ui.comun.llegada') }} → {{ __('ui.comun.salida') }}" data-orden="{{ $reserva->llegada->format('Y-m-d') }}">
                            {{ Formato::fechaCorta($reserva->llegada) }} → {{ Formato::fechaCorta($reserva->salida) }}
                            <span class="af-secundario">{{ trans_choice('ui.comun.n_noches', $reserva->noches) }}</span>
                        </td>
                        <td class="af-num" data-etiqueta="{{ __('ui.comun.personas') }}">{{ $reserva->personas }}</td>
                        <td data-etiqueta="{{ __('ui.comun.origen') }}">{{ $reserva->origen_texto ?? '—' }}</td>
                        @if ($conDatos !== [])
                            <td class="af-num" data-etiqueta="{{ __('ui.comun.pendiente') }}">
                                @if ($verDatos && $reserva->pendiente > 0)
                                    <strong>{{ Formato::dinero($reserva->pendiente, $reserva->moneda) }}</strong>
                                @else
                                    —
                                @endif
                            </td>
                        @endif
                        <td data-etiqueta="{{ __('ui.comun.estado') }}">
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
                        @if ($conDatos !== [])
                        <td data-etiqueta="{{ __('ui.comun.contacto') }}">
                            @if ($telefono)
                                <span class="af-iconos">
                                    <a href="tel:{{ $telefono->valor_normalizado }}" title="{{ __('ui.comun.llamar') }}" aria-label="{{ __('ui.comun.llamar') }}: {{ $telefono->valor }}">
                                        <x-filament::icon icon="heroicon-o-phone" class="h-5 w-5" />
                                    </a>
                                    @if ($whatsapp = $telefono->enlaceWhatsapp($reserva->propiedad->pais))
                                        <a href="{{ $whatsapp }}" target="_blank" rel="noopener noreferrer" title="{{ __('ui.comun.whatsapp') }}" aria-label="{{ __('ui.comun.whatsapp') }}">
                                            <x-filament::icon icon="heroicon-o-chat-bubble-left-ellipsis" class="h-5 w-5" />
                                        </a>
                                    @endif
                                </span>
                                <span class="af-secundario">{{ $telefono->valor }}</span>
                            @else
                                —
                            @endif
                        </td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
