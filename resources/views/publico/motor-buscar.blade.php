{{-- AlojaFlow · Copyright (C) 2026 Juan Galeana Durán · SPDX-License-Identifier: FSL-1.1-ALv2 --}}
@use('App\Support\Formato')
@use('App\Enums\ModoTarifa')
@use('App\Enums\TipoLugar')
@extends('publico.layout')
@section('titulo', __('motor.titulo'))
@section('cabeza')
    <script type="application/ld+json">{!! $pagina->datosEstructurados() !!}</script>
@endsection
@section('contenido')
    {{-- Quien ya buscó quiere ver los resultados: las fotos pasan abajo. La descripción siempre va después del formulario, para no alejarlo en el teléfono. --}}
    @if ($ofertas === null && ! $error)
        @include('publico.fotos', ['fotos' => $fotos, 'nombre' => $propiedad->nombre])
    @endif
    @include('publico.motor-pasos', ['paso' => 'elegir'])
    <form method="get" class="tarjeta" action="{{ route('motor.buscar', ['codigo' => $propiedad->codigo]) }}">
        <h2>{{ __('motor.titulo') }}</h2>
        <input type="hidden" name="idioma" value="{{ app()->getLocale() }}">
        <div class="rejilla">
            <div>
                <label for="llegada">{{ __('ui.comun.llegada') }}</label>
                <input type="date" id="llegada" name="llegada" value="{{ $llegada }}" min="{{ $minimoFecha }}" required autocomplete="off" data-af-rango-inicio="estancia" data-af-rango-dias="1" data-af-rango-llenar="1">
            </div>
            <div>
                <label for="salida">{{ __('ui.comun.salida') }}</label>
                <input type="date" id="salida" name="salida" value="{{ $salida }}" min="{{ $minimoFecha }}" required autocomplete="off" data-af-rango-fin="estancia">
            </div>
            <div>
                <label for="personas">{{ __('ui.comun.personas') }}</label>
                <input type="number" id="personas" name="personas" value="{{ $personas }}" min="1" max="100" inputmode="numeric" autocomplete="off" required>
            </div>
            @if ($unidadesMaximas > 1)
                {{-- Varias unidades en un solo pedido, con un solo pago (D-158). --}}
                <div>
                    <label for="unidades">{{ __('motor.unidades') }}</label>
                    <select id="unidades" name="unidades">
                        @for ($i = 1; $i <= $unidadesMaximas; $i++)
                            <option value="{{ $i }}" @selected($i === $unidades)>{{ $i }}</option>
                        @endfor
                    </select>
                </div>
            @endif
            <div><button type="submit">{{ __('motor.buscar') }}</button></div>
        </div>
        @if ($conFiltros)
            <details class="filtros" @if ($filtro->parametros() !== []) open @endif>
                <summary>{{ __('camping.motor.filtros') }}</summary>
                <div class="rejilla" style="margin-top:8px">
                    <div>
                        <label for="tipo_lugar">{{ __('camping.atributos.tipo') }}</label>
                        <select id="tipo_lugar" name="tipo_lugar">
                            <option value="">{{ __('camping.motor.cualquiera') }}</option>
                            @foreach (TipoLugar::opciones() as $valor => $etiqueta)
                                <option value="{{ $valor }}" @selected($filtro->tipo?->value === $valor)>{{ $etiqueta }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="largo">{{ __('camping.equipo.largo', ['unidad' => $unidadLargo]) }}</label>
                        <input type="number" id="largo" name="largo" min="0" max="1000" step="any" inputmode="decimal" autocomplete="off" value="{{ $filtro->largo }}">
                    </div>
                </div>
                @foreach (['luz', 'agua', 'drenaje', 'mascotas', 'accesible'] as $casilla)
                    <label class="casilla"><input type="checkbox" name="con_{{ $casilla }}" value="1" @checked(in_array($casilla, $filtro->casillas, true))> <span>{{ __('camping.atributos.'.$casilla) }}</span></label>
                @endforeach
            </details>
        @endif
        @if ($conCalendario)
            <p style="margin:12px 0 0"><a href="{{ route('motor.calendario', ['codigo' => $propiedad->codigo, 'personas' => $personas, 'idioma' => app()->getLocale()]) }}">{{ __('motor.calendario.ver') }}</a></p>
        @endif
    </form>

    @if ($error)
        <p class="mensaje error" role="alert">{{ $error }}</p>
    @endif

    @if ($ofertas !== null)
        @include('publico.plano-lugares')
        @forelse ($ofertas as $oferta)
            <section class="tarjeta">
                {{-- La que sale de otra es la misma habitación: sin fotos propias, las de aquella. --}}
                @include('publico.fotos', ['fotos' => $fotosTarifa->get($oferta->tarifa->id) ?? $fotosTarifa->get($oferta->tarifa->tarifa_base_id, collect()), 'nombre' => $oferta->tarifa->nombre])
                <div class="oferta">
                    <div>
                        <h2>{{ $oferta->tarifa->nombre }}</h2>
                        @if ($oferta->tarifa->descripcion_publica)
                            <p class="gris" style="margin:0 0 4px">{{ $oferta->tarifa->descripcion_publica }}</p>
                        @endif
                        @if ($oferta->politica)
                            <details class="filtros" style="margin:0 0 4px">
                                <summary class="gris">{{ $oferta->politicaNombre }}</summary>
                                <p class="gris" style="margin:0">{{ $oferta->politica }}</p>
                            </details>
                        @endif
                        <span class="gris">{{ trans_choice('motor.capacidad', $oferta->capacidad) }}</span>
                        @if ($oferta->libres <= 2)
                            <span class="gris"> · {{ trans_choice('motor.quedan', $oferta->libres) }}</span>
                        @endif
                        @if ($oferta->etiquetas !== [])
                            <p class="gris" style="margin:4px 0 0">{{ implode(' · ', $oferta->etiquetas) }}</p>
                        @endif
                        @if ($conEstanciasLargas && $oferta->tarifa->tarifa_base_id === null && ($oferta->tarifa->precio_semana !== null || $oferta->tarifa->precio_mes !== null))
                            <p class="gris" style="margin:4px 0 0">
                                @if ($oferta->tarifa->precio_semana !== null){{ __('estancias.motor.semana', ['precio' => Formato::dinero($oferta->tarifa->precio_semana, $propiedad->moneda)]) }}@endif
                                @if ($oferta->tarifa->precio_semana !== null && $oferta->tarifa->precio_mes !== null) · @endif
                                @if ($oferta->tarifa->precio_mes !== null){{ __('estancias.motor.mes', ['precio' => Formato::dinero($oferta->tarifa->precio_mes, $propiedad->moneda)]) }}@endif
                                @if ($oferta->tarifa->modo === ModoTarifa::PorPersona) {{ __('estancias.motor.por_persona') }}@endif
                            </p>
                        @endif
                    </div>
                    <div class="oferta-compra">
                        <div>
                        <div class="precio">{{ Formato::dinero($oferta->presupuesto->total(), $propiedad->moneda) }}</div>
                        @if ($segunda = (new \App\Dinero\SegundaMoneda($propiedad))->texto($oferta->presupuesto->total()))<div class="gris">{{ $segunda }}</div>@endif
                        <div class="gris">{{ trans_choice('ui.comun.n_noches', $noches) }}@if ($oferta->cantidad > 1) · {{ trans_choice('motor.n_unidades', $oferta->cantidad) }}@endif</div>
                        @if ($oferta->presupuesto->cotizacion?->estanciaLarga)
                            <div class="gris">{{ __('estancias.motor.aplicado.'.$oferta->presupuesto->cotizacion->estanciaLarga) }}</div>
                        @endif
                        </div>
                        @if ($oferta->cumpleEstancia)
                            <a class="boton" href="{{ route('motor.datos', ['codigo' => $propiedad->codigo, 'tarifa' => $oferta->tarifa->id, 'llegada' => $llegada, 'salida' => $salida, 'personas' => $personas, 'unidades' => $oferta->cantidad, 'idioma' => app()->getLocale()] + $filtro->parametros()) }}">{{ __('motor.reservar') }}</a>
                        @else
                            <div class="gris">{{ __('motor.error.estancia_minima', ['noches' => $oferta->estanciaMinima]) }}</div>
                        @endif
                    </div>
                </div>
            </section>
        @empty
            <p class="mensaje" role="status">
                {{ __('motor.sin_resultados') }}
                @if ($conCalendario)
                    <a href="{{ route('motor.calendario', ['codigo' => $propiedad->codigo, 'personas' => $personas, 'idioma' => app()->getLocale()]) }}">{{ __('motor.calendario.ver') }}</a>
                @endif
            </p>
        @endforelse
    @endif
    @if ($ofertas !== null || $error)
        @include('publico.fotos', ['fotos' => $fotos, 'nombre' => $propiedad->nombre])
    @endif
    @include('publico.motor-presentacion')
    @if ($politica)
        <section class="tarjeta">
            <h2>{{ __('portal.politicas') }}</h2>
            <p class="gris" style="white-space:pre-line;margin:0">{{ $politica }}</p>
        </section>
    @endif
@endsection
