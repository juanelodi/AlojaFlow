{{-- AlojaFlow · SPDX-License-Identifier: AGPL-3.0-or-later --}}
@use('App\Support\Formato')
@use('App\Enums\BaseCargo')
@extends('publico.layout')
@section('titulo', __('motor.tus_datos'))
@section('contenido')
    @if ($errores->any())
        <div class="mensaje error" role="alert">
            @foreach ($errores->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <section class="tarjeta">
        <h2>{{ $tarifa->nombre }}</h2>
        <p class="gris" style="margin:0">
            {{ Formato::fecha($llegada, 'ddd D MMM') }} → {{ Formato::fecha($salida, 'ddd D MMM') }}
            · {{ trans_choice('ui.comun.n_noches', $noches) }}
            · {{ __('motor.n_personas', ['personas' => $personas]) }}
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
        </table>
        @if ($ajustes->anticipoPorcentaje() > 0)
            <p class="gris" style="margin:8px 0 0">{{ __('motor.anticipo', ['porcentaje' => $ajustes->anticipoPorcentaje()]) }}</p>
        @endif
    </section>

    <form method="post" action="{{ route('motor.reservar', ['codigo' => $propiedad->codigo]) }}" class="tarjeta">
        <h2>{{ __('motor.tus_datos') }}</h2>
        <input type="hidden" name="tarifa_id" value="{{ $tarifa->id }}">
        <input type="hidden" name="llegada" value="{{ $llegada->format('Y-m-d') }}">
        <input type="hidden" name="salida" value="{{ $salida->format('Y-m-d') }}">
        <input type="hidden" name="personas" value="{{ $personas }}">
        <div class="oculto" aria-hidden="true"><label>{{ __('motor.no_llenar') }}<input type="text" name="sitio_web" tabindex="-1" autocomplete="off"></label></div>

        <div class="rejilla">
            <div class="campo"><label for="nombre">{{ __('motor.nombre') }}</label><input type="text" id="nombre" name="nombre" value="{{ $valores['nombre'] ?? '' }}" autocomplete="given-name" required maxlength="100"></div>
            <div class="campo"><label for="apellido">{{ __('motor.apellido') }}</label><input type="text" id="apellido" name="apellido" value="{{ $valores['apellido'] ?? '' }}" autocomplete="family-name" required maxlength="100"></div>
            <div class="campo"><label for="correo">{{ __('motor.correo') }}</label><input type="email" id="correo" name="correo" value="{{ $valores['correo'] ?? '' }}" autocomplete="email" required maxlength="150"></div>
            <div class="campo"><label for="telefono">{{ __('motor.telefono') }}</label><input type="tel" id="telefono" name="telefono" value="{{ $valores['telefono'] ?? '' }}" autocomplete="tel" required maxlength="50"></div>
        </div>

        @if ($cargos->isNotEmpty())
            <h2 style="margin-top:8px">{{ __('motor.extras') }}</h2>
            @foreach ($cargos as $cargo)
                <label class="casilla">
                    <input type="checkbox" name="cargos[]" value="{{ $cargo->id }}" @checked(in_array($cargo->id, array_map('intval', (array) ($valores['cargos'] ?? []))))>
                    <span>{{ $cargo->nombre }}
                        <span class="gris">
                            @if ($cargo->base === BaseCargo::PorcentajeAlojamiento)
                                {{ (float) $cargo->porcentaje }} %
                            @else
                                {{ Formato::dinero($cargo->importe, $propiedad->moneda) }} · {{ $cargo->base->etiqueta() }}
                            @endif
                        </span>
                    </span>
                </label>
            @endforeach
        @endif

        <div class="campo" style="margin-top:12px"><label for="cupon">{{ __('motor.cupon') }}</label><input type="text" id="cupon" name="cupon" value="{{ $valores['cupon'] ?? '' }}" maxlength="40" autocomplete="off"></div>
        <div class="campo"><label for="comentario">{{ __('motor.comentario') }}</label><textarea id="comentario" name="comentario" rows="3" maxlength="2000">{{ $valores['comentario'] ?? '' }}</textarea></div>

        @if ($ajustes->condiciones())
            <p class="gris" style="white-space:pre-line">{{ $ajustes->condiciones() }}</p>
        @endif
        <label class="casilla"><input type="checkbox" name="acepto" value="1" required> <span>{{ __('motor.acepto') }}</span></label>

        <button type="submit" style="margin-top:8px">{{ __('motor.confirmar') }}</button>
        <a class="boton secundario" href="{{ route('motor.buscar', ['codigo' => $propiedad->codigo, 'llegada' => $llegada->format('Y-m-d'), 'salida' => $salida->format('Y-m-d'), 'personas' => $personas]) }}">{{ __('motor.volver') }}</a>
    </form>
@endsection
