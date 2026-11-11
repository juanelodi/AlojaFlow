{{-- AlojaFlow · Copyright (C) 2026 Juan Galeana Durán · SPDX-License-Identifier: FSL-1.1-ALv2 --}}
{{--
    Una reserva en el teléfono: lo esencial de un vistazo y los botones al alcance. La usan «Hoy» y la vista de día del calendario.
    «verDatos» abre la ficha, el saldo y el teléfono; «gestionable», registrar la entrada y la salida (solo donde la página las atiende);
    «ajena», una reserva que este usuario no puede ver: solo se dice que está ocupado.
--}}
@props(['reserva', 'titulo' => null, 'verDatos' => false, 'gestionable' => false, 'mostrarUnidad' => true, 'mostrarPropiedad' => false, 'momento' => null, 'ajena' => false])
@use('App\Enums\TipoContacto')
@use('App\Enums\TipoReserva')
@use('App\Filament\Resources\Reservas\ReservaResource')
@use('App\Support\Formato')
@php
    $esBloqueo = $reserva->tipo === TipoReserva::Bloqueo;
    $sinDetalle = $ajena || $esBloqueo;
    $nombre = $titulo ?? $reserva->titular?->nombreCompleto() ?? '—';
    $telefono = $verDatos ? $reserva->titular?->contactos->first(fn ($c) => $c->tipo === TipoContacto::Telefono) : null;
    $whatsapp = $telefono?->enlaceWhatsapp($reserva->propiedad->pais);
    $detalle = array_filter([
        $mostrarUnidad ? $reserva->unidad->nombre : null,
        $mostrarPropiedad ? $reserva->propiedad->nombre : null,
        $sinDetalle ? null : __('ui.reservas.numero').' '.$reserva->numero,
        $sinDetalle ? null : trans_choice('ui.comun.n_personas', $reserva->personas),
        $sinDetalle ? null : $reserva->origen_texto,
    ]);
    $conEntrada = $gestionable && $reserva->entradaEn() === null;
    $conSalida = $gestionable && $reserva->entradaEn() !== null && $reserva->salidaEn() === null;
@endphp
<div {{ $attributes->class('af-fila-movil af-tarjeta-reserva') }}>
    <div class="af-fila-movil__cabecera">
        @if ($verDatos)
            <a class="af-tarjeta-reserva__nombre" href="{{ ReservaResource::getUrl('view', ['record' => $reserva]) }}">{{ $nombre }}</a>
        @else
            <strong class="af-tarjeta-reserva__nombre">{{ $nombre }}</strong>
        @endif
        @if ($esBloqueo)
            <x-filament::badge color="gray">{{ __('ui.calendario.bloqueo') }}</x-filament::badge>
        @elseif (! $ajena)
            <x-estado-estancia :reserva="$reserva" />
        @endif
    </div>
    <div>@if ($momento)<strong>{{ $momento }}</strong> · @endif{{ Formato::fechaCorta($reserva->llegada) }} → {{ Formato::fechaCorta($reserva->salida) }} · {{ trans_choice('ui.comun.n_noches', $reserva->noches) }}</div>
    @if ($detalle !== [])
        <div class="af-secundario">{{ implode(' · ', $detalle) }}</div>
    @endif
    @if ($verDatos && $reserva->pendiente > 0)
        <div class="af-fila-movil__saldo">{{ __('ui.comun.pendiente') }}: <strong>{{ Formato::dinero($reserva->pendiente, $reserva->moneda) }}</strong></div>
    @endif
    @if ($conEntrada || $conSalida || $telefono)
        <div class="af-tarjeta-reserva__acciones">
            @if ($conEntrada)
                <x-filament::button color="success" wire:click="registrarEntrada({{ $reserva->id }})" wire:loading.attr="disabled">{{ __('ui.reservas.registrar_entrada') }}</x-filament::button>
            @elseif ($conSalida)
                <x-filament::button color="info" wire:click="registrarSalida({{ $reserva->id }})" wire:loading.attr="disabled">{{ __('ui.reservas.registrar_salida') }}</x-filament::button>
            @endif
            @if ($telefono)
                <span class="af-iconos">
                    <a href="tel:{{ $telefono->valor_normalizado }}" title="{{ __('ui.comun.llamar') }}" aria-label="{{ __('ui.comun.llamar') }}: {{ $telefono->valor }}">
                        <x-filament::icon icon="heroicon-o-phone" class="h-5 w-5" />
                    </a>
                    @if ($whatsapp)
                        <a href="{{ $whatsapp }}" target="_blank" rel="noopener noreferrer" title="{{ __('ui.comun.whatsapp') }}" aria-label="{{ __('ui.comun.whatsapp') }}: {{ $telefono->valor }}">
                            <x-filament::icon icon="heroicon-o-chat-bubble-left-ellipsis" class="h-5 w-5" />
                        </a>
                    @endif
                </span>
            @endif
        </div>
    @endif
</div>
