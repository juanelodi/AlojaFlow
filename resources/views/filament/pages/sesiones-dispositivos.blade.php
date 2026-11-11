{{-- AlojaFlow · Copyright (C) 2026 Juan Galeana Durán · SPDX-License-Identifier: FSL-1.1-ALv2 --}}
@use('App\Support\Formato')
<x-filament-panels::page>
    <div class="af-pila">
        <x-filament::section :heading="__('permisos.sesiones.abiertas')">
            @php($sesiones = $this->sesiones())
            @if ($sesiones === [])
                <p class="af-nota">{{ __('permisos.sesiones.sin_sesiones') }}</p>
            @else
                <div class="af-tabla-contenedor" tabindex="0" role="region" aria-label="{{ __('permisos.sesiones.abiertas') }}">
                    <table class="af-tabla">
                        <caption class="af-sr">{{ __('permisos.sesiones.abiertas') }}</caption>
                        <thead>
                            <tr>
                                @if ($this->veTodos())<th scope="col">{{ __('permisos.sesiones.usuario') }}</th>@endif
                                <th scope="col">{{ __('permisos.sesiones.dispositivo') }}</th>
                                <th scope="col">IP</th>
                                <th scope="col">{{ __('permisos.sesiones.ultimo_uso') }}</th>
                                <th scope="col"><span class="af-sr">{{ __('permisos.sesiones.acciones') }}</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($sesiones as $s)
                                <tr wire:key="sesion-{{ $s['clave'] }}">
                                    @if ($this->veTodos())<td>{{ $s['usuario'] }}</td>@endif
                                    <td>{{ $s['dispositivo'] }}</td>
                                    <td>{{ $s['ip'] }}</td>
                                    <td data-orden="{{ $s['ultimo']?->format('Y-m-d H:i:s') }}">{{ Formato::momento($s['ultimo']) }}</td>
                                    <td>
                                        @if ($s['actual'])
                                            <x-filament::badge color="success">{{ __('permisos.sesiones.esta') }}</x-filament::badge>
                                        @else
                                            <x-filament::button size="sm" color="gray" wire:click="cerrarSesion('{{ $s['clave'] }}')">{{ __('permisos.sesiones.cerrar') }}</x-filament::button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>

        <x-filament::section :heading="__('permisos.sesiones.recordados')" :description="__('permisos.sesiones.recordados_ayuda')">
            @php($dispositivos = $this->dispositivos())
            @if ($dispositivos === [])
                <p class="af-nota">{{ __('permisos.sesiones.sin_dispositivos') }}</p>
            @else
                <div class="af-tabla-contenedor" tabindex="0" role="region" aria-label="{{ __('permisos.sesiones.recordados') }}">
                    <table class="af-tabla">
                        <caption class="af-sr">{{ __('permisos.sesiones.recordados') }}</caption>
                        <thead>
                            <tr>
                                @if ($this->veTodos())<th scope="col">{{ __('permisos.sesiones.usuario') }}</th>@endif
                                <th scope="col">{{ __('permisos.sesiones.dispositivo') }}</th>
                                <th scope="col">IP</th>
                                <th scope="col">{{ __('permisos.sesiones.ultimo_uso') }}</th>
                                <th scope="col">{{ __('permisos.sesiones.caduca') }}</th>
                                <th scope="col"><span class="af-sr">{{ __('permisos.sesiones.acciones') }}</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($dispositivos as $d)
                                <tr wire:key="dispositivo-{{ $d['id'] }}">
                                    @if ($this->veTodos())<td>{{ $d['usuario'] }}</td>@endif
                                    <td>{{ $d['dispositivo'] }}</td>
                                    <td>{{ $d['ip'] }}</td>
                                    <td data-orden="{{ $d['ultimo']?->format('Y-m-d H:i:s') }}">{{ Formato::momento($d['ultimo']) }}</td>
                                    <td data-orden="{{ $d['expira']?->format('Y-m-d') }}">{{ Formato::fecha($d['expira']) }}</td>
                                    <td><x-filament::button size="sm" color="gray" wire:click="olvidarDispositivo({{ $d['id'] }})">{{ __('permisos.sesiones.olvidar') }}</x-filament::button></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>

        <div><x-filament::button color="gray" wire:click="cerrarLasDemas" wire:target="cerrarLasDemas">{{ __('permisos.sesiones.cerrar_demas') }}</x-filament::button></div>

        @if ($this->veTodos())
            <x-filament::section :heading="__('permisos.sesiones.dias')">
                <form wire:submit="guardarDias" class="af-barra-herramientas">
                    <label class="af-campo">
                        <span>{{ __('permisos.sesiones.dias_ayuda') }}</span>
                        <x-filament::input.wrapper>
                            <x-filament::input type="number" min="0" max="365" wire:model="diasRecordar" />
                        </x-filament::input.wrapper>
                    </label>
                    <x-filament::button type="submit" wire:target="guardarDias">{{ __('permisos.sesiones.guardar') }}</x-filament::button>
                </form>
                @error('diasRecordar')<p class="af-aviso af-aviso--error" role="alert" style="margin-top: .5rem">{{ $message }}</p>@enderror
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
