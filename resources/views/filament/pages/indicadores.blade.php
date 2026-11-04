{{-- AlojaFlow · SPDX-License-Identifier: AGPL-3.0-or-later --}}
@use('App\Support\Formato')
<x-filament-panels::page>
    @php($datos = $this->datos())
    <div class="af-pila">
        <div class="af-barra-herramientas">
            @include('filament.partes.selector-propiedad')
            <label class="af-campo">
                <span>{{ __('ui.indicadores.desde') }}</span>
                <x-filament::input.wrapper><x-filament::input type="date" :value="$this->desde" wire:model.live="desde" /></x-filament::input.wrapper>
            </label>
            <label class="af-campo">
                <span>{{ __('ui.indicadores.hasta') }}</span>
                <x-filament::input.wrapper><x-filament::input type="date" :value="$this->hasta" wire:model.live="hasta" /></x-filament::input.wrapper>
            </label>
            <div class="af-grupo-botones">
                @foreach (['este_mes', 'mes_anterior', 'anio', 'proximos_90'] as $periodo)
                    <x-filament::button color="gray" size="sm" wire:click="periodo('{{ $periodo }}')">{{ __('ui.indicadores.'.$periodo) }}</x-filament::button>
                @endforeach
                <x-filament::button icon="heroicon-m-arrow-down-tray" size="sm" wire:click="descargarCsv">{{ __('ui.indicadores.descargar_csv') }}</x-filament::button>
            </div>
        </div>

        @if ($this->rango() === null)
            <p class="af-aviso" role="alert">{{ __('ui.indicadores.rango_invalido') }}</p>
        @endif

        @foreach ($datos as $fila)
            @php($moneda = $fila['moneda'])
            <x-filament::section :heading="$fila['propiedad']->nombre">
                <div class="af-pila">
                    <dl class="af-kpis">
                        <div class="af-kpi">
                            <dt>{{ __('ui.indicadores.ocupacion') }}</dt>
                            <dd>{{ Formato::porcentaje($fila['ocupacion']) }}
                                <small>{{ __('ui.indicadores.noches_ocupadas', ['ocupadas' => $fila['ocupadas'], 'disponibles' => $fila['disponibles']]) }}</small></dd>
                        </div>
                        <div class="af-kpi"><dt>{{ __('ui.indicadores.ingresos') }}</dt><dd>{{ Formato::dinero($fila['ingresos'], $moneda) }}</dd></div>
                        <div class="af-kpi" title="{{ __('ui.indicadores.adr_ayuda') }}"><dt>{{ __('ui.indicadores.adr') }}</dt>
                            <dd>{{ $fila['adr'] === null ? '—' : Formato::dinero($fila['adr'], $moneda) }}<small>{{ __('ui.indicadores.adr_ayuda') }}</small></dd></div>
                        <div class="af-kpi" title="{{ __('ui.indicadores.revpar_ayuda') }}"><dt>{{ __('ui.indicadores.revpar') }}</dt>
                            <dd>{{ $fila['revpar'] === null ? '—' : Formato::dinero($fila['revpar'], $moneda) }}<small>{{ __('ui.indicadores.revpar_ayuda') }}</small></dd></div>
                        <div class="af-kpi"><dt>{{ __('ui.indicadores.reservas') }}</dt>
                            <dd>{{ $fila['reservas'] }}<small>{{ __('ui.indicadores.canceladas') }}: {{ $fila['canceladas'] }}</small></dd></div>
                        <div class="af-kpi"><dt>{{ __('ui.indicadores.estancia_media') }}</dt>
                            <dd>{{ $fila['estancia_media'] === null ? '—' : trans_choice('ui.comun.n_noches', (int) ceil($fila['estancia_media']), ['count' => $fila['estancia_media']]) }}</dd></div>
                    </dl>

                    <div class="af-dos-columnas">
                        <div class="af-tabla-contenedor" tabindex="0" role="region" aria-label="{{ __('ui.indicadores.por_origen') }} · {{ $loop->iteration }}">
                            <table class="af-tabla">
                                <caption class="af-sr">{{ __('ui.indicadores.por_origen') }}</caption>
                                <thead><tr><th scope="col">{{ __('ui.indicadores.por_origen') }}</th><th scope="col" class="af-num">{{ __('ui.comun.noches') }}</th><th scope="col" class="af-num">{{ __('ui.indicadores.ingresos') }}</th></tr></thead>
                                <tbody>
                                    @forelse ($fila['por_origen'] as $origen)
                                        <tr><td>{{ $origen->origen }}</td><td class="af-num">{{ $origen->noches }}</td><td class="af-num">{{ Formato::dinero((int) $origen->ingresos, $moneda) }}</td></tr>
                                    @empty
                                        <tr><td colspan="3" class="af-vacio-tabla">{{ __('ui.comun.sin_datos') }}</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div class="af-tabla-contenedor" tabindex="0" role="region" aria-label="{{ __('ui.indicadores.por_mes') }} · {{ $loop->iteration }}">
                            <table class="af-tabla">
                                <caption class="af-sr">{{ __('ui.indicadores.por_mes') }}</caption>
                                <thead><tr><th scope="col">{{ __('ui.indicadores.mes') }}</th><th scope="col" class="af-num">{{ __('ui.comun.noches') }}</th><th scope="col" class="af-num">{{ __('ui.indicadores.ingresos') }}</th></tr></thead>
                                <tbody>
                                    @forelse ($fila['por_mes'] as $mes)
                                        <tr>
                                            <td>{{ Formato::fecha(\Carbon\CarbonImmutable::createFromFormat('!Y-m', $mes->mes), 'MMMM YYYY') }}</td>
                                            <td class="af-num">{{ $mes->noches }}</td>
                                            <td class="af-num">{{ Formato::dinero((int) $mes->ingresos, $moneda) }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="3" class="af-vacio-tabla">{{ __('ui.comun.sin_datos') }}</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </x-filament::section>
        @endforeach

        <p class="af-nota">{{ __('ui.indicadores.nota') }}</p>
    </div>
</x-filament-panels::page>
