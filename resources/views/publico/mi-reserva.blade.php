{{-- AlojaFlow · Copyright (C) 2026 Juan Galeana Durán · SPDX-License-Identifier: FSL-1.1-ALv2 --}}
@use('App\Support\Formato')
@use('App\Enums\EstadoReserva')
@extends('publico.layout')
@section('titulo', __('portal.mi_reserva'))
@section('contenido')
    @if (in_array($aviso, ['pedido', 'cancelada', 'no_cancelada'], true))
        <p class="mensaje {{ $aviso === 'no_cancelada' ? 'error' : 'bien' }}" role="status">{{ __('portal.avisos.'.$aviso) }}</p>
    @endif
    @if ($reserva->estado === EstadoReserva::Cancelada)
        <p class="mensaje error" role="status">{{ __('motor.estado_cancelada') }}</p>
    @elseif ($espera !== null)
        <p class="mensaje" role="status">{{ __('motor.espera.titulo') }}</p>
    @elseif ($reserva->estado === EstadoReserva::Confirmada)
        <p class="mensaje bien" role="status">{{ __('motor.estado_confirmada') }}</p>
    @else
        <p class="mensaje" role="status">{{ __('motor.estado_pendiente') }}</p>
    @endif

    <section class="tarjeta">
        <h2>{{ __('motor.tu_reserva') }} {{ $reserva->codigo_publico }}</h2>
        <table>
            <tr><th scope="row">{{ __('ui.comun.huesped') }}</th><td>{{ $reserva->titular?->nombreCompleto() }}</td></tr>
            <tr><th scope="row">{{ __('ui.comun.llegada') }}</th><td>{{ Formato::fecha($reserva->llegada, 'dddd LL') }}</td></tr>
            <tr><th scope="row">{{ __('ui.comun.salida') }}</th><td>{{ Formato::fecha($reserva->salida, 'dddd LL') }}</td></tr>
            <tr><th scope="row">{{ __('ui.comun.personas') }}</th><td>{{ $reserva->personas }}</td></tr>
            @if ($codigoAcceso !== null)
                {{-- Desde el día antes de llegar (D-156). --}}
                <tr><th scope="row">{{ __('portal.codigo_acceso') }}</th><td><strong>{{ $codigoAcceso }}</strong></td></tr>
            @endif
            <tr><th scope="row">{{ __('ui.comun.total') }}</th><td><strong>{{ Formato::dinero($reserva->total, $reserva->moneda) }}</strong>@if ($segunda = (new \App\Dinero\SegundaMoneda($propiedad))->texto($reserva->total)) <span class="gris">{{ $segunda }}</span>@endif</td></tr>
            <tr><th scope="row">{{ __('ui.comun.pagado') }}</th><td>{{ Formato::dinero($reserva->pagado, $reserva->moneda) }}</td></tr>
            <tr><th scope="row">{{ __('ui.comun.pendiente') }}</th><td>{{ Formato::dinero($reserva->pendiente, $reserva->moneda) }}@if ($reserva->pendiente > 0 && ($segunda = (new \App\Dinero\SegundaMoneda($propiedad))->texto($reserva->pendiente))) <span class="gris">{{ $segunda }}</span>@endif</td></tr>
        </table>
        <p style="margin:12px 0 0"><a class="boton secundario" href="{{ route('mi-reserva.pdf', ['token' => $token, 'idioma' => app()->getLocale()]) }}">{{ __('documentos.descargar_confirmacion') }}</a></p>
    </section>

    @if ($espera !== null)
        @include('publico.pago-en-espera')
    @elseif ($porCobrar > 0 && $pasarelas !== [])
        <section class="tarjeta">
            <h2>{{ __('motor.pagar_titulo', ['importe' => Formato::dinero($porCobrar, $reserva->moneda)]) }}</h2>
            <p class="gris">{{ __('motor.pagar_ayuda') }}</p>
            @foreach ($pasarelas as $pasarela)
                <form method="post" action="{{ route('motor.pagar', ['codigo' => $propiedad->codigo, 'publico' => $reserva->codigo_publico, 'pasarela' => $pasarela, 'idioma' => app()->getLocale()]) }}" style="margin-bottom:8px">
                    <button type="submit">{{ __('motor.pagar_con.'.$pasarela) }}</button>
                </form>
            @endforeach
        </section>
    @endif

    @include('publico.calendario-cobros')

    @if ($propiedad->esIndependiente())
        @include('publico.comprobante-reserva', ['conCsrf' => true, 'accionComprobante' => route('mi-reserva.comprobante', ['token' => $token, 'idioma' => app()->getLocale()])])
    @endif

    @if ($enlaceLlegada)
        <section class="tarjeta">
            <h2>{{ __('portal.registro') }}</h2>
            <p class="gris">{{ __('portal.registro_ayuda') }}</p>
            <a class="boton secundario" href="{{ $enlaceLlegada }}">{{ __('portal.registro_boton') }}</a>
        </section>
    @endif

    @include('publico.guia-secciones')

    @if ($reserva->estado !== EstadoReserva::Cancelada)
        <section class="tarjeta" id="pedir">
            <h2>{{ __('portal.pedir') }}</h2>
            <p class="gris">{{ __('portal.pedir_ayuda') }}</p>
            @if ($errors->any())
                <p class="mensaje error" role="alert">{{ $errors->first() }}</p>
            @endif
            <form method="post" action="{{ route('mi-reserva.pedir', ['token' => $token, 'idioma' => app()->getLocale()]) }}">
                @csrf
                <div class="campo">
                    <label for="mensaje">{{ __('portal.mensaje') }}</label>
                    <textarea id="mensaje" name="mensaje" rows="3" maxlength="2000" required>{{ old('mensaje') }}</textarea>
                </div>
                <div class="oculto" aria-hidden="true"><label for="sitio_web">Web</label><input type="text" id="sitio_web" name="sitio_web" tabindex="-1" autocomplete="off"></div>
                <button type="submit">{{ __('portal.enviar') }}</button>
            </form>
            @include('publico.contacto')
        </section>

        <section class="tarjeta">
            <h2>{{ __('portal.cancelar') }}</h2>
            @if ($politica)
                <p class="gris" style="white-space:pre-line">{{ $politica }}</p>
            @endif
            @if ($puedeCancelar)
                <form method="post" action="{{ route('mi-reserva.cancelar', ['token' => $token, 'idioma' => app()->getLocale()]) }}">
                    @csrf
                    <label class="casilla"><input type="checkbox" name="confirmo" value="1" required> <span>{{ __('portal.cancelar_confirmo') }}</span></label>
                    <button type="submit" class="boton secundario">{{ __('portal.cancelar_boton') }}</button>
                </form>
            @else
                <p class="gris" style="margin:0">{{ __('portal.cancelar_pedir') }}</p>
            @endif
        </section>
    @endif
@endsection
