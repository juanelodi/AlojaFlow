{{-- AlojaFlow · SPDX-License-Identifier: AGPL-3.0-or-later --}}
@use('App\Enums\TipoContacto')
@use('App\Filament\Resources\Huespedes\HuespedResource')
<x-filament-panels::page>
    <div class="af-pila">
        <p class="af-nota">{{ __('herramientas.duplicados.ayuda') }}</p>
        <div class="af-barra-herramientas">
            @include('filament.partes.selector-propiedad')
        </div>
        @php($grupos = $this->grupos())
        @if ($grupos === [])
            <p class="af-vacio">{{ __('herramientas.duplicados.vacio') }}</p>
        @endif
        @foreach ($grupos as $indice => $grupo)
            <x-filament::section wire:key="grupo-{{ $indice }}-{{ $grupo['huespedes'][0]->id }}" :heading="__('herramientas.duplicados.motivos.'.$grupo['motivo'])">
                <div class="af-tabla-contenedor" tabindex="0" role="region" aria-label="{{ __('herramientas.duplicados.motivos.'.$grupo['motivo']) }}">
                    <table class="af-tabla">
                        <caption class="af-sr">{{ __('herramientas.duplicados.motivos.'.$grupo['motivo']) }}</caption>
                        <thead>
                            <tr>
                                <th scope="col">{{ __('ui.comun.huesped') }}</th>
                                <th scope="col">{{ __('herramientas.duplicados.contacto') }}</th>
                                <th scope="col">{{ __('herramientas.duplicados.reservas') }}</th>
                                <th scope="col"><span class="af-sr">{{ __('permisos.sesiones.acciones') }}</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($grupo['huespedes'] as $huesped)
                                @php($otras = array_values(array_filter(array_map(fn ($h) => $h->id, $grupo['huespedes']), fn ($id) => $id !== $huesped->id)))
                                <tr wire:key="ficha-{{ $indice }}-{{ $huesped->id }}">
                                    <td><a class="af-enlace" href="{{ HuespedResource::getUrl('view', ['record' => $huesped]) }}">{{ $huesped->nombreCompleto() }}</a></td>
                                    <td>{{ $huesped->contactos->pluck('valor')->implode(' · ') }}</td>
                                    <td>{{ $huesped->reservas_como_titular_count }}</td>
                                    <td><x-filament::button size="sm" color="gray" wire:click="fusionar({{ $huesped->id }}, {{ json_encode($otras) }})" wire:confirm="{{ __('herramientas.duplicados.confirmar', ['nombre' => $huesped->nombreCompleto()]) }}">{{ __('herramientas.duplicados.conservar') }}</x-filament::button></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @endforeach
    </div>
</x-filament-panels::page>
