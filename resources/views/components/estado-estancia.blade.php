{{-- AlojaFlow · SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Dónde va la estancia: lo que recepción mira antes que el estado de la reserva. --}}
@props(['reserva'])
@use('App\Enums\EstadoReserva')
@if ($reserva->eliminada_en_principal_en)
    <x-filament::badge color="danger">{{ __('ui.reservas.eliminada') }}</x-filament::badge>
@elseif ($reserva->estado === EstadoReserva::Cancelada)
    <x-filament::badge color="danger">{{ $reserva->estado->etiqueta() }}</x-filament::badge>
@elseif ($reserva->estado === EstadoReserva::SinConfirmar)
    <x-filament::badge color="warning">{{ __('ui.hoy.sin_confirmar') }}</x-filament::badge>
@elseif ($reserva->salida_registrada_en)
    <x-filament::badge color="gray">{{ __('ui.comun.salio') }}</x-filament::badge>
@elseif ($reserva->entrada_registrada_en)
    <x-filament::badge color="success">{{ __('ui.comun.en_casa') }}</x-filament::badge>
@else
    <x-filament::badge color="info">{{ $reserva->estado->etiqueta() }}</x-filament::badge>
@endif
