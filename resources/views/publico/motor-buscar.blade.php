{{-- AlojaFlow · SPDX-License-Identifier: AGPL-3.0-or-later --}}
@use('App\Support\Formato')
@extends('publico.layout')
@section('titulo', __('motor.titulo'))
@section('cabeza')
    <script type="application/ld+json">{!! $pagina->datosEstructurados() !!}</script>
@endsection
@section('contenido')
    @include('publico.fotos', ['fotos' => $fotos, 'nombre' => $propiedad->nombre])
    @if ($pagina->descripcion() || $pagina->ubicacion() || $pagina->servicios())
        <section class="tarjeta">
            @if ($pagina->descripcion())
                <p style="white-space:pre-line;margin:0 0 8px">{{ $pagina->descripcion() }}</p>
            @endif
            @if ($pagina->servicios())
                <h2>{{ __('portal.servicios') }}</h2>
                <p class="gris" style="white-space:pre-line;margin:0 0 8px">{{ $pagina->servicios() }}</p>
            @endif
            @if ($pagina->ubicacion())
                <h2>{{ __('portal.ubicacion') }}</h2>
                <p class="gris" style="white-space:pre-line;margin:0">{{ $pagina->ubicacion() }}</p>
            @endif
            @include('publico.contacto')
        </section>
    @elseif ($pagina->whatsapp() || $pagina->mapaUrl())
        <section class="tarjeta" style="padding-top:6px">@include('publico.contacto')</section>
    @endif
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
                @include('publico.fotos', ['fotos' => $fotosTarifa->get($oferta->tarifa->id, collect()), 'nombre' => $oferta->tarifa->nombre])
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
    @if ($politica)
        <section class="tarjeta">
            <h2>{{ __('portal.politicas') }}</h2>
            <p class="gris" style="white-space:pre-line;margin:0">{{ $politica }}</p>
        </section>
    @endif
@endsection
