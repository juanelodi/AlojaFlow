{{-- AlojaFlow · SPDX-License-Identifier: AGPL-3.0-or-later --}}
@extends('publico.layout')
@section('titulo', __('operacion.opiniones.pagina_titulo'))
@section('contenido')
    @if ($gracias)
        <p class="mensaje bien" role="status">{{ __('operacion.opiniones.gracias') }}</p>
    @endif
    @foreach ($errores as $error)
        <p class="mensaje error" role="alert">{{ $error }}</p>
    @endforeach
    <section class="tarjeta">
        <h2>{{ __('operacion.opiniones.pregunta', ['propiedad' => $propiedad->nombre]) }}</h2>
        <form method="post" action="{{ route('opinion.guardar', ['token' => $token, 'idioma' => app()->getLocale()]) }}">
            @csrf
            <fieldset style="border:0;padding:0;margin:0 0 12px">
                <legend class="gris" style="padding:0;margin-bottom:6px">{{ __('operacion.opiniones.calificacion') }}</legend>
                @for ($i = 5; $i >= 1; $i--)
                    <label style="display:flex;align-items:center;gap:8px;min-height:44px;font-weight:400;margin:0">
                        <input type="radio" name="calificacion" value="{{ $i }}" required @checked((int) old('calificacion', $opinion?->calificacion) === $i)>
                        <span>{{ trans_choice('operacion.opiniones.estrellas', $i, ['n' => $i]) }}</span>
                    </label>
                @endfor
            </fieldset>
            <div class="campo">
                <label for="comentario">{{ __('operacion.opiniones.comentario') }}</label>
                <textarea id="comentario" name="comentario" rows="4" maxlength="2000">{{ old('comentario', $opinion?->comentario) }}</textarea>
            </div>
            <button type="submit">{{ __('operacion.opiniones.enviar') }}</button>
        </form>
    </section>
    {{-- A todos, contesten lo que contesten. --}}
    @if ($gracias && $enlacePublico !== null)
        <section class="tarjeta">
            <p style="margin:0 0 10px">{{ __('operacion.opiniones.publica_ayuda') }}</p>
            <a class="boton secundario" href="{{ $enlacePublico }}" rel="noopener" target="_blank">{{ __('operacion.opiniones.publica') }}</a>
        </section>
    @endif
@endsection
