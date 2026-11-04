{{-- AlojaFlow · SPDX-License-Identifier: AGPL-3.0-or-later --}}
@use('App\Support\Formato')
@use('App\Enums\MetodoPago')
<x-filament-panels::page>
    <div class="af-pila">
        <div class="af-barra-herramientas">
            @include('filament.partes.selector-propiedad')
            <label class="af-campo">
                <span>{{ __('ui.hoy.fecha') }}</span>
                <x-filament::input.wrapper><x-filament::input type="date" wire:model.live="fecha" /></x-filament::input.wrapper>
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
                                    <td>{{ Formato::fecha($cierre->fecha) }}</td>
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
