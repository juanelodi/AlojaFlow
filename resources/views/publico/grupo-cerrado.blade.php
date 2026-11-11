{{-- AlojaFlow · Copyright (C) 2026 Juan Galeana Durán · SPDX-License-Identifier: FSL-1.1-ALv2 --}}
@extends('publico.layout')
@section('titulo', __('grupos.publico.titulo'))
@section('contenido')
    <section class="tarjeta">
        <h2>{{ __('grupos.publico.cerrado_titulo') }}</h2>
        <p class="gris" style="margin:0">{{ __('grupos.publico.cerrado_texto', ['propiedad' => $propiedad->nombre]) }}</p>
    </section>
@endsection
