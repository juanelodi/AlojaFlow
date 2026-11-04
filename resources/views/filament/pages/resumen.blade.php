{{-- AlojaFlow · SPDX-License-Identifier: AGPL-3.0-or-later --}}
<x-filament-panels::page>
    @php($tarjetas = $this->tarjetas())

    @if ($tarjetas === [])
        <x-filament::empty-state
            :heading="__('ui.resumen.sin_propiedades')"
            :description="$this->puedeConfigurar() ? __('ui.resumen.sin_propiedades_texto') : __('ui.resumen.sin_acceso_texto')"
            icon="heroicon-o-home-modern"
        >
            @if ($this->puedeConfigurar())
                <x-slot name="footer">
                    <x-filament::button tag="a" :href="\App\Filament\Resources\Propiedades\PropiedadResource::getUrl('create')" icon="heroicon-m-plus">
                        {{ __('ui.resumen.crear_propiedad') }}
                    </x-filament::button>
                </x-slot>
            @endif
        </x-filament::empty-state>
    @else
        <div class="af-tarjetas">
            @foreach ($tarjetas as $t)
                @php($propiedad = $t['propiedad'])
                <section class="af-tarjeta" style="--af-color: {{ $propiedad->color }}" aria-labelledby="propiedad-{{ $propiedad->id }}">
                    <div class="af-tarjeta__cabecera">
                        <h2 class="af-tarjeta__titulo" id="propiedad-{{ $propiedad->id }}">{{ $propiedad->nombre }}</h2>
                        <span class="af-tarjeta__fecha">{{ \App\Support\Formato::fecha($t['hoy'], 'dddd D MMMM') }}</span>
                    </div>

                    <dl class="af-cifras">
                        <div class="af-cifra"><dt>{{ __('ui.resumen.llegan_hoy') }}</dt><dd>{{ $t['llegan'] }}</dd></div>
                        <div class="af-cifra"><dt>{{ __('ui.resumen.salen_hoy') }}</dt><dd>{{ $t['salen'] }}</dd></div>
                        <div class="af-cifra"><dt>{{ __('ui.resumen.alojados') }}</dt><dd>{{ $t['alojados'] }}<span class="af-nota"> / {{ $t['unidades'] }}</span></dd></div>
                        <div class="af-cifra"><dt>{{ __('ui.resumen.llegadas_semana') }}</dt><dd>{{ $t['llegadas_semana'] }}</dd></div>
                        <div @class(['af-cifra', 'af-cifra--destacada' => $t['sin_confirmar'] > 0])><dt>{{ __('ui.resumen.sin_confirmar') }}</dt><dd>{{ $t['sin_confirmar'] }}</dd></div>
                        <div @class(['af-cifra', 'af-cifra--destacada' => $t['pendiente'] > 0]) title="{{ __('ui.resumen.pendiente_ayuda') }}">
                            <dt>{{ __('ui.resumen.pendiente') }}</dt><dd>{{ \App\Support\Formato::dinero($t['pendiente'], $propiedad->moneda) }}</dd>
                        </div>
                    </dl>

                    @foreach ($t['avisos'] as $aviso)
                        <p @class(['af-aviso', 'af-aviso--error' => $aviso->nivel === \App\Enums\NivelAviso::Error]) role="status">{{ $aviso->mensaje() }}</p>
                    @endforeach

                    <div class="af-tarjeta__pie">
                        @if ($propiedad->esIndependiente())
                            <span class="af-frescura">{{ $propiedad->es_demo ? __('ui.demo.etiqueta') : $propiedad->modo->etiqueta() }}</span>
                        @else
                            <x-frescura :principal="$t['principal']" />
                        @endif
                        <span class="af-enlaces">
                            <a href="{{ \App\Filament\Pages\Hoy::getUrl(['propiedad' => $propiedad->id]) }}">{{ __('ui.resumen.ir_hoy') }}</a>
                            <a href="{{ \App\Filament\Pages\Calendario::getUrl(['propiedad' => $propiedad->id]) }}">{{ __('ui.resumen.ir_calendario') }}</a>
                        </span>
                    </div>
                </section>
            @endforeach
        </div>
    @endif
</x-filament-panels::page>
