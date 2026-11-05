{{-- AlojaFlow · SPDX-License-Identifier: AGPL-3.0-or-later --}}
@use('App\Support\Formato')
@extends('publico.layout')
@section('titulo', __('operacion.enlaces.pagina_titulo'))
@section('contenido')
    @if ($aviso === 'pasarela')
        <p class="mensaje error" role="alert">{{ __('motor.error.pasarela') }}</p>
    @endif
    @php($estado = $enlace->estado())
    @if ($estado === 'pagado')
        <p class="mensaje bien" role="status">{{ __('operacion.enlaces.estado_pagado') }}</p>
    @elseif ($estado === 'vencido')
        <p class="mensaje error" role="alert">{{ __('operacion.enlaces.estado_vencido') }}</p>
    @elseif ($estado === 'anulado')
        <p class="mensaje error" role="alert">{{ __('operacion.enlaces.estado_anulado') }}</p>
    @endif
    <section class="tarjeta">
        <h2>{{ $enlace->concepto }}</h2>
        <p class="precio" style="margin:0 0 4px">{{ Formato::dinero($enlace->importe, $enlace->moneda) }}</p>
        <p class="gris" style="margin:0">{{ __('operacion.enlaces.de_la_reserva', ['numero' => $enlace->reserva->numero]) }} · {{ Formato::fecha($enlace->reserva->llegada, 'LL') }}</p>
        @if ($estado === 'vigente')
            <p class="gris" style="margin:8px 0 0">{{ __('operacion.enlaces.vence', ['fecha' => Formato::momento($enlace->vence_en, $propiedad->zona_horaria)]) }}</p>
        @endif
    </section>
    @if ($espera !== null)
        @include('publico.pago-en-espera')
    @elseif ($estado === 'vigente' && $pasarelas !== [])
        <section class="tarjeta">
            @foreach ($pasarelas as $pasarela)
                <form method="post" action="{{ route('pagar.pagar', ['token' => $token, 'pasarela' => $pasarela, 'idioma' => app()->getLocale()]) }}" style="margin-bottom:8px">
                    @csrf
                    <button type="submit">{{ __('motor.pagar_con.'.$pasarela) }}</button>
                </form>
            @endforeach
        </section>
    @endif
@endsection
