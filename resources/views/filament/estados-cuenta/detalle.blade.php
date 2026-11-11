{{-- AlojaFlow · Copyright (C) 2026 Juan Galeana Durán · SPDX-License-Identifier: FSL-1.1-ALv2 --}}
@use('App\Filament\Resources\Reservas\ReservaResource')
@use('App\Support\Formato')
@use('App\Propietarios\GestorEstados')
@use('App\Enums\FaseEstadoCuenta')
@use('Carbon\CarbonImmutable')
@php($estado = $getRecord())
<div class="af-pila">
    @if ($estado->fase !== FaseEstadoCuenta::Borrador && app(GestorEstados::class)->difiere($estado))
        <p class="af-nota" role="status">{{ __('propietarios.estado.difiere') }}</p>
    @endif
    <x-filament::section :heading="__('propietarios.estado.resumen')">
        <table class="af-tabla">
            <caption class="af-sr">{{ __('propietarios.estado.resumen') }}</caption>
            <tbody>
                <tr><th scope="row">{{ __('propietarios.estado.ingresos') }}</th><td class="af-num">{{ Formato::dinero($estado->ingresos, $estado->moneda) }}</td></tr>
                <tr><th scope="row">{{ __('propietarios.estado.comision_portales') }}</th><td class="af-num">− {{ Formato::dinero($estado->comision_portales, $estado->moneda) }}</td></tr>
                <tr><th scope="row">{{ __('propietarios.estado.comision') }}</th><td class="af-num">− {{ Formato::dinero($estado->comision, $estado->moneda) }}</td></tr>
                <tr><th scope="row">{{ __('propietarios.estado.gastos') }}</th><td class="af-num">− {{ Formato::dinero($estado->gastos, $estado->moneda) }}</td></tr>
                <tr><th scope="row">{{ __('propietarios.estado.cargo_fijo') }}</th><td class="af-num">− {{ Formato::dinero($estado->cargo_fijo, $estado->moneda) }}</td></tr>
                <tr><th scope="row"><strong>{{ __('propietarios.estado.neto') }}</strong></th><td class="af-num"><strong>{{ Formato::dinero($estado->neto, $estado->moneda) }}</strong></td></tr>
            </tbody>
        </table>
    </x-filament::section>

    <x-filament::section :heading="__('propietarios.estado.reservas')">
        @if (empty($estado->detalle['reservas']))
            <p class="af-vacio">{{ __('propietarios.estado.sin_reservas') }}</p>
        @else
            <div class="af-tabla-contenedor" tabindex="0" role="region" aria-label="{{ __('propietarios.estado.reservas') }}">
                <table class="af-tabla">
                    <caption class="af-sr">{{ __('propietarios.estado.reservas') }}</caption>
                    <thead>
                        <tr>
                            <th scope="col">{{ __('informes.columnas.reserva') }}</th>
                            <th scope="col">{{ __('ui.comun.unidad') }}</th>
                            <th scope="col">{{ __('ui.comun.llegada') }}</th>
                            <th scope="col">{{ __('ui.comun.salida') }}</th>
                            <th scope="col" class="af-num">{{ __('ui.comun.noches') }}</th>
                            <th scope="col" class="af-num">{{ __('propietarios.estado.ingresos') }}</th>
                            <th scope="col" class="af-num">{{ __('propietarios.estado.portal') }}</th>
                            <th scope="col" class="af-num">{{ __('propietarios.unidades.comision') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($estado->detalle['reservas'] as $linea)
                            <tr>
                                <td>{{ $linea['numero'] }}</td>
                                <td>{{ $linea['unidad'] }}</td>
                                <td style="white-space: nowrap" data-orden="{{ CarbonImmutable::parse($linea['llegada'])->format('Y-m-d') }}">{{ Formato::fecha(CarbonImmutable::parse($linea['llegada']), 'D MMM') }}</td>
                                <td style="white-space: nowrap" data-orden="{{ CarbonImmutable::parse($linea['salida'])->format('Y-m-d') }}">{{ Formato::fecha(CarbonImmutable::parse($linea['salida']), 'D MMM') }}</td>
                                <td class="af-num">{{ $linea['noches'] }}</td>
                                <td class="af-num">{{ Formato::dinero($linea['ingreso'], $estado->moneda) }}</td>
                                <td class="af-num">{{ Formato::dinero($linea['portal'], $estado->moneda) }}</td>
                                <td class="af-num">{{ Formato::dinero($linea['comision'], $estado->moneda) }} ({{ Formato::tasa($linea['porcentaje']) }})</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>

    @if (! empty($estado->detalle['estancias']))
        <x-filament::section :heading="__('propietarios.estado.estancias')">
            <table class="af-tabla">
                <caption class="af-sr">{{ __('propietarios.estado.estancias') }}</caption>
                <tbody>
                    @foreach ($estado->detalle['estancias'] as $linea)
                        <tr><td>{{ $linea['unidad'] }}</td><td data-orden="{{ CarbonImmutable::parse($linea['llegada'])->format('Y-m-d') }}">{{ Formato::fecha(CarbonImmutable::parse($linea['llegada'])) }} → {{ Formato::fecha(CarbonImmutable::parse($linea['salida'])) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </x-filament::section>
    @endif

    @if (! empty($estado->detalle['gastos']))
        <x-filament::section :heading="__('propietarios.estado.gastos')">
            <table class="af-tabla">
                <caption class="af-sr">{{ __('propietarios.estado.gastos') }}</caption>
                <tbody>
                    @foreach ($estado->detalle['gastos'] as $linea)
                        <tr>
                            <td data-orden="{{ CarbonImmutable::parse($linea['fecha'])->format('Y-m-d') }}">{{ Formato::fecha(CarbonImmutable::parse($linea['fecha'])) }}</td>
                            <td>{{ $linea['concepto'] }}@if ($linea['unidad']) · {{ $linea['unidad'] }}@endif</td>
                            <td class="af-num">{{ Formato::dinero($linea['importe'], $estado->moneda) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-filament::section>
    @endif
</div>
