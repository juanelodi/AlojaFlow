{{-- AlojaFlow · Copyright (C) 2026 Juan Galeana Durán · SPDX-License-Identifier: FSL-1.1-ALv2 --}}
<x-filament-panels::page>
    <div class="af-pila">
        <x-filament::section>
            <p class="af-prosa">{{ __('soporte.explicacion') }}</p>
            @php($hasta = $this->permitidoHasta())
            @if ($hasta === null)
                <p class="af-prosa"><x-filament::badge color="gray">{{ __('soporte.sin_permiso_estado') }}</x-filament::badge></p>
            @else
                <p class="af-prosa"><x-filament::badge color="warning">{{ __('soporte.permitido_estado', ['hasta' => $hasta]) }}</x-filament::badge></p>
            @endif
        </x-filament::section>

        <x-filament::section :heading="__('soporte.accesos')">
            @php($accesos = $this->accesos())
            @if ($accesos->isEmpty())
                <p class="af-nota">{{ __('soporte.sin_accesos') }}</p>
            @else
                <div class="af-tabla-contenedor" tabindex="0" role="region" aria-label="{{ __('soporte.accesos') }}">
                    <table class="af-tabla">
                        <caption class="af-sr">{{ __('soporte.accesos') }}</caption>
                        <thead>
                            <tr>
                                <th scope="col">{{ __('soporte.quien') }}</th>
                                <th scope="col">{{ __('soporte.cuando') }}</th>
                                <th scope="col">{{ __('soporte.hasta') }}</th>
                                <th scope="col">{{ __('soporte.motivo') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($accesos as $acceso)
                                <tr>
                                    <td>
                                        {{ $acceso['nombre'] }}
                                        @if ($acceso['dentro'])
                                            <x-filament::badge color="warning">{{ __('soporte.dentro') }}</x-filament::badge>
                                        @endif
                                    </td>
                                    <td data-orden="{{ $acceso['orden'] }}">{{ $acceso['cuando'] }}</td>
                                    <td>{{ $acceso['hasta'] }}</td>
                                    <td>{{ $acceso['emergencia'] === null ? __('soporte.con_permiso') : __('soporte.emergencia', ['motivo' => $acceso['emergencia']]) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
