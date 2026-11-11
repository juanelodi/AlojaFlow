{{-- AlojaFlow · Copyright (C) 2026 Juan Galeana Durán · SPDX-License-Identifier: FSL-1.1-ALv2 --}}
@extends('publico.layout')
@section('titulo', __('grupos.publico.titulo'))
@section('contenido')
    <section class="tarjeta">
        <h2>{{ __('grupos.publico.clave_titulo') }}</h2>
        <p class="gris">{{ __('grupos.publico.clave_ayuda') }}</p>
        @error('clave')
            <p class="mensaje error" role="alert">{{ $message }}</p>
        @enderror
        <form method="post" action="{{ route('grupo.clave', ['token' => $token, 'idioma' => app()->getLocale()]) }}">
            @csrf
            <div class="campo">
                <label for="clave">{{ __('grupos.publico.clave_nueva') }}</label>
                <input id="clave" type="password" name="clave" required minlength="8" autocomplete="new-password" autofocus>
            </div>
            <div class="campo">
                <label for="clave_confirmation">{{ __('grupos.publico.clave_repite') }}</label>
                <input id="clave_confirmation" type="password" name="clave_confirmation" required minlength="8" autocomplete="new-password">
            </div>
            <button type="submit">{{ __('grupos.publico.guardar') }}</button>
        </form>
    </section>
@endsection
