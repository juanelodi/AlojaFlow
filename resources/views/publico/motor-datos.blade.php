{{-- AlojaFlow · Copyright (C) 2026 Juan Galeana Durán · SPDX-License-Identifier: FSL-1.1-ALv2 --}}
@use('App\Support\Formato')
@use('App\Enums\BaseCargo')
@use('App\Enums\TipoEquipo')
@use('App\Reservas\ReglasCargo')
@extends('publico.layout')
@section('titulo', __('motor.tus_datos'))
@section('contenido')
    @php
        // Lo que lleva un campo con error: el borde, y que un lector de pantalla lo una con su mensaje.
        $marca = fn (string $campo): string => $errores->has($campo) ? 'class="con-error" aria-invalid="true" aria-describedby="error-'.$campo.'"' : '';
        $conAncla = ['nombre' => __('motor.nombre'), 'apellido' => __('motor.apellido'), 'correo' => __('motor.correo'), 'telefono' => __('motor.telefono'), 'tipo_equipo' => __('camping.equipo.tipo'), 'largo' => __('camping.equipo.largo', ['unidad' => $unidadLargo]), 'vehiculos' => __('camping.equipo.vehiculos'), 'placas' => __('camping.equipo.placas'), 'cupon' => __('motor.cupon'), 'comentario' => __('motor.comentario'), 'acepto' => __('portal.politicas')];
    @endphp
    @include('publico.motor-pasos', ['paso' => 'datos'])

    <section class="tarjeta">
        <h2>{{ $tarifa->nombre }}</h2>
        @if ($lugar)
            <p style="margin:0 0 4px"><strong>{{ __('camping.motor.tu_lugar', ['lugar' => $lugar->nombre]) }}</strong></p>
        @endif
        @if ($oferta->etiquetas !== [])
            <p class="gris" style="margin:0 0 4px">{{ implode(' · ', $oferta->etiquetas) }}</p>
        @endif
        <p class="gris" style="margin:0">
            {{ Formato::fecha($llegada, 'ddd D MMM') }} → {{ Formato::fecha($salida, 'ddd D MMM') }}
            · {{ trans_choice('ui.comun.n_noches', $noches) }}
            · {{ trans_choice('motor.n_personas', $personas) }}
            @if ($unidades > 1) · {{ trans_choice('motor.n_unidades', $unidades) }}@endif
        </p>
        <table style="margin-top:10px">
            <tr><td>{{ __('documentos.alojamiento') }}</td><td class="num">{{ Formato::dinero($oferta->presupuesto->alojamiento, $propiedad->moneda) }}</td></tr>
            @foreach ($oferta->presupuesto->cargos as $cargo)
                <tr><td>{{ $cargo['nombre'] }}</td><td class="num">{{ Formato::dinero($cargo['importe'], $propiedad->moneda) }}</td></tr>
            @endforeach
            @foreach ($oferta->presupuesto->impuestos as $impuesto)
                @unless ($impuesto['incluido'])
                    <tr><td>{{ $impuesto['nombre'] }}</td><td class="num">{{ Formato::dinero($impuesto['importe'], $propiedad->moneda) }}</td></tr>
                @endunless
            @endforeach
            <tr class="total"><td>{{ __('ui.comun.total') }}</td><td class="num">{{ Formato::dinero($oferta->presupuesto->total(), $propiedad->moneda) }}</td></tr>
            @if ($segunda = (new \App\Dinero\SegundaMoneda($propiedad))->texto($oferta->presupuesto->total()))
                <tr><td></td><td class="num gris">{{ $segunda }}</td></tr>
            @endif
        </table>
        @if ($oferta->anticipo > 0)
            <p class="gris" style="margin:8px 0 0">{{ __('motor.anticipo', ['porcentaje' => Formato::tasa($oferta->anticipo)]) }}</p>
        @endif
        @if ($oferta->politica)
            <p class="gris" style="margin:8px 0 0">{{ $oferta->politica }}</p>
        @endif
    </section>

    {{-- Con un error, la página vuelve abierta aquí y no arriba, y sin JavaScript. --}}
    <form method="post" id="datos" action="{{ route('motor.reservar', ['codigo' => $propiedad->codigo, 'idioma' => app()->getLocale()]) }}#datos" class="tarjeta">
        <h2>{{ __('motor.tus_datos') }}</h2>
        @if ($errores->any())
            <div class="mensaje error" role="alert">
                <strong>{{ __('motor.revisa') }}</strong>
                <ul>
                    @foreach ($errores->keys() as $campo)
                        <li>
                            @if (isset($conAncla[$campo]))
                                <a href="#{{ $campo }}">{{ $conAncla[$campo] }}</a>
                            @else
                                {{ $errores->first($campo) }}
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
        <input type="hidden" name="tarifa_id" value="{{ $tarifa->id }}">
        <input type="hidden" name="llegada" value="{{ $llegada->format('Y-m-d') }}">
        <input type="hidden" name="salida" value="{{ $salida->format('Y-m-d') }}">
        <input type="hidden" name="personas" value="{{ $personas }}">
        <input type="hidden" name="unidades" value="{{ $unidades }}">
        @if ($lugar)
            <input type="hidden" name="unidad_id" value="{{ $lugar->id }}">
        @endif
        @foreach ($filtro->parametros() as $parametro => $valorFiltro)
            @continue($parametro === 'largo' && $ajustes->pideEquipo())
            <input type="hidden" name="{{ $parametro }}" value="{{ $valorFiltro }}">
        @endforeach
        <div class="oculto" aria-hidden="true"><label>{{ __('motor.no_llenar') }}<input type="text" name="sitio_web" tabindex="-1" autocomplete="off"></label></div>

        <div class="rejilla arriba">
            <div class="campo">
                <label for="nombre">{{ __('motor.nombre') }}</label>
                <input type="text" id="nombre" name="nombre" value="{{ $valores['nombre'] ?? '' }}" autocomplete="given-name" autocapitalize="words" required maxlength="100" {!! $marca('nombre') !!}>
                @include('publico.motor-error-campo', ['campo' => 'nombre'])
            </div>
            <div class="campo">
                <label for="apellido">{{ __('motor.apellido') }}</label>
                <input type="text" id="apellido" name="apellido" value="{{ $valores['apellido'] ?? '' }}" autocomplete="family-name" autocapitalize="words" required maxlength="100" {!! $marca('apellido') !!}>
                @include('publico.motor-error-campo', ['campo' => 'apellido'])
            </div>
        </div>
        <div class="rejilla arriba amplia">
            <div class="campo">
                <label for="correo">{{ __('motor.correo') }}</label>
                <input type="email" id="correo" name="correo" value="{{ $valores['correo'] ?? '' }}" autocomplete="email" autocapitalize="none" spellcheck="false" required maxlength="150" {!! $marca('correo') !!}>
                @include('publico.motor-error-campo', ['campo' => 'correo'])
            </div>
            <div class="campo">
                <label for="telefono">{{ __('motor.telefono') }}</label>
                <input type="tel" id="telefono" name="telefono" value="{{ $valores['telefono'] ?? '' }}" autocomplete="tel" required maxlength="50" {!! $marca('telefono') !!}>
                @include('publico.motor-error-campo', ['campo' => 'telefono'])
            </div>
        </div>

        @if ($ajustes->pideEquipo())
            <h2 style="margin-top:8px">{{ __('camping.equipo.titulo_publico') }}</h2>
            <div class="rejilla arriba">
                <div class="campo">
                    <label for="tipo_equipo">{{ __('camping.equipo.tipo') }}</label>
                    <select id="tipo_equipo" name="tipo_equipo" autocomplete="off">
                        <option value=""></option>
                        @foreach (TipoEquipo::opciones() as $valor => $etiqueta)
                            <option value="{{ $valor }}" @selected(($valores['tipo_equipo'] ?? '') === $valor)>{{ $etiqueta }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="campo"><label for="largo">{{ __('camping.equipo.largo', ['unidad' => $unidadLargo]) }}</label><input type="number" id="largo" name="largo" min="0" max="1000" step="any" inputmode="decimal" autocomplete="off" value="{{ $valores['largo'] ?? '' }}" {!! $marca('largo') !!}>@include('publico.motor-error-campo', ['campo' => 'largo'])</div>
                <div class="campo"><label for="vehiculos">{{ __('camping.equipo.vehiculos') }}</label><input type="number" id="vehiculos" name="vehiculos" min="0" max="20" inputmode="numeric" autocomplete="off" value="{{ $valores['vehiculos'] ?? '' }}" {!! $marca('vehiculos') !!}>@include('publico.motor-error-campo', ['campo' => 'vehiculos'])</div>
                <div class="campo"><label for="placas">{{ __('camping.equipo.placas') }}</label><input type="text" id="placas" name="placas" maxlength="100" value="{{ $valores['placas'] ?? '' }}" autocomplete="off" autocapitalize="characters" spellcheck="false" {!! $marca('placas') !!}>@include('publico.motor-error-campo', ['campo' => 'placas'])</div>
            </div>
        @endif

        @if ($cargos->isNotEmpty())
            <h2 style="margin-top:8px">{{ __('motor.extras') }}</h2>
            <p class="gris" style="margin:0 0 8px">{{ __('motor.extras_ayuda') }}</p>
            @foreach ($cargos as $cargo)
                @if (array_key_exists($cargo->id, $cupos))
                    <div class="campo">
                        <label for="cantidad-{{ $cargo->id }}">{{ $cargo->nombre }} <span class="gris" style="font-weight:400">{{ Formato::dinero($cargo->importe, $propiedad->moneda) }} · {{ ReglasCargo::etiqueta($cargo) }}</span></label>
                        @if ($cupos[$cargo->id] > 0)
                            <select id="cantidad-{{ $cargo->id }}" name="cantidades[{{ $cargo->id }}]">
                                @for ($n = 0; $n <= min($cupos[$cargo->id], 10); $n++)
                                    <option value="{{ $n }}" @selected((int) ($valores['cantidades'][$cargo->id] ?? 0) === $n)>{{ $n }}</option>
                                @endfor
                            </select>
                        @else
                            <span class="gris">{{ __('camping.extras.sin_cupo') }}</span>
                        @endif
                    </div>
                    @continue
                @endif
                <label class="casilla">
                    <input type="checkbox" name="cargos[]" value="{{ $cargo->id }}" @checked(in_array($cargo->id, array_map('intval', (array) ($valores['cargos'] ?? []))))>
                    <span>{{ $cargo->nombre }}
                        <span class="gris">
                            @if ($cargo->base === BaseCargo::PorcentajeAlojamiento)
                                {{ Formato::tasa($cargo->porcentaje) }}
                            @else
                                {{ Formato::dinero($cargo->importe, $propiedad->moneda) }} · {{ ReglasCargo::etiqueta($cargo) }}
                            @endif
                        </span>
                    </span>
                </label>
            @endforeach
        @endif

        @if (\App\Modulos\Modulos::activo(\App\Modulos\Modulo::Cupones) && $unidades === 1)
            {{-- Casi nadie trae uno: plegado, salvo si ya escribió algo. --}}
            <details class="filtros" @if (filled($valores['cupon'] ?? null) || $errores->has('cupon')) open @endif>
                <summary>{{ __('motor.cupon_pregunta') }}</summary>
                <div class="campo"><label for="cupon">{{ __('motor.cupon') }}</label><input type="text" id="cupon" name="cupon" value="{{ $valores['cupon'] ?? '' }}" maxlength="40" autocomplete="off" autocapitalize="characters" spellcheck="false" {!! $marca('cupon') !!}>@include('publico.motor-error-campo', ['campo' => 'cupon'])</div>
            </details>
        @endif
        <div class="campo"><label for="comentario">{{ __('motor.comentario') }} <span class="opcional">({{ __('motor.opcional') }})</span></label><textarea id="comentario" name="comentario" rows="2" maxlength="2000" autocomplete="off" {!! $marca('comentario') !!}>{{ $valores['comentario'] ?? '' }}</textarea>@include('publico.motor-error-campo', ['campo' => 'comentario'])</div>

        @if ($ajustes->condiciones())
            <p class="gris" style="white-space:pre-line">{{ $ajustes->condiciones() }}</p>
        @endif
        <label class="casilla"><input type="checkbox" id="acepto" name="acepto" value="1" required @checked(filled($valores['acepto'] ?? null)) {!! $errores->has('acepto') ? 'aria-invalid="true" aria-describedby="error-acepto"' : '' !!}> <span>{{ __('motor.acepto') }}</span></label>
        @include('publico.motor-error-campo', ['campo' => 'acepto'])

        <button type="submit" style="margin-top:8px">{{ __('motor.confirmar') }}</button>
        <p class="gris" style="margin:8px 0">{{ __($conPago ? 'motor.sin_cobro_pago' : 'motor.sin_cobro') }}</p>
        <a class="boton secundario" href="{{ route('motor.buscar', ['codigo' => $propiedad->codigo, 'llegada' => $llegada->format('Y-m-d'), 'salida' => $salida->format('Y-m-d'), 'personas' => $personas, 'idioma' => app()->getLocale()] + $filtro->parametros()) }}">{{ __('motor.volver') }}</a>
    </form>
@endsection
