{{-- AlojaFlow · Copyright (C) 2026 Juan Galeana Durán · SPDX-License-Identifier: FSL-1.1-ALv2 --}}
@use('App\Support\Formato')
@extends('publico.layout')
@section('titulo', __('propietarios.portal.titulo'))
@section('contenido')
    @if (in_array($aviso, ['bloqueado', 'desbloqueado'], true))
        <p class="mensaje bien" role="status">{{ __('propietarios.portal.'.$aviso) }}</p>
    @endif
    {{-- Arriba: al volver del formulario, la página abre aquí y en un teléfono el formulario queda muy abajo. --}}
    @if ($errors->any())
        <p class="mensaje error" role="alert">{{ $errors->first() }}</p>
    @endif

    <section class="tarjeta">
        <h2>{{ __('propietarios.portal.hola', ['nombre' => $propietario->nombre]) }}</h2>
        <table>
            <tr><th scope="row">{{ __('propietarios.portal.este_mes') }}</th><td class="num">{{ Formato::dinero($mes['ingresos'], $propiedad->moneda) }}</td></tr>
            <tr><th scope="row">{{ __('propietarios.portal.ocupacion') }}</th><td class="num">{{ $ocupacion }} %</td></tr>
            <tr><th scope="row">{{ __('propietarios.portal.te_toca') }}</th><td class="num"><strong>{{ Formato::dinero($mes['neto'], $propiedad->moneda) }}</strong></td></tr>
        </table>
        <p class="gris" style="margin:8px 0 0">{{ __('propietarios.portal.estimado') }}</p>
    </section>

    @if (! empty($mes['detalle']['gastos']))
        <section class="tarjeta">
            <h2>{{ __('propietarios.portal.gastos') }}</h2>
            <table>
                @foreach ($mes['detalle']['gastos'] as $gasto)
                    <tr>
                        <td>
                            {{ $gasto['concepto'] }}@if ($gasto['unidad']) · {{ $gasto['unidad'] }}@endif
                            @if ($gasto['comprobante'])
                                <br><a href="{{ route('propietario.comprobante', ['token' => $token, 'movimiento' => $gasto['id'], 'idioma' => app()->getLocale()]) }}">{{ __('propietarios.gasto.ver_comprobante') }}</a>
                            @endif
                        </td>
                        <td class="num">{{ Formato::dinero($gasto['importe'], $propiedad->moneda) }}</td>
                    </tr>
                @endforeach
            </table>
        </section>
    @endif

    <section class="tarjeta">
        <h2>{{ __('propietarios.portal.calendario') }}</h2>
        @forelse ($franjas as $franja)
            <p style="margin:8px 0 4px"><strong>{{ $franja['unidad']->nombre }}</strong></p>
            <div class="franja" aria-hidden="true">
                @foreach ($franja['dias'] as $dia)
                    <span class="dia {{ $dia['estado'] }}" title="{{ Formato::fecha($dia['fecha']) }}: {{ __('propietarios.portal.tipos.'.$dia['estado']) }}"></span>
                @endforeach
            </div>
        @empty
            <p class="gris">{{ __('propietarios.portal.sin_unidades') }}</p>
        @endforelse
        @if ($franjas !== [])
            <p class="gris" style="margin:10px 0 0">
                <span class="dia libre muestra"></span> {{ __('propietarios.portal.tipos.libre') }}
                <span class="dia reserva muestra"></span> {{ __('propietarios.portal.tipos.reserva') }}
                <span class="dia dueno muestra"></span> {{ __('propietarios.portal.tipos.dueno') }}
                <span class="dia bloqueo muestra"></span> {{ __('propietarios.portal.tipos.bloqueo') }}
            </p>
        @endif
    </section>

    <section class="tarjeta" id="estancias">
        <h2>{{ __('propietarios.portal.proximas') }}</h2>
        @if ($estancias === [])
            <p class="gris" style="margin:0">{{ __('propietarios.portal.sin_estancias') }}</p>
        @else
            <table>
                @foreach ($estancias as $fila)
                    <tr>
                        <td>
                            {{ $fila['unidad'] }}
                            <br><span class="gris">{{ Formato::fecha($fila['reserva']->llegada) }} → {{ Formato::fecha($fila['reserva']->salida) }}</span>
                        </td>
                        <td class="num">
                            {{ __('propietarios.portal.tipos.'.$fila['tipo']) }}
                            @if ($fila['tipo'] === 'dueno' && $propietario->puede_bloquear && $fila['reserva']->llegada->format('Y-m-d') > $hoy->format('Y-m-d'))
                                <form method="post" action="{{ route('propietario.quitar', ['token' => $token, 'reserva' => $fila['reserva']->id, 'idioma' => app()->getLocale()]) }}" style="margin-top:4px">
                                    @csrf
                                    <button type="submit" class="boton secundario" style="min-height:36px;padding:4px 10px">{{ __('propietarios.portal.quitar') }}</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </table>
        @endif
    </section>

    @if ($propietario->puede_bloquear && $unidades->isNotEmpty())
        <section class="tarjeta">
            <h2>{{ __('propietarios.portal.bloquear') }}</h2>
            <p class="gris">{{ __('propietarios.portal.bloquear_ayuda') }}</p>
            <form method="post" action="{{ route('propietario.bloquear', ['token' => $token, 'idioma' => app()->getLocale()]) }}">
                @csrf
                <div class="rejilla">
                    <div class="campo">
                        <label for="unidad_id">{{ __('ui.comun.unidad') }}</label>
                        <select id="unidad_id" name="unidad_id" required>
                            @foreach ($unidades as $unidad)
                                <option value="{{ $unidad->id }}" @selected((int) old('unidad_id') === $unidad->id)>{{ $unidad->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="campo"><label for="llegada">{{ __('ui.comun.llegada') }}</label><input id="llegada" type="date" data-af-rango-inicio="estancia" data-af-rango-dias="1" data-af-rango-llenar="1" name="llegada" min="{{ $hoy->format('Y-m-d') }}" value="{{ old('llegada') }}" required></div>
                    <div class="campo"><label for="salida">{{ __('ui.comun.salida') }}</label><input id="salida" type="date" data-af-rango-fin="estancia" name="salida" min="{{ $hoy->format('Y-m-d') }}" value="{{ old('salida') }}" required></div>
                </div>
                <button type="submit">{{ __('propietarios.portal.bloquear_boton') }}</button>
            </form>
        </section>
    @endif

    <section class="tarjeta">
        <h2>{{ __('propietarios.portal.estados') }}</h2>
        @if ($estados->isEmpty())
            <p class="gris" style="margin:0">{{ __('propietarios.portal.sin_estados') }}</p>
        @else
            <table>
                @foreach ($estados as $estado)
                    <tr>
                        <td>
                            {{ $estado->periodo() }}
                            <br><span class="gris">{{ $estado->fase->etiqueta() }}@if ($estado->pagado_en) · {{ Formato::fecha($estado->pagado_en) }}@endif</span>
                        </td>
                        <td class="num">
                            {{ Formato::dinero($estado->neto, $estado->moneda) }}
                            <br><a href="{{ route('propietario.estado', ['token' => $token, 'estado' => $estado->id, 'idioma' => app()->getLocale()]) }}">{{ __('propietarios.estado.descargar_pdf') }}</a>
                        </td>
                    </tr>
                @endforeach
            </table>
        @endif
    </section>

    <form method="post" action="{{ route('propietario.salir', ['token' => $token, 'idioma' => app()->getLocale()]) }}">
        @csrf
        <button type="submit" class="boton secundario">{{ __('propietarios.portal.salir') }}</button>
    </form>
@endsection
