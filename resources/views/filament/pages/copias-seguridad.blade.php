{{-- AlojaFlow · Copyright (C) 2026 Juan Galeana Durán · SPDX-License-Identifier: FSL-1.1-ALv2 --}}
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
            @php($externa = app(\App\Copias\CopiaExterna::class))
            @php($ultimaExterna = $externa->ultima())
            @if ($externa->configurada())
                <p class="af-nota" style="margin-top: .75rem">{{ __('copias.externa.estado', ['destino' => $externa->destino($externa->configuracion())]) }}</p>
                @if (isset($ultimaExterna['en']))
                    <p class="{{ ($ultimaExterna['ok'] ?? false) ? 'af-nota' : 'af-aviso' }}" style="margin-top: .5rem">
                        {{ ($ultimaExterna['ok'] ?? false) ? __('copias.externa.ultima_ok', ['cuando' => Formato::momento(\Illuminate\Support\Carbon::parse($ultimaExterna['en']))]) : __('copias.externa.no_subida', ['motivo' => (string) ($ultimaExterna['error'] ?? '')]) }}
                    </p>
                @endif
            @else
                <p class="af-aviso" style="margin-top: .75rem">{{ __('copias.aviso_fuera') }} {{ __('copias.externa.sugerencia') }}</p>
            @endif
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
                                    <td data-orden="{{ $copia['creada_en']?->format('Y-m-d H:i:s') }}">{{ Formato::momento($copia['creada_en']) }}
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
