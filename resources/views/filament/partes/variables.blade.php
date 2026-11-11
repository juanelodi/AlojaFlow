{{-- AlojaFlow · Copyright (C) 2026 Juan Galeana Durán · SPDX-License-Identifier: FSL-1.1-ALv2 --}}
{{-- La lista de variables junto al texto: se toca una y queda donde está el cursor (resources/js/alojaflow.js). --}}
<aside class="af-variables" data-af-variables data-af-insertada="{{ __('variables.insertada') }}" aria-label="{{ __('variables.titulo') }}" wire:key="af-variables-{{ md5(json_encode($grupos)) }}">
    <div wire:ignore>
        <h3 class="af-variables__titulo">{{ __('variables.titulo') }}</h3>
        <p class="af-nota">{{ __('variables.ayuda') }}</p>
        <label class="af-campo af-variables__buscar">
            <span class="fi-sr-only">{{ __('variables.buscar') }}</span>
            <x-filament::input.wrapper prefix-icon="heroicon-m-magnifying-glass">
                <x-filament::input type="search" data-af-variables-buscar placeholder="{{ __('variables.buscar') }}" autocomplete="off" />
            </x-filament::input.wrapper>
        </label>
        <p class="af-nota af-variables__estado" role="status" data-af-variables-estado></p>
        @foreach ($grupos as $i => $grupo)
            <details class="af-variables__grupo" @if ($i < 2) open @endif>
                <summary>{{ $grupo['titulo'] }}</summary>
                @if ($grupo['nota'])
                    <p class="af-nota">{{ $grupo['nota'] }}</p>
                @endif
                <ul>
                    @foreach ($grupo['variables'] as $variable)
                        <li>
                            <button type="button" class="af-variable" data-af-variable="{{ '{'.$variable['nombre'].'}' }}">
                                <code>{{ '{'.$variable['nombre'].'}' }}</code>
                                <span>{{ $variable['descripcion'] }}</span>
                            </button>
                        </li>
                    @endforeach
                </ul>
            </details>
        @endforeach
    </div>
</aside>
