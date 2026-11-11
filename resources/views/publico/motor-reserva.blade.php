{{-- AlojaFlow · Copyright (C) 2026 Juan Galeana Durán · SPDX-License-Identifier: FSL-1.1-ALv2 --}}
@use('App\Support\Formato')
@use('App\Enums\EstadoReserva')
@extends('publico.layout')
@section('titulo', __('motor.tu_reserva'))
@section('contenido')
    @if ($reserva->estado === EstadoReserva::Cancelada)
        <p class="mensaje error" role="status">{{ __('motor.estado_cancelada') }}</p>
    @elseif ($reserva->estado === EstadoReserva::Confirmada)
        <p class="mensaje bien" role="status">{{ __('motor.estado_confirmada') }}</p>
    @elseif ($espera !== null)
        <p class="mensaje" role="status">{{ __('motor.espera.titulo') }}</p>
    @elseif ($porCobrar > 0 && $pasarelas !== [])
        <p class="mensaje" role="status">{{ __('motor.estado_pagar', ['fecha' => Formato::momento($reserva->expira_en, $propiedad->zona_horaria)]) }}</p>
    @else
        <p class="mensaje" role="status">{{ __('motor.estado_pendiente') }}</p>
    @endif

    @if ($espera !== null)
        @include('publico.pago-en-espera')
    @elseif ($porCobrar > 0 && $pasarelas !== [])
        <section class="tarjeta">
            <h2>{{ __('motor.pagar_titulo', ['importe' => Formato::dinero($porCobrar, $reserva->moneda)]) }}</h2>
            <p class="gris">{{ __('motor.pagar_ayuda') }}</p>
            <div class="pagos">
            @foreach ($pasarelas as $pasarela)
                <form method="post" action="{{ route('motor.pagar', ['codigo' => $propiedad->codigo, 'publico' => $reserva->codigo_publico, 'pasarela' => $pasarela, 'idioma' => app()->getLocale()]) }}" target="_top">
                    <button type="submit">{{ __('motor.pagar_con.'.$pasarela) }}</button>
                </form>
            @endforeach
            </div>
        </section>
    @endif

    <section class="tarjeta">
        <h2>{{ __('motor.tu_reserva') }} {{ $reserva->codigo_publico }}</h2>
        <p class="gris" style="margin:0 0 8px">{{ __('motor.guardar_enlace') }}</p>
        <table>
            <tr><th>{{ __('ui.comun.huesped') }}</th><td>{{ $reserva->titular?->nombreCompleto() }}</td></tr>
            <tr><th>{{ __('ui.comun.llegada') }}</th><td>{{ Formato::fecha($reserva->llegada, 'dddd LL') }}</td></tr>
            <tr><th>{{ __('ui.comun.salida') }}</th><td>{{ Formato::fecha($reserva->salida, 'dddd LL') }}</td></tr>
            <tr><th>{{ __('ui.reservas.tarifa') }}</th><td>{{ $reserva->tarifa_nombre }}</td></tr>
            @if ($pedido->count() > 1)
                {{-- Un pedido de varias unidades (D-158): cada una con sus personas, y los importes de todas. --}}
                @foreach ($pedido as $una)
                    <tr><th>{{ __('motor.unidad_n', ['n' => $loop->iteration]) }}</th><td>{{ trans_choice('motor.n_personas', $una->personas) }} · {{ Formato::dinero($una->total, $una->moneda) }}</td></tr>
                @endforeach
            @else
                <tr><th>{{ __('ui.comun.personas') }}</th><td>{{ $reserva->personas }}</td></tr>
            @endif
            @foreach ($reserva->cargos as $cargo)
                <tr><th>{{ $cargo->nombre }}</th><td>{{ Formato::dinero($cargo->importe, $reserva->moneda) }}</td></tr>
            @endforeach
            @php($total = (int) $pedido->sum('total'))
            <tr><th>{{ __('ui.comun.total') }}</th><td><strong>{{ Formato::dinero($total, $reserva->moneda) }}</strong>@if ($segunda = (new \App\Dinero\SegundaMoneda($reserva->propiedad))->texto($total)) <span class="gris">{{ $segunda }}</span>@endif</td></tr>
            <tr><th>{{ __('ui.comun.pagado') }}</th><td>{{ Formato::dinero((int) $pedido->sum('pagado'), $reserva->moneda) }}</td></tr>
            <tr><th>{{ __('ui.comun.pendiente') }}</th><td>{{ Formato::dinero((int) $pedido->sum('pendiente'), $reserva->moneda) }}</td></tr>
        </table>
        <p style="margin:12px 0 0"><a class="boton secundario" href="{{ route('motor.pdf', ['codigo' => $propiedad->codigo, 'publico' => $reserva->codigo_publico, 'idioma' => app()->getLocale()]) }}">{{ __('documentos.descargar_confirmacion') }}</a></p>
    </section>

    @include('publico.calendario-cobros')

    @include('publico.comprobante-reserva', ['accionComprobante' => route('motor.comprobante', ['codigo' => $propiedad->codigo, 'publico' => $reserva->codigo_publico, 'idioma' => app()->getLocale()])])
@endsection
