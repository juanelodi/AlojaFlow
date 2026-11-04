{{-- AlojaFlow · SPDX-License-Identifier: AGPL-3.0-or-later --}}
@use('App\Support\Formato')
@use('Illuminate\Support\Number')
<x-filament-panels::page>
    @php($copias = $this->copias())
    <div class="af-pila">
        <x-filament::section>
            <p class="af-prosa">{{ __('copias.explicacion') }}</p>
            <p class="af-nota" style="margin-top: .5rem">
                @if ($this->automaticas())
                    {{ __('copias.estado_automaticas', ['cuantas' => $this->conservar()]) }}
                @else
                    {{ __('copias.estado_manuales') }}
                @endif
            </p>
            <p class="af-aviso" style="margin-top: .75rem">{{ __('copias.aviso_fuera') }}</p>
        </x-filament::section>

        <x-filament::section :heading="__('copias.lista')">
            @if ($copias === [])
                <p class="af-nota">{{ __('copias.vacio') }}</p>
            @else
                <div class="af-tabla-contenedor" tabindex="0" role="region" aria-label="{{ __('copias.lista') }}">
                    <table class="af-tabla">
                        <caption class="af-sr">{{ __('copias.lista') }}</caption>
                        <thead>
                            <tr>
                                <th scope="col">{{ __('copias.fecha') }}</th>
                                <th scope="col">{{ __('copias.origen') }}</th>
                                <th scope="col">{{ __('copias.version') }}</th>
                                <th scope="col" class="af-num">{{ __('copias.tamano') }}</th>
                                <th scope="col"><span class="af-sr">{{ __('copias.acciones') }}</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($copias as $copia)
                                <tr wire:key="copia-{{ $copia['nombre'] }}">
                                    <td>
                                        {{ Formato::momento($copia['creada_en']) }}
                                        <span class="af-secundario">{{ $copia['nombre'] }}</span>
                                        @unless ($copia['misma_clave'])
                                            <span class="af-secundario" style="color: var(--warning-700)">{{ __('copias.otra_clave') }}</span>
                                        @endunless
                                    </td>
                                    <td>{{ __('copias.origenes.'.$copia['origen']) }}</td>
                                    <td>{{ $copia['version'] }}</td>
                                    <td class="af-num">{{ Number::fileSize($copia['tamano'], 1) }}</td>
                                    <td>
                                        <div class="af-grupo-botones">
                                            {{ ($this->descargarAction)(['nombre' => $copia['nombre']]) }}
                                            {{ ($this->restaurarAction)(['nombre' => $copia['nombre']]) }}
                                            {{ ($this->eliminarAction)(['nombre' => $copia['nombre']]) }}
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>
    </div>
    <x-filament-actions::modals />
</x-filament-panels::page>
