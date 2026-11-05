{{-- AlojaFlow · SPDX-License-Identifier: AGPL-3.0-or-later --}}
@use('App\Support\Formato')
@use('Carbon\CarbonImmutable')
@extends('documentos.base')
@section('titulo', __('propietarios.estado.titulo'))
@section('contenido')
    <h2>{{ $estado->propietario->nombre }} · {{ $estado->periodo() }}</h2>
    <table class="datos">
        <tr><th>{{ __('ui.comun.estado') }}</th><td>{{ $estado->fase->etiqueta() }}@if ($estado->pagado_en) · {{ Formato::fecha($estado->pagado_en) }}@endif @if ($estado->referencia_pago) · {{ $estado->referencia_pago }}@endif</td></tr>
    </table>

    <h2>{{ __('propietarios.estado.resumen') }}</h2>
    <table class="importes">
        <tr><td>{{ __('propietarios.estado.ingresos') }}</td><td class="num">{{ Formato::dinero($estado->ingresos, $estado->moneda) }}</td></tr>
        <tr><td>{{ __('propietarios.estado.comision_portales') }}</td><td class="num">− {{ Formato::dinero($estado->comision_portales, $estado->moneda) }}</td></tr>
        <tr><td>{{ __('propietarios.estado.comision') }}</td><td class="num">− {{ Formato::dinero($estado->comision, $estado->moneda) }}</td></tr>
        <tr><td>{{ __('propietarios.estado.gastos') }}</td><td class="num">− {{ Formato::dinero($estado->gastos, $estado->moneda) }}</td></tr>
        <tr><td>{{ __('propietarios.estado.cargo_fijo') }}</td><td class="num">− {{ Formato::dinero($estado->cargo_fijo, $estado->moneda) }}</td></tr>
        <tr class="total"><td>{{ __('propietarios.estado.neto') }}</td><td class="num">{{ Formato::dinero($estado->neto, $estado->moneda) }}</td></tr>
    </table>

    @if (! empty($estado->detalle['reservas']))
        <h2>{{ __('propietarios.estado.reservas') }}</h2>
        <table class="importes">
            <tr>
                <th style="padding-right:8px">{{ __('informes.columnas.reserva') }}</th>
                <th style="padding-right:8px">{{ __('ui.comun.unidad') }}</th>
                <th style="padding-right:8px">{{ __('ui.comun.llegada') }}</th>
                <th style="padding-right:8px">{{ __('ui.comun.salida') }}</th>
                <th class="num">{{ __('ui.comun.noches') }}</th>
                <th class="num">{{ __('propietarios.estado.ingresos') }}</th>
                <th class="num">{{ __('propietarios.estado.portal') }}</th>
                <th class="num">{{ __('propietarios.unidades.comision') }}</th>
            </tr>
            @foreach ($estado->detalle['reservas'] as $linea)
                <tr>
                    <td style="padding-right:8px">{{ $linea['numero'] }}</td>
                    <td style="padding-right:8px">{{ $linea['unidad'] }}</td>
                    <td style="padding-right:8px">{{ Formato::fecha(CarbonImmutable::parse($linea['llegada'])) }}</td>
                    <td style="padding-right:8px">{{ Formato::fecha(CarbonImmutable::parse($linea['salida'])) }}</td>
                    <td class="num">{{ $linea['noches'] }}</td>
                    <td class="num" style="padding-left:8px">{{ Formato::dinero($linea['ingreso'], $estado->moneda) }}</td>
                    <td class="num" style="padding-left:8px">{{ Formato::dinero($linea['portal'], $estado->moneda) }}</td>
                    <td class="num">{{ Formato::dinero($linea['comision'], $estado->moneda) }} ({{ Formato::tasa($linea['porcentaje']) }})</td>
                </tr>
            @endforeach
        </table>
    @endif

    @if (! empty($estado->detalle['estancias']))
        <h2>{{ __('propietarios.estado.estancias') }}</h2>
        <table class="importes">
            @foreach ($estado->detalle['estancias'] as $linea)
                <tr>
                    <td>{{ $linea['unidad'] }}</td>
                    <td>{{ Formato::fecha(CarbonImmutable::parse($linea['llegada'])) }} → {{ Formato::fecha(CarbonImmutable::parse($linea['salida'])) }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    @if (! empty($estado->detalle['gastos']))
        <h2>{{ __('propietarios.estado.gastos') }}</h2>
        <table class="importes">
            @foreach ($estado->detalle['gastos'] as $linea)
                <tr>
                    <td>{{ Formato::fecha(CarbonImmutable::parse($linea['fecha'])) }}</td>
                    <td>{{ $linea['concepto'] }}@if ($linea['unidad']) · {{ $linea['unidad'] }}@endif</td>
                    <td class="num">{{ Formato::dinero($linea['importe'], $estado->moneda) }}</td>
                </tr>
            @endforeach
        </table>
    @endif
@endsection
