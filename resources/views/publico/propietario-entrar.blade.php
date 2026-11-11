{{-- AlojaFlow · Copyright (C) 2026 Juan Galeana Durán · SPDX-License-Identifier: FSL-1.1-ALv2 --}}
@extends('publico.layout')
@section('titulo', __('propietarios.portal.titulo'))
@section('contenido')
    <section class="tarjeta">
        <h2>{{ __('propietarios.portal.entrar', ['nombre' => $propietario->nombre]) }}</h2>
        <p class="gris">{{ __('propietarios.portal.entrar_ayuda') }}</p>
        @error('clave')
            <p class="mensaje error" role="alert">{{ $message }}</p>
        @enderror
        <form method="post" action="{{ route('propietario.entrar', ['token' => $token, 'idioma' => app()->getLocale()]) }}">
            @csrf
            <div class="campo">
                <label for="clave">{{ __('grupos.publico.clave') }}</label>
                <input id="clave" type="password" name="clave" required autocomplete="current-password" autofocus>
            </div>
            <button type="submit">{{ __('grupos.publico.entrar') }}</button>
        </form>
    </section>
@endsection
