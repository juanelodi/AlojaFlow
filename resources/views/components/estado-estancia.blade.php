{{-- AlojaFlow · Copyright (C) 2026 Juan Galeana Durán · SPDX-License-Identifier: FSL-1.1-ALv2 --}}
{{-- Dónde va la estancia: lo que recepción mira antes que el estado de la reserva. --}}
@props(['reserva'])
@use('App\Enums\EstadoReserva')
@if ($reserva->eliminada_en_principal_en)
    <x-filament::badge color="danger">{{ __('ui.reservas.eliminada') }}</x-filament::badge>
@elseif ($reserva->estado === EstadoReserva::Cancelada)
    <x-filament::badge color="danger">{{ $reserva->estado->etiqueta() }}</x-filament::badge>
@elseif ($reserva->estado === EstadoReserva::SinConfirmar)
    <x-filament::badge color="warning">{{ __('ui.hoy.sin_confirmar') }}</x-filament::badge>
@elseif ($salida = $reserva->salidaEn())
    {{-- Con la hora, en la de la propiedad (D-153). --}}
    <x-filament::badge color="gray">{{ __('ui.comun.salio') }} · {{ \App\Support\Formato::hora($salida, $reserva->propiedad->zona_horaria) }}</x-filament::badge>
@elseif ($entrada = $reserva->entradaEn())
    <x-filament::badge color="success">{{ __('ui.comun.en_casa') }} · {{ \App\Support\Formato::hora($entrada, $reserva->propiedad->zona_horaria) }}</x-filament::badge>
@else
    <x-filament::badge color="info">{{ $reserva->estado->etiqueta() }}</x-filament::badge>
@endif
