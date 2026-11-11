{{-- AlojaFlow · Copyright (C) 2026 Juan Galeana Durán · SPDX-License-Identifier: FSL-1.1-ALv2 --}}
@use('App\Support\Formato')
@extends('documentos.base')
@section('titulo', __('llegada.tarjeta'))
@section('contenido')
    <h2>{{ __('documentos.estancia') }}</h2>
    <table class="datos">
        <tr><th>{{ __('ui.comun.llegada') }}</th><td>{{ Formato::fecha($reserva->llegada, 'dddd LL') }}@if ($registro->hora_llegada_estimada) · {{ $registro->hora_llegada_estimada }}@endif</td></tr>
        <tr><th>{{ __('ui.comun.salida') }}</th><td>{{ Formato::fecha($reserva->salida, 'dddd LL') }}</td></tr>
        <tr><th>{{ __('ui.comun.unidad') }}</th><td>{{ $reserva->unidad->nombre }}</td></tr>
    </table>

    @foreach ($registro->personas as $persona)
        <h2>{{ $persona->es_titular ? __('llegada.titular') : __('llegada.acompanante', ['n' => $persona->orden]) }}</h2>
        <table class="datos">
            <tr><th>{{ __('llegada.nombre') }}</th><td>{{ $persona->nombreCompleto() }}</td></tr>
            <tr><th>{{ __('llegada.fecha_nacimiento') }}</th><td>{{ Formato::fecha($persona->fecha_nacimiento) }}</td></tr>
            <tr><th>{{ __('llegada.nacionalidad') }}</th><td>{{ $persona->nacionalidad_texto ?? '—' }}</td></tr>
            <tr><th>{{ __('llegada.documento_tipo') }}</th><td>{{ $tipos[$persona->documento_tipo] ?? $persona->documento_tipo ?? '—' }}</td></tr>
            <tr><th>{{ __('llegada.documento_numero') }}</th><td>{{ $persona->documento_numero ?? '—' }}</td></tr>
            @if ($persona->es_titular)
                <tr><th>{{ __('llegada.correo') }}</th><td>{{ $persona->correo }}</td></tr>
                <tr><th>{{ __('llegada.telefono') }}</th><td>{{ $persona->telefono }}</td></tr>
                <tr><th>{{ __('llegada.domicilio') }}</th><td>{{ collect([$persona->domicilio_calle, $persona->domicilio_ciudad, $persona->domicilio_region, $persona->domicilio_codigo_postal, $persona->domicilio_pais_texto])->filter()->implode(', ') }}</td></tr>
            @endif
            @foreach ((array) $persona->datos_pais as $campo => $valor)
                <tr><th>{{ $campos[$campo]['etiqueta'] ?? $campo }}</th><td>{{ $valor }}</td></tr>
            @endforeach
        </table>
    @endforeach

    <p style="margin-top:48px">______________________________<br>{{ __('llegada.firma') }}</p>
    <p class="nota">{{ __('llegada.tarjeta_nota') }}</p>
@endsection
