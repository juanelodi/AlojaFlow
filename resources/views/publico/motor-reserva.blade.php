{{-- AlojaFlow · SPDX-License-Identifier: AGPL-3.0-or-later --}}
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

    <section class="tarjeta">
        <h2>{{ __('motor.tu_reserva') }} {{ $reserva->codigo_publico }}</h2>
        <table>
            <tr><th>{{ __('ui.comun.huesped') }}</th><td>{{ $reserva->titular?->nombreCompleto() }}</td></tr>
            <tr><th>{{ __('ui.comun.llegada') }}</th><td>{{ Formato::fecha($reserva->llegada, 'dddd LL') }}</td></tr>
            <tr><th>{{ __('ui.comun.salida') }}</th><td>{{ Formato::fecha($reserva->salida, 'dddd LL') }}</td></tr>
            <tr><th>{{ __('ui.reservas.tarifa') }}</th><td>{{ $reserva->tarifa_nombre }}</td></tr>
            <tr><th>{{ __('ui.comun.personas') }}</th><td>{{ $reserva->personas }}</td></tr>
            @foreach ($reserva->cargos as $cargo)
                <tr><th>{{ $cargo->nombre }}</th><td>{{ Formato::dinero($cargo->importe, $reserva->moneda) }}</td></tr>
            @endforeach
            <tr><th>{{ __('ui.comun.total') }}</th><td><strong>{{ Formato::dinero($reserva->total, $reserva->moneda) }}</strong></td></tr>
            <tr><th>{{ __('ui.comun.pagado') }}</th><td>{{ Formato::dinero($reserva->pagado, $reserva->moneda) }}</td></tr>
            <tr><th>{{ __('ui.comun.pendiente') }}</th><td>{{ Formato::dinero($reserva->pendiente, $reserva->moneda) }}</td></tr>
        </table>
        <p style="margin:12px 0 0"><a class="boton secundario" href="{{ route('motor.pdf', ['codigo' => $propiedad->codigo, 'publico' => $reserva->codigo_publico, 'idioma' => app()->getLocale()]) }}">{{ __('documentos.descargar_confirmacion') }}</a></p>
    </section>

    @if ($espera !== null)
        @include('publico.pago-en-espera')
    @elseif ($porCobrar > 0 && $pasarelas !== [])
        <section class="tarjeta">
            <h2>{{ __('motor.pagar_titulo', ['importe' => Formato::dinero($porCobrar, $reserva->moneda)]) }}</h2>
            <p class="gris">{{ __('motor.pagar_ayuda') }}</p>
            @foreach ($pasarelas as $pasarela)
                <form method="post" action="{{ route('motor.pagar', ['codigo' => $propiedad->codigo, 'publico' => $reserva->codigo_publico, 'pasarela' => $pasarela, 'idioma' => app()->getLocale()]) }}" target="_top" style="display:inline-block;margin:4px 8px 4px 0">
                    <button type="submit">{{ __('motor.pagar_con.'.$pasarela) }}</button>
                </form>
            @endforeach
        </section>
    @endif


    @include('publico.calendario-cobros')

    @include('publico.comprobante-reserva', ['accionComprobante' => route('motor.comprobante', ['codigo' => $propiedad->codigo, 'publico' => $reserva->codigo_publico, 'idioma' => app()->getLocale()])])
@endsection
