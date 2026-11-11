{{-- AlojaFlow · Copyright (C) 2026 Juan Galeana Durán · SPDX-License-Identifier: FSL-1.1-ALv2 --}}
@extends('publico.layout')
@section('titulo', __('llegada.verificar_titulo'))
@section('contenido')
    @if ($errors->any())
        <p class="mensaje error" role="alert">{{ $errors->first() }}</p>
    @endif
    @if (session('aviso_llegada'))
        <p class="mensaje bien" role="status">{{ session('aviso_llegada') }}</p>
    @endif
    <section class="tarjeta">
        <h2>{{ __('llegada.verificar_titulo') }}</h2>
        <p class="gris">{{ __('llegada.verificar_ayuda', ['correo' => $correo]) }}</p>
        <form method="post" action="{{ route('llegada.comprobar', ['token' => $token, 'idioma' => app()->getLocale()]) }}">
            @csrf
            <div class="campo"><label for="codigo">{{ __('llegada.codigo') }}</label><input type="text" id="codigo" name="codigo" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required></div>
            <button type="submit">{{ __('llegada.verificar') }}</button>
        </form>
        <form method="post" action="{{ route('llegada.reenviar', ['token' => $token, 'idioma' => app()->getLocale()]) }}" style="margin-top:12px">
            @csrf
            <button type="submit" class="boton secundario">{{ __('llegada.reenviar') }}</button>
        </form>
    </section>
@endsection
