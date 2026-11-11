{{-- AlojaFlow · Copyright (C) 2026 Juan Galeana Durán · SPDX-License-Identifier: FSL-1.1-ALv2 --}}
<x-filament-panels::page>
    @php($listas = $this->listas())
    @php($variasPropiedades = count($this->propiedadesElegidas()) > 1)
    <div class="af-pila">
        <div class="af-barra-herramientas">
            @include('filament.partes.selector-propiedad')
            <label class="af-campo">
                <span>{{ __('ui.hoy.fecha') }}</span>
                <x-filament::input.wrapper>
                    <x-filament::input type="date" :value="$this->fecha" wire:model.live="fecha" />
                </x-filament::input.wrapper>
            </label>
            <div class="af-grupo-botones">
                <x-filament::button color="gray" icon="heroicon-m-chevron-left" wire:click="moverDia(-1)" :label-sr-only="true" :tooltip="__('ui.comun.anterior')">
                    {{ __('ui.comun.anterior') }}
                </x-filament::button>
                <x-filament::button color="gray" wire:click="irAHoy">{{ __('ui.comun.hoy') }}</x-filament::button>
                <x-filament::button color="gray" icon="heroicon-m-chevron-right" wire:click="moverDia(1)" :label-sr-only="true" :tooltip="__('ui.comun.siguiente')">
                    {{ __('ui.comun.siguiente') }}
                </x-filament::button>
            </div>
        </div>

        <p class="af-nota">{{ \App\Support\Formato::fechaLarga($this->fechaElegida()) }}@if ($this->haySubordinadas()) · {{ __('ui.comun.solo_consulta') }}@endif</p>

        @php($cifras = $this->cifrasDelDia($listas))
        @if ($cifras['pendiente'] !== [] || $cifras['por_limpiar'] !== null)
            <dl class="af-kpis af-kpis--compactas">
                @foreach ($cifras['pendiente'] as $moneda => $importe)
                    <div class="af-kpi"><dt>{{ __('operacion.hoy.pendiente') }}</dt><dd>{{ \App\Support\Formato::dinero($importe, $moneda) }}</dd></div>
                @endforeach
                @if ($cifras['por_limpiar'] !== null)
                    <div class="af-kpi"><dt>{{ __('operacion.hoy.por_limpiar') }}</dt><dd>{{ $cifras['por_limpiar'] }}</dd></div>
                @endif
            </dl>
        @endif

        @php($notas = $this->notasImportantes())
        @if ($notas->isNotEmpty())
            <x-filament::section :heading="__('caja.bitacora.importantes')">
                <ul class="af-lista-notas">
                    @foreach ($notas as $nota)
                        <li wire:key="nota-{{ $nota->id }}">
                            <p style="white-space: pre-line">{{ $nota->texto }}</p>
                            <p class="af-nota">{{ $nota->autor?->name ?? '—' }} · {{ \App\Support\Formato::momento($nota->creado_en) }}</p>
                        </li>
                    @endforeach
                </ul>
                <p style="margin-top: .5rem"><a class="af-enlace" href="{{ \App\Filament\Resources\Bitacora\NotaBitacoraResource::getUrl() }}">{{ __('caja.bitacora.ver_todas') }}</a></p>
            </x-filament::section>
        @endif

        @foreach (['llegan', 'salen', 'continuan'] as $lista)
            {{-- Cuántas ya llegaron o salieron (D-153): lo primero que pregunta recepción. --}}
            @php($hechas = match ($lista) { 'llegan' => $listas[$lista]->filter(fn ($r) => $r->entradaEn() !== null)->count(), 'salen' => $listas[$lista]->filter(fn ($r) => $r->salidaEn() !== null)->count(), default => null })
            <x-filament::section class="af-seccion-lista" :heading="__('ui.hoy.'.$lista).' ('.$listas[$lista]->count().')'"
                :description="$hechas === null || $listas[$lista]->isEmpty() ? null : __('ui.hoy.ya_'.$lista, ['hechas' => $hechas, 'total' => $listas[$lista]->count()])">
                <x-tabla-reservas
                    :reservas="$listas[$lista]"
                    :titulo="__('ui.hoy.'.$lista)"
                    :vacio="__('ui.hoy.vacio_'.$lista)"
                    :mostrar-propiedad="$variasPropiedades"
                    :gestionables="$this->propiedadesMarcables()"
                />
            </x-filament::section>
        @endforeach
    </div>
</x-filament-panels::page>
