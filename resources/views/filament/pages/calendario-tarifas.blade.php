{{-- AlojaFlow · SPDX-License-Identifier: AGPL-3.0-or-later --}}
@use('App\Support\Dinero')
@use('App\Support\Formato')
<x-filament-panels::page>
    @php
        $datos = $this->datos();
        $dias = $datos['dias'];
        $hoy = \App\Servicios\Operacion::hoyDe($this->propiedadUnica())->format('Y-m-d');
        $hayFilas = collect($datos['grupos'])->contains(fn (array $g): bool => $g['filas'] !== []);
        $varias = count($datos['grupos']) > 1;
    @endphp
    <div class="af-pila">
        <div class="af-barra-herramientas">
            @include('filament.partes.selector-propiedad')
            <label class="af-campo">
                <span>{{ __('ui.calendario.mes') }}</span>
                <x-filament::input.wrapper>
                    <x-filament::input type="month" :value="$this->mes" wire:model.live="mes" />
                </x-filament::input.wrapper>
            </label>
            <div class="af-grupo-botones">
                <x-filament::button color="gray" icon="heroicon-m-chevron-left" wire:click="moverMes(-1)" :label-sr-only="true" :tooltip="__('ui.comun.anterior')">{{ __('ui.comun.anterior') }}</x-filament::button>
                <x-filament::button color="gray" wire:click="irAEsteMes">{{ __('ui.calendario.este_mes') }}</x-filament::button>
                <x-filament::button color="gray" icon="heroicon-m-chevron-right" wire:click="moverMes(1)" :label-sr-only="true" :tooltip="__('ui.comun.siguiente')">{{ __('ui.comun.siguiente') }}</x-filament::button>
            </div>
        </div>
        <div class="af-leyenda">
            <span><i class="af-tar-muestra af-tar-muestra--temporada"></i>{{ __('tarifas_calendario.con_temporada') }}</span>
            <span><i class="af-tar-muestra af-tar-muestra--cerrado"></i>{{ __('tarifas_calendario.cerrado') }}</span>
            <span class="af-nota">{{ __('tarifas_calendario.ayuda') }}</span>
        </div>

        @if (! $hayFilas)
            <x-filament::empty-state :heading="__('tarifas_calendario.vacio')" icon="heroicon-o-table-cells" />
        @else
            <div class="af-calendario af-tar" style="--af-dias: {{ count($dias) }}" role="region" aria-label="{{ __('tarifas_calendario.titulo') }} {{ Formato::fecha($this->inicioMes(), 'MMMM YYYY') }}" tabindex="0">
                <div class="af-cal-fila af-cal-fila--cabecera">
                    <div class="af-cal-unidad">{{ Formato::fecha($this->inicioMes(), 'MMMM YYYY') }}</div>
                    @foreach ($dias as $i => $dia)
                        <div @class(['af-cal-cabecera-dia', 'af-cal-dia--finde' => $dia->isWeekend(), 'af-cal-dia--hoy' => $dia->format('Y-m-d') === $hoy]) style="grid-column: {{ $i + 2 }}">
                            {{ $dia->locale(app()->getLocale())->isoFormat('dd') }}<strong>{{ $dia->day }}</strong>
                        </div>
                    @endforeach
                </div>
                @foreach ($datos['grupos'] as $grupo)
                    @continue($grupo['filas'] === [])
                    <div class="af-cal-fila af-cal-fila--grupo" wire:key="propiedad-{{ $grupo['propiedad']->id }}"><div>{{ $grupo['propiedad']->nombre }} · {{ $grupo['propiedad']->moneda }}</div></div>
                    @foreach ($grupo['filas'] as $fila)
                        @php($tarifa = $fila['tarifa'])
                        @php($moneda = $grupo['propiedad']->moneda)
                        <div class="af-cal-fila" wire:key="tarifa-{{ $tarifa->id }}">
                            <div class="af-cal-unidad">
                                {{ $tarifa->nombre }}
                                @if ($tarifa->base)<span class="af-secundario">{{ __('tarifas_calendario.sale_de', ['tarifa' => $tarifa->base->nombre]) }}</span>@endif
                            </div>
                            @foreach ($fila['celdas'] as $i => $celda)
                                @php($clases = \Illuminate\Support\Arr::toCssClasses(['af-tar-celda', 'af-tar-celda--temporada' => $celda['temporada'], 'af-tar-celda--cerrado' => $celda['cerrado_llegada'] || $celda['cerrado_salida'], 'af-tar-celda--hoy' => $celda['fecha'] === $hoy]))
                                @php($aviso = implode(' · ', array_filter([Formato::fechaCorta($dias[$i]), Formato::dinero($celda['precio'], $moneda), $celda['temporada'], $celda['estancia_minima'] > 1 ? __('tarifas_calendario.minimo', ['noches' => $celda['estancia_minima']]) : null, $celda['cerrado_llegada'] ? __('tarifas_calendario.sin_llegadas') : null, $celda['cerrado_salida'] ? __('tarifas_calendario.sin_salidas') : null])))
                                @if ($tarifa->base)
                                    <div class="{{ $clases }}" style="grid-column: {{ $i + 2 }}" title="{{ $aviso }}">{{ Dinero::corto($celda['precio'], $moneda) }}@if ($celda['estancia_minima'] > 1)<small>{{ __('tarifas_calendario.noches_corto', ['n' => $celda['estancia_minima']]) }}</small>@endif</div>
                                @else
                                    <button type="button" class="{{ $clases }}" style="grid-column: {{ $i + 2 }}" title="{{ $aviso }}" aria-label="{{ $tarifa->nombre }}: {{ $aviso }}"
                                        wire:click="mountAction('fijarPrecio', { tarifa: {{ $tarifa->id }}, fecha: '{{ $celda['fecha'] }}' })">{{ Dinero::corto($celda['precio'], $moneda) }}@if ($celda['estancia_minima'] > 1)<small>{{ __('tarifas_calendario.noches_corto', ['n' => $celda['estancia_minima']]) }}</small>@endif</button>
                                @endif
                            @endforeach
                        </div>
                    @endforeach
                @endforeach
            </div>
        @endif
    </div>
</x-filament-panels::page>
