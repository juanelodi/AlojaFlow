{{-- AlojaFlow · SPDX-License-Identifier: AGPL-3.0-or-later --}}
@php($total = \App\Filament\Pages\PrimerasPreguntas::PASOS)
<x-filament-panels::page>
    <div class="af-preguntas">
        <p class="af-prosa">{{ __('modulos.preguntas.explicacion') }}</p>

        @if ($paso <= $total)
            <p class="af-preguntas__paso" aria-live="polite">{{ __('modulos.preguntas.paso', ['paso' => $paso, 'total' => $total]) }}</p>
            <div class="af-preguntas__barra" aria-hidden="true"><div style="width: {{ (int) round(100 * $paso / $total) }}%"></div></div>
        @endif

        <x-filament::section>
            @if ($paso === 1)
                <fieldset class="af-opciones">
                    <legend class="af-preguntas__titulo">{{ __('modulos.preguntas.propiedades.titulo') }}</legend>
                    @foreach (['una', 'varias'] as $opcion)
                        <label class="af-opcion" wire:key="propiedades-{{ $opcion }}">
                            <input type="radio" name="propiedades" value="{{ $opcion }}" wire:model.live="propiedades">
                            <span class="af-opcion__texto"><span class="af-opcion__titulo">{{ __('modulos.preguntas.propiedades.'.$opcion) }}</span><span class="af-nota">{{ __('modulos.preguntas.propiedades.'.$opcion.'_ayuda') }}</span></span>
                        </label>
                    @endforeach
                </fieldset>
            @elseif ($paso === 2)
                <fieldset class="af-opciones">
                    <legend class="af-preguntas__titulo">{{ __('modulos.preguntas.otro_sistema.titulo') }}</legend>
                    @foreach (['no', 'principal'] as $opcion)
                        <label class="af-opcion" wire:key="otro-{{ $opcion }}">
                            <input type="radio" name="otro_sistema" value="{{ $opcion }}" wire:model.live="otroSistema">
                            <span class="af-opcion__texto"><span class="af-opcion__titulo">{{ __('modulos.preguntas.otro_sistema.'.$opcion) }}</span><span class="af-nota">{{ __('modulos.preguntas.otro_sistema.'.$opcion.'_ayuda') }}</span></span>
                        </label>
                    @endforeach
                </fieldset>
            @elseif ($paso <= $total)
                @php($seccion = [3 => 'reservas', 4 => 'operacion', 5 => 'dinero'][$paso])
                <fieldset class="af-opciones">
                    <legend class="af-preguntas__titulo">{{ __('modulos.preguntas.'.$seccion.'.titulo') }}</legend>
                    <p class="af-nota">{{ __('modulos.preguntas.'.$seccion.'.ayuda') }}</p>
                    @foreach ($this->modulosDe(...($seccion === 'dinero' ? ['dinero', 'sistema'] : [$seccion])) as $modulo)
                        <label class="af-opcion" wire:key="modulo-{{ $modulo->value }}">
                            <input type="checkbox" wire:model.live="elegidos.{{ $modulo->value }}">
                            <span class="af-opcion__texto"><span class="af-opcion__titulo">{{ $modulo->etiqueta() }}</span><span class="af-nota">{{ $modulo->descripcion() }}</span></span>
                        </label>
                    @endforeach
                </fieldset>
            @else
                <h2 class="af-preguntas__titulo">{{ __('modulos.preguntas.resumen.titulo') }}</h2>
                <p class="af-nota">{{ __('modulos.preguntas.resumen.ayuda') }}</p>
                <div class="af-preguntas__resumen">
                    <div>
                        <h3 class="af-preguntas__subtitulo">{{ __('modulos.preguntas.resumen.prendidos') }}</h3>
                        <ul class="af-lista-simple">
                            @foreach ($this->resultado() as $modulo)
                                <li>{{ $modulo->etiqueta() }}</li>
                            @endforeach
                            <li>{{ __('modulos.preguntas.resumen.base') }}</li>
                        </ul>
                    </div>
                    @if ($this->apagados() !== [])
                        <div>
                            <h3 class="af-preguntas__subtitulo">{{ __('modulos.preguntas.resumen.apagados') }}</h3>
                            <ul class="af-lista-simple">
                                @foreach ($this->apagados() as $modulo)
                                    <li>{{ $modulo->etiqueta() }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            @endif
        </x-filament::section>

        <div class="af-barra-herramientas">
            {{-- El principal primero, para que no se mueva entre un paso y otro. --}}
            @if ($paso <= $total)
                <x-filament::button wire:click="siguiente" wire:target="siguiente">{{ __('modulos.preguntas.siguiente') }}</x-filament::button>
            @else
                <x-filament::button wire:click="terminar" wire:target="terminar">{{ __('modulos.preguntas.terminar') }}</x-filament::button>
            @endif
            @if ($paso > 1)
                <x-filament::button color="gray" wire:click="atras" wire:target="atras">{{ __('modulos.preguntas.atras') }}</x-filament::button>
            @endif
            <x-filament::button color="gray" outlined wire:click="saltar" wire:target="saltar">{{ __('modulos.preguntas.saltar') }}</x-filament::button>
        </div>
    </div>
</x-filament-panels::page>
