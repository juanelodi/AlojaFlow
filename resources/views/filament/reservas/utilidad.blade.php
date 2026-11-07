{{-- AlojaFlow · SPDX-License-Identifier: AGPL-3.0-or-later --}}
@use('App\Support\Formato')
@php
    $reserva = $getRecord();
    $utilidad = app(\App\Reservas\UtilidadReserva::class)->de($reserva);
@endphp
@if ($utilidad !== null)
    <details class="af-plegable">
        <summary>
            {{ __('canales.utilidad.titulo') }}:
            <span>{{ __('canales.utilidad.bruta') }} {{ Formato::dinero($utilidad['bruta'], $reserva->moneda) }}</span> ·
            <span>{{ __('canales.utilidad.neta') }} {{ Formato::dinero($utilidad['neta'], $reserva->moneda) }}</span>
        </summary>
        <dl class="af-utilidad">
            <div><dt>{{ __('canales.utilidad.ingreso') }}</dt><dd>{{ Formato::dinero($utilidad['ingreso'], $reserva->moneda) }}</dd></div>
            <div><dt>{{ __('canales.utilidad.impuestos') }}</dt><dd>− {{ Formato::dinero($utilidad['impuestos'], $reserva->moneda) }}</dd></div>
            <div><dt>{{ __('ui.reservas.comision') }}</dt><dd>− {{ Formato::dinero($utilidad['comision'], $reserva->moneda) }}</dd></div>
            @foreach ($utilidad['cargos'] as $cargo)
                <div><dt>{{ $cargo['nombre'] }}</dt><dd>− {{ Formato::dinero($cargo['importe'], $reserva->moneda) }}</dd></div>
            @endforeach
            <div class="af-utilidad__suma"><dt>{{ __('canales.utilidad.bruta') }}</dt><dd>{{ Formato::dinero($utilidad['bruta'], $reserva->moneda) }}</dd></div>
            @foreach ($utilidad['retenciones'] as $retencion)
                <div><dt>{{ $retencion['nombre'] }}</dt><dd>− {{ Formato::dinero($retencion['importe'], $reserva->moneda) }}</dd></div>
            @endforeach
            <div class="af-utilidad__suma"><dt>{{ __('canales.utilidad.neta') }}</dt><dd>{{ Formato::dinero($utilidad['neta'], $reserva->moneda) }}</dd></div>
        </dl>
        <p class="af-nota">{{ $utilidad['canal'] === null ? __('canales.utilidad.sin_canal') : __('canales.utilidad.ayuda') }}</p>
    </details>
@endif
