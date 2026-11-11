{{-- AlojaFlow · Copyright (C) 2026 Juan Galeana Durán · SPDX-License-Identifier: FSL-1.1-ALv2 --}}
{{-- Una pasarela a la que se llega mandando un formulario firmado. Sin JavaScript, el botón hace lo mismo. --}}
@extends('publico.layout')
@section('titulo', __('motor.ir_titulo'))
@section('contenido')
    <section class="tarjeta">
        <h2>{{ __('motor.ir_titulo') }}</h2>
        <p class="gris">{{ __('motor.ir_ayuda') }}</p>
        <form id="ir-a-pago" method="post" action="{{ $formulario['accion'] }}">
            @foreach ($formulario['campos'] as $nombre => $valor)
                <input type="hidden" name="{{ $nombre }}" value="{{ $valor }}">
            @endforeach
            <button type="submit">{{ __('motor.ir_boton') }}</button>
        </form>
    </section>
    <script>
        // Quien vuelve atrás desde la pasarela se queda aquí: si no, no podría salir de ella.
        if (performance.getEntriesByType('navigation')[0]?.type !== 'back_forward') {
            document.getElementById('ir-a-pago').submit();
        }
    </script>
@endsection
