{{-- AlojaFlow · SPDX-License-Identifier: AGPL-3.0-or-later --}}
<x-filament-panels::page>
    @php($tarjetas = $this->tarjetas())
    @php($pasos = $this->primerosPasos())

    @if ($pasos === [] && $this->faltaDosPasos())
        <p class="af-aviso af-aviso-global" role="status">
            {{ __('ui.resumen.dos_pasos') }}
            <a class="af-enlace" href="{{ filament()->getProfileUrl() }}">{{ __('ui.resumen.dos_pasos_enlace') }}</a>
        </p>
    @endif

    @if (($nuevos = $this->modulosNuevos()) !== [])
        <p class="af-aviso af-aviso-global" role="status">
            {{ __('modulos.aviso_nuevos', ['modulos' => collect($nuevos)->map->etiqueta()->join(', ')]) }}
            <a class="af-enlace" href="{{ \App\Filament\Pages\ModulosInstalacion::getUrl() }}">{{ __('modulos.ver') }}</a>
        </p>
    @endif

    @if ($pasos !== [])
        <x-filament::section class="af-pasos-seccion" :heading="__('modulos.pasos.titulo')" :description="__('modulos.pasos.avance', ['hechos' => collect($pasos)->where('hecho', true)->count(), 'total' => count($pasos)])" collapsible>
            <ul class="af-pasos">
                @foreach ($pasos as $paso)
                    <li @class(['af-paso', 'af-paso--hecho' => $paso['hecho']]) wire:key="paso-{{ $paso['clave'] }}">
                        <span class="af-paso__marca" aria-hidden="true">@if ($paso['hecho'])<x-filament::icon icon="heroicon-m-check" class="af-paso__icono" />@endif</span>
                        <div class="af-paso__texto">
                            <strong>{{ __('modulos.pasos.'.$paso['clave'].'.titulo') }}</strong>
                            @if ($paso['hecho'])
                                <span class="af-sr">{{ __('ui.comun.hecho') }}</span>
                            @else
                                <p class="af-nota">{{ __('modulos.pasos.'.$paso['clave'].'.ayuda') }}</p>
                                @if ($paso['comando'])
                                    <div class="af-comando" x-data="{ copiado: false }">
                                        <code x-ref="comando">{{ $paso['comando'] }}</code>
                                        <x-filament::button size="sm" color="gray" x-on:click="navigator.clipboard?.writeText($refs.comando.textContent).then(() => { copiado = true })">
                                            <span x-show="! copiado">{{ __('modulos.pasos.copiar') }}</span>
                                            <span x-show="copiado" x-cloak>{{ __('modulos.pasos.copiado') }}</span>
                                        </x-filament::button>
                                    </div>
                                @endif
                            @endif
                        </div>
                        @if (! $paso['hecho'] && $paso['url'])
                            <x-filament::button tag="a" size="sm" color="gray" :href="$paso['url']">{{ __('modulos.pasos.ir') }}</x-filament::button>
                        @endif
                    </li>
                @endforeach
            </ul>
            <div class="af-pasos__pie">
                <button type="button" class="af-enlace" wire:click="ocultarPrimerosPasos">{{ __('modulos.pasos.ocultar') }}</button>
            </div>
        </x-filament::section>
    @endif

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
                        @if (\App\Seguridad\Acceso::puede(\App\Seguridad\Acceso::usuarioActual(), \App\Seguridad\Acceso::DATOS, $propiedad->id))
                            <div @class(['af-cifra', 'af-cifra--destacada' => $t['pendiente'] > 0]) title="{{ __('ui.resumen.pendiente_ayuda') }}">
                                <dt>{{ __('ui.resumen.pendiente') }}</dt><dd>{{ \App\Support\Formato::dinero($t['pendiente'], $propiedad->moneda) }}</dd>
                            </div>
                        @endif
                    </dl>

                    {{-- Hablan de pagos, sincronización y mensajes: no son para limpieza ni mantenimiento. --}}
                    @foreach (\App\Seguridad\Acceso::puede(\App\Seguridad\Acceso::usuarioActual(), \App\Seguridad\Acceso::DATOS, $propiedad->id) ? $t['avisos'] : [] as $aviso)
                        <div @class(['af-aviso', 'af-aviso--error' => $aviso->nivel === \App\Enums\NivelAviso::Error]) role="status">
                            {{ $aviso->mensaje() }}
                            @if (in_array($aviso->tipo, \App\Models\Aviso::SE_CIERRAN_A_MANO, true))
                                <button type="button" class="af-enlace" wire:click="atenderAviso({{ $aviso->id }})">{{ __('ui.resumen.aviso_atendido') }}</button>
                            @endif
                        </div>
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
