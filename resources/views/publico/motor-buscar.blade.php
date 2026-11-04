{{-- AlojaFlow · SPDX-License-Identifier: AGPL-3.0-or-later --}}
@use('App\Support\Formato')
@extends('publico.layout')
@section('titulo', __('motor.titulo'))
@section('contenido')
    <form method="get" class="tarjeta" action="{{ route('motor.buscar', ['codigo' => $propiedad->codigo]) }}">
        <h2>{{ __('motor.titulo') }}</h2>
        <input type="hidden" name="idioma" value="{{ app()->getLocale() }}">
        <div class="rejilla">
            <div>
                <label for="llegada">{{ __('ui.comun.llegada') }}</label>
                <input type="date" id="llegada" name="llegada" value="{{ $llegada }}" min="{{ $minimoFecha }}" required>
            </div>
            <div>
                <label for="salida">{{ __('ui.comun.salida') }}</label>
                <input type="date" id="salida" name="salida" value="{{ $salida }}" min="{{ $minimoFecha }}" required>
            </div>
            <div>
                <label for="personas">{{ __('ui.comun.personas') }}</label>
                <input type="number" id="personas" name="personas" value="{{ $personas }}" min="1" max="100" required>
            </div>
            <div><button type="submit">{{ __('motor.buscar') }}</button></div>
        </div>
    </form>

    @if ($error)
        <p class="mensaje error" role="alert">{{ $error }}</p>
    @endif

    @if ($ofertas !== null)
        @forelse ($ofertas as $oferta)
            <section class="tarjeta">
                <div class="oferta">
                    <div>
                        <h2>{{ $oferta->tarifa->nombre }}</h2>
                        @if ($oferta->tarifa->descripcion_publica)
                            <p class="gris" style="margin:0 0 4px">{{ $oferta->tarifa->descripcion_publica }}</p>
                        @endif
                        <span class="gris">{{ __('motor.capacidad', ['personas' => $oferta->capacidad]) }}</span>
                        @if ($oferta->libres <= 2)
                            <span class="gris"> · {{ trans_choice('motor.quedan', $oferta->libres) }}</span>
                        @endif
                    </div>
                    <div style="text-align:right">
                        <div class="precio">{{ Formato::dinero($oferta->presupuesto->total(), $propiedad->moneda) }}</div>
                        <div class="gris">{{ trans_choice('ui.comun.n_noches', $noches) }}</div>
                        @if ($oferta->cumpleEstancia)
                            <a class="boton" style="margin-top:8px" href="{{ route('motor.datos', ['codigo' => $propiedad->codigo, 'tarifa' => $oferta->tarifa->id, 'llegada' => $llegada, 'salida' => $salida, 'personas' => $personas, 'idioma' => app()->getLocale()]) }}">{{ __('motor.reservar') }}</a>
                        @else
                            <div class="gris">{{ __('motor.error.estancia_minima', ['noches' => $oferta->estanciaMinima]) }}</div>
                        @endif
                    </div>
                </div>
            </section>
        @empty
            <p class="mensaje">{{ __('motor.sin_resultados') }}</p>
        @endforelse
    @endif
@endsection
