{{-- AlojaFlow · SPDX-License-Identifier: AGPL-3.0-or-later --}}
@use('App\Support\Formato')
@extends('documentos.base')
@section('titulo', __($pago->importe < 0 ? 'documentos.devolucion' : 'documentos.recibo'))
@section('contenido')
    <table class="datos">
        <tr><th>{{ __('ui.comun.huesped') }}</th><td>{{ $reserva->titular?->nombreCompleto() ?? '—' }}</td></tr>
        <tr><th>{{ __('ui.pagos.fecha') }}</th><td>{{ Formato::fecha($pago->fecha, 'LL') }}</td></tr>
        <tr><th>{{ __('ui.pagos.metodo') }}</th><td>{{ $pago->metodo->etiqueta() }}</td></tr>
        @if ($pago->referencia)
            <tr><th>{{ __('ui.pagos.referencia') }}</th><td>{{ $pago->referencia }}</td></tr>
        @endif
        <tr><th>{{ __('documentos.concepto') }}</th><td>{{ __('documentos.concepto_estancia', ['unidad' => $reserva->unidad->nombre, 'llegada' => Formato::fecha($reserva->llegada), 'salida' => Formato::fecha($reserva->salida)]) }}</td></tr>
    </table>

    <table class="importes" style="margin-top: 16px">
        <tr class="total"><td>{{ __('ui.pagos.importe') }}</td><td class="num">{{ Formato::dinero(abs($pago->importe), $pago->moneda) }}</td></tr>
        <tr><td>{{ __('ui.comun.total') }}</td><td class="num">{{ Formato::dinero($reserva->total, $reserva->moneda) }}</td></tr>
        <tr><td>{{ __('ui.comun.pagado') }}</td><td class="num">{{ Formato::dinero($reserva->pagado, $reserva->moneda) }}</td></tr>
        <tr><td><strong>{{ __('ui.comun.pendiente') }}</strong></td><td class="num"><strong>{{ Formato::dinero($reserva->pendiente, $reserva->moneda) }}</strong></td></tr>
    </table>
    <p class="nota">{{ __('documentos.no_fiscal') }}</p>
@endsection
