{{-- AlojaFlow · SPDX-License-Identifier: AGPL-3.0-or-later --}}
@extends('publico.layout')
@section('titulo', __('grupos.publico.titulo'))
@section('contenido')
    <section class="tarjeta">
        <h2>{{ __('grupos.publico.cerrado_titulo') }}</h2>
        <p class="gris" style="margin:0">{{ __('grupos.publico.cerrado_texto', ['propiedad' => $propiedad->nombre]) }}</p>
    </section>
@endsection
