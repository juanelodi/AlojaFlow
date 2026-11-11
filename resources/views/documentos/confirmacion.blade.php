{{-- AlojaFlow · Copyright (C) 2026 Juan Galeana Durán · SPDX-License-Identifier: FSL-1.1-ALv2 --}}
@use('App\Support\Formato')
@extends('documentos.base')
@section('titulo', __('documentos.confirmacion'))
@section('contenido')
    <h2>{{ __('documentos.estancia') }}</h2>
    <table class="datos">
        <tr><th>{{ __('ui.comun.huesped') }}</th><td>{{ $reserva->titular?->nombreCompleto() ?? '—' }}</td></tr>
        <tr><th>{{ __('ui.comun.llegada') }}</th><td>{{ Formato::fecha($reserva->llegada, 'dddd LL') }}</td></tr>
        <tr><th>{{ __('ui.comun.salida') }}</th><td>{{ Formato::fecha($reserva->salida, 'dddd LL') }}</td></tr>
        <tr><th>{{ __('ui.comun.noches') }}</th><td>{{ $reserva->noches }}</td></tr>
        <tr><th>{{ __('ui.comun.unidad') }}</th><td>{{ $reserva->unidad->nombre }}</td></tr>
        <tr><th>{{ __('ui.comun.personas') }}</th><td>{{ $reserva->personas }}</td></tr>
        <tr><th>{{ __('ui.comun.estado') }}</th><td>{{ $reserva->estado->etiqueta() }}</td></tr>
    </table>

    <h2>{{ __('documentos.importes') }}</h2>
    <table class="importes">
        <tr><td>{{ __('documentos.alojamiento') }}@if ($reserva->tarifa_nombre) ({{ $reserva->tarifa_nombre }})@endif</td><td class="num">{{ Formato::dinero($alojamiento, $reserva->moneda) }}</td></tr>
        @foreach ($reserva->cargos as $cargo)
            <tr><td>{{ $cargo->nombre }}@if ((float) $cargo->cantidad !== 1.0) × {{ (float) $cargo->cantidad }}@endif</td><td class="num">{{ Formato::dinero($cargo->importe, $reserva->moneda) }}</td></tr>
        @endforeach
        @if ($reserva->descuento > 0)
            <tr><td colspan="2" class="gris">{{ __('documentos.descuento_aplicado', ['importe' => Formato::dinero($reserva->descuento, $reserva->moneda)]) }}</td></tr>
        @endif
        <tr class="total"><td>{{ __('ui.comun.total') }}</td><td class="num">{{ Formato::dinero($reserva->total, $reserva->moneda) }}</td></tr>
        <tr><td>{{ __('ui.comun.pagado') }}</td><td class="num">{{ Formato::dinero($reserva->pagado, $reserva->moneda) }}</td></tr>
        <tr><td><strong>{{ __('ui.comun.pendiente') }}</strong></td><td class="num"><strong>{{ Formato::dinero($reserva->pendiente, $reserva->moneda) }}</strong></td></tr>
    </table>
    @if ($reserva->deposito > 0 && $reserva->pagado < $reserva->deposito)
        <p class="nota">{{ __('documentos.deposito_pendiente', ['importe' => Formato::dinero($reserva->deposito, $reserva->moneda)]) }}</p>
    @endif
    @foreach ($impuestos as $impuesto)
        @if ($impuesto['incluido'])
            <p class="nota" style="margin: 2px 0">{{ __('documentos.impuesto_incluido', ['impuesto' => $impuesto['nombre']]) }}</p>
        @endif
    @endforeach
    @if ($reserva->comentario)
        <h2>{{ __('ui.reservas.comentario') }}</h2>
        <p>{{ $reserva->comentario }}</p>
    @endif
    <p class="nota">{{ __('documentos.no_fiscal') }}</p>
@endsection
