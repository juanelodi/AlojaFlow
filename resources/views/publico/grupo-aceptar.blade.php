{{-- AlojaFlow · Copyright (C) 2026 Juan Galeana Durán · SPDX-License-Identifier: FSL-1.1-ALv2 --}}
@extends('publico.layout')
@section('titulo', __('grupos.publico.titulo'))
@section('contenido')
    <p class="mensaje" role="status">{{ __('grupos.publico.aceptar_ayuda') }}</p>
    @foreach ($pendientes as $pendiente)
        <section class="tarjeta">
            <h2>{{ $pendiente['documento']->titulo }}</h2>
            <p style="white-space:pre-line;margin:0">{{ $pendiente['texto'] }}</p>
        </section>
    @endforeach
    <section class="tarjeta">
        @if ($errors->any())
            <div class="mensaje error" role="alert">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif
        <form method="post" action="{{ route('grupo.aceptar', ['token' => $token, 'idioma' => app()->getLocale()]) }}">
            @csrf
            <label class="casilla">
                <input type="checkbox" name="acepto" value="1" required>
                <span>{{ __('grupos.publico.acepto') }}</span>
            </label>
            <div class="campo">
                <label for="nombre">{{ __('grupos.publico.nombre_firma') }}</label>
                <input id="nombre" type="text" name="nombre" value="{{ old('nombre') }}" required maxlength="200" autocomplete="name">
            </div>
            <button type="submit">{{ __('grupos.publico.acepto_boton') }}</button>
        </form>
    </section>
@endsection
