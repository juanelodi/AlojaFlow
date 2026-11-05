{{-- AlojaFlow · SPDX-License-Identifier: AGPL-3.0-or-later --}}
<x-filament-panels::page>
    <form wire:submit="guardar" class="af-pila">
        <p class="af-prosa">{{ __('modulos.explicacion') }}</p>

        @foreach ($this->grupos() as $grupo => $modulos)
            <x-filament::section :heading="__('modulos.grupos.'.$grupo)">
                <div class="af-opciones">
                    @foreach ($modulos as $modulo)
                        @php($uso = $this->enUso($modulo))
                        <label class="af-opcion" wire:key="modulo-{{ $modulo->value }}">
                            <input type="checkbox" wire:model.live="elegidos.{{ $modulo->value }}">
                            <span class="af-opcion__texto">
                                <span class="af-opcion__titulo">
                                    {{ $modulo->etiqueta() }}
                                    @if (in_array($modulo->value, $nuevos, true))
                                        <x-filament::badge color="warning">{{ __('modulos.nuevo') }}</x-filament::badge>
                                    @endif
                                </span>
                                <span class="af-nota">{{ $modulo->descripcion() }}</span>
                                @if ($modulo->requiere() !== [])
                                    <span class="af-nota">{{ __('modulos.requiere', ['modulos' => collect($modulo->requiere())->map->etiqueta()->join(', ')]) }}</span>
                                @endif
                                @if ($uso > 0 && ! ($elegidos[$modulo->value] ?? false))
                                    <span class="af-aviso af-opcion__aviso" role="status">{{ trans_choice('modulos.en_uso.'.$modulo->value, $uso, ['n' => $uso]) }}</span>
                                @endif
                            </span>
                        </label>
                    @endforeach
                </div>
            </x-filament::section>
        @endforeach

        <p class="af-nota">{{ __('modulos.siempre') }}</p>

        <div class="af-barra-herramientas">
            <x-filament::button type="submit" wire:target="guardar">{{ __('modulos.guardar') }}</x-filament::button>
            <x-filament::button tag="a" color="gray" :href="\App\Filament\Pages\PrimerasPreguntas::getUrl()">{{ __('modulos.repetir') }}</x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
