{{-- AlojaFlow · Copyright (C) 2026 Juan Galeana Durán · SPDX-License-Identifier: FSL-1.1-ALv2 --}}
@extends('publico.layout')
@section('titulo', __('portal.guia'))
@section('contenido')
    <section class="tarjeta">
        <h2>{{ __('portal.guia_de', ['unidad' => $unidad->nombre]) }}</h2>
        @if ($guia === [])
            <p class="gris" style="margin:0">{{ __('portal.guia_vacia') }}</p>
        @endif
        @include('publico.contacto')
    </section>
    @include('publico.guia-secciones')
@endsection
