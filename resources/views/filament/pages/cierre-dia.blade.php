{{-- AlojaFlow · Copyright (C) 2026 Juan Galeana Durán · SPDX-License-Identifier: FSL-1.1-ALv2 --}}
@use('App\Support\Formato')
@use('App\Enums\MetodoPago')
<x-filament-panels::page>
    <div class="af-pila">
        <div class="af-barra-herramientas">
            @include('filament.partes.selector-propiedad')
            <label class="af-campo">
                <span>{{ __('ui.hoy.fecha') }}</span>
                <x-filament::input.wrapper><x-filament::input type="date" :value="$this->fecha" wire:model.live="fecha" /></x-filament::input.wrapper>
            </label>
        </div>

        @foreach ($this->dias() as $dia)
            @php($r = $dia['resumen'])
            @php($moneda = $dia['propiedad']->moneda)
            <x-filament::section :heading="$dia['propiedad']->nombre" wire:key="cierre-{{ $dia['propiedad']->id }}">
                <dl class="af-kpis">
                    <div class="af-kpi"><dt>{{ __('cierre.ocupadas') }}</dt><dd>{{ $r['ocupadas'] }} / {{ $r['unidades'] }}</dd></div>
                    <div class="af-kpi"><dt>{{ __('cierre.llegadas_sin_registrar') }}</dt><dd>{{ $r['llegadas_sin_registrar'] }}</dd></div>
                    <div class="af-kpi"><dt>{{ __('cierre.salidas_sin_registrar') }}</dt><dd>{{ $r['salidas_sin_registrar'] }}</dd></div>
                    <div class="af-kpi"><dt>{{ __('cierre.por_cobrar') }}</dt><dd>{{ Formato::dinero($r['por_cobrar_salidas'], $moneda) }}</dd></div>
                    @foreach ($r['cobros'] as $metodo => $importe)
                        <div class="af-kpi"><dt>{{ MetodoPago::tryFrom($metodo)?->etiqueta() ?? $metodo }}</dt><dd>{{ Formato::dinero($importe, $moneda) }}</dd></div>
                    @endforeach
                    <div class="af-kpi"><dt>{{ __('cierre.gastos') }}</dt><dd>{{ Formato::dinero($r['gastos'], $moneda) }}</dd></div>
                    <div class="af-kpi"><dt>{{ __('cierre.ingresos') }}</dt><dd>{{ Formato::dinero($r['ingresos'], $moneda) }}</dd></div>
                </dl>
                <ul class="af-lista-comprobacion">
                    @foreach (['llegadas_sin_registrar', 'salidas_sin_registrar', 'salidas_con_saldo', 'sin_confirmar'] as $apartado)
                        @php($lista = $r['detalle'][$apartado] ?? [])
                        <li @class(['af-lista-comprobacion__pendiente' => $lista !== []])>
                            <span>{{ $lista === [] ? '✓' : '!' }}</span>
                            {{ __('operacion.cierre.'.$apartado) }}: {{ count($lista) }}
                            @foreach ($lista as $reserva)
                                <a class="af-enlace" href="{{ \App\Filament\Resources\Reservas\ReservaResource::getUrl('view', ['record' => $reserva['id']]) }}">{{ $reserva['numero'] }}</a>
                            @endforeach
                        </li>
                    @endforeach
                </ul>
                @if (($r['caja'] ?? []) !== [])
                    <div class="af-tabla-contenedor" style="margin-top: 1rem" tabindex="0" role="region" aria-label="{{ __('operacion.cierre.caja') }}">
                        <table class="af-tabla">
                            <caption class="af-sr">{{ __('operacion.cierre.caja') }}</caption>
                            <thead>
                                <tr>
                                    <th scope="col">{{ __('ui.cajas.modelo_campo') }}</th>
                                    <th scope="col">{{ __('ui.pagos.metodo') }}</th>
                                    <th scope="col">{{ __('operacion.cierre.entradas') }}</th>
                                    <th scope="col">{{ __('operacion.cierre.salidas') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($r['caja'] as $fila)
                                    <tr>
                                        <td>{{ $fila['caja'] ?? '—' }}</td>
                                        <td>{{ MetodoPago::tryFrom($fila['metodo'])?->etiqueta() ?? $fila['metodo'] }}</td>
                                        <td>{{ Formato::dinero($fila['entradas'], $moneda) }}</td>
                                        <td>{{ Formato::dinero($fila['salidas'], $moneda) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                @if (($r['turnos'] ?? []) !== [])
                    <div class="af-tabla-contenedor" style="margin-top: 1rem" tabindex="0" role="region" aria-label="{{ __('caja.turnos.modelos') }}">
                        <table class="af-tabla">
                            <caption class="af-sr">{{ __('caja.turnos.modelos') }}</caption>
                            <thead>
                                <tr>
                                    <th scope="col">{{ __('ui.cajas.modelo_campo') }}</th>
                                    <th scope="col">{{ __('caja.turnos.esperado') }}</th>
                                    <th scope="col">{{ __('caja.turnos.contado') }}</th>
                                    <th scope="col">{{ __('caja.turnos.diferencia') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($r['turnos'] as $turno)
                                    <tr>
                                        <td>{{ $turno['caja'] }}</td>
                                        <td>{{ Formato::dinero($turno['esperado'], $moneda) }}</td>
                                        <td>{{ Formato::dinero($turno['contado'], $moneda) }}</td>
                                        <td>{{ Formato::dinero($turno['diferencia'], $moneda) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                <div style="margin-top: 1rem">
                    @if ($dia['cierre'])
                        <p class="af-nota">{{ __('cierre.cerrado_por', ['quien' => $dia['cierre']->usuario->name ?? '—', 'cuando' => Formato::momento($dia['cierre']->cerrado_en, $dia['propiedad']->zona_horaria)]) }}</p>
                        @if ($dia['cierre']->notas)<p class="af-nota">{{ $dia['cierre']->notas }}</p>@endif
                    @endif
                    {{ ($this->cerrarAction)(['propiedad' => $dia['propiedad']->id]) }}
                </div>
            </x-filament::section>
        @endforeach

        @php($anteriores = $this->anteriores())
        @if ($anteriores->isNotEmpty())
            <x-filament::section :heading="__('cierre.anteriores')">
                <div class="af-tabla-contenedor" tabindex="0" role="region" aria-label="{{ $this->getTitle() }}">
                    <table class="af-tabla">
                        <tbody>
                            @foreach ($anteriores as $cierre)
                                <tr>
                                    <td data-orden="{{ $cierre->fecha?->format('Y-m-d') }}">{{ Formato::fecha($cierre->fecha) }}</td>
                                    <td>{{ $cierre->propiedad->nombre }}</td>
                                    <td>{{ $cierre->usuario->name ?? '—' }}</td>
                                    <td>{{ $cierre->notas }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
