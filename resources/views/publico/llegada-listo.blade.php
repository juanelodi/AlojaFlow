{{-- AlojaFlow · Copyright (C) 2026 Juan Galeana Durán · SPDX-License-Identifier: FSL-1.1-ALv2 --}}
@use('App\Support\Formato')
@extends('publico.layout')
@section('titulo', __('llegada.listo_titulo'))
@section('contenido')
    <p class="mensaje bien" role="status">{{ __('llegada.listo_titulo') }}</p>
    <section class="tarjeta">
        <h2>{{ __('motor.tu_reserva') }}</h2>
        <p class="gris" style="margin:0">{{ Formato::fecha($reserva->llegada, 'dddd LL') }} → {{ Formato::fecha($reserva->salida, 'dddd LL') }}</p>
        <p style="margin:12px 0 0">{{ __('llegada.listo_ayuda') }}</p>
    </section>
@endsection
