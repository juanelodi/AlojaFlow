{{-- AlojaFlow · SPDX-License-Identifier: AGPL-3.0-or-later --}}
@extends('publico.layout')
@section('titulo', __('grupos.publico.titulo'))
@section('contenido')
    <section class="tarjeta">
        <h2>{{ __('grupos.publico.entrar_titulo', ['grupo' => $grupo->nombre]) }}</h2>
        <p class="gris">{{ __('grupos.publico.entrar_ayuda') }}</p>
        @error('clave')
            <p class="mensaje error" role="alert">{{ $message }}</p>
        @enderror
        <form method="post" action="{{ route('grupo.entrar', ['token' => $token, 'idioma' => app()->getLocale()]) }}">
            @csrf
            <div class="campo">
                <label for="clave">{{ __('grupos.publico.clave') }}</label>
                <input id="clave" type="password" name="clave" required autocomplete="current-password" autofocus>
            </div>
            <button type="submit">{{ __('grupos.publico.entrar') }}</button>
        </form>
    </section>
@endsection
