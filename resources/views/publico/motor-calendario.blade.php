{{-- AlojaFlow · SPDX-License-Identifier: AGPL-3.0-or-later --}}
@use('App\Support\Formato')
@extends('publico.layout')
@section('titulo', __('motor.calendario.titulo'))
@section('contenido')
    <section class="tarjeta">
        <h2>{{ __('motor.calendario.titulo') }}</h2>
        <p class="gris" style="margin:0 0 12px">{{ __('motor.calendario.ayuda') }}</p>
        <div class="oferta" style="margin-bottom:8px">
            <strong style="flex:1 1 auto">{{ Formato::fecha($mes, 'MMMM YYYY') }}</strong>
            <div>
                @if ($anterior)
                    <a class="boton secundario" href="{{ route('motor.calendario', ['codigo' => $propiedad->codigo, 'mes' => $anterior, 'personas' => $personas, 'idioma' => app()->getLocale()]) }}" aria-label="{{ __('motor.calendario.anterior') }}">←</a>
                @endif
                @if ($siguiente)
                    <a class="boton secundario" href="{{ route('motor.calendario', ['codigo' => $propiedad->codigo, 'mes' => $siguiente, 'personas' => $personas, 'idioma' => app()->getLocale()]) }}" aria-label="{{ __('motor.calendario.siguiente') }}">→</a>
                @endif
            </div>
        </div>
        <table class="calendario">
            <caption class="oculto">{{ Formato::fecha($mes, 'MMMM YYYY') }}</caption>
            <thead>
                <tr>
                    @foreach (Formato::diasDeLaSemana() as $numero => $nombre)
                        <th scope="col" abbr="{{ $nombre }}">{{ Formato::diasCortos([$numero]) }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($semanas as $semana)
                    <tr>
                        @foreach ($semana as $dia)
                            @if ($dia === null)
                                <td></td>
                            @elseif ($dia['reservable'])
                                <td><a href="{{ route('motor.buscar', ['codigo' => $propiedad->codigo, 'llegada' => $dia['fecha']->format('Y-m-d'), 'salida' => $dia['fecha']->addDay()->format('Y-m-d'), 'personas' => $personas, 'idioma' => app()->getLocale()]) }}" aria-label="{{ Formato::fecha($dia['fecha'], 'LL') }}: {{ __('motor.calendario.libre') }}">{{ $dia['fecha']->day }}</a></td>
                            @else
                                <td class="{{ $dia['libre'] ? 'pasado' : 'lleno' }}"><span aria-label="{{ Formato::fecha($dia['fecha'], 'LL') }}: {{ __($dia['libre'] ? 'motor.calendario.no_disponible' : 'motor.calendario.lleno') }}">{{ $dia['fecha']->day }}</span></td>
                            @endif
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
        <p class="gris" style="margin:10px 0 0">
            <span class="calendario-muestra libre"></span> {{ __('motor.calendario.libre') }}
            <span class="calendario-muestra lleno"></span> {{ __('motor.calendario.lleno') }}
        </p>
    </section>
    <a class="boton secundario" href="{{ route('motor.buscar', ['codigo' => $propiedad->codigo, 'personas' => $personas, 'idioma' => app()->getLocale()]) }}">{{ __('motor.calendario.volver') }}</a>
@endsection
