{{-- AlojaFlow · SPDX-License-Identifier: AGPL-3.0-or-later --}}
@use('App\Enums\EstadoLimpieza')
@use('App\Enums\EstadoTarea')
<x-filament-panels::page>
    <div class="af-pila">
        <div class="af-barra-herramientas">
            @include('filament.partes.selector-propiedad')
            <label class="af-campo">
                <span>{{ __('ui.hoy.fecha') }}</span>
                <x-filament::input.wrapper>
                    <x-filament::input type="date" :value="$this->fecha" wire:model.live="fecha" />
                </x-filament::input.wrapper>
            </label>
            <div class="af-grupo-botones">
                <x-filament::button color="gray" icon="heroicon-m-chevron-left" wire:click="moverDia(-1)" :label-sr-only="true" :tooltip="__('ui.comun.anterior')">{{ __('ui.comun.anterior') }}</x-filament::button>
                <x-filament::button color="gray" wire:click="irAHoy">{{ __('ui.comun.hoy') }}</x-filament::button>
                <x-filament::button color="gray" icon="heroicon-m-chevron-right" wire:click="moverDia(1)" :label-sr-only="true" :tooltip="__('ui.comun.siguiente')">{{ __('ui.comun.siguiente') }}</x-filament::button>
            </div>
            <label class="af-casilla">
                <x-filament::input.checkbox :checked="$this->soloMias" wire:model.live="soloMias" />
                <span>{{ __('limpieza.solo_mias') }}</span>
            </label>
        </div>

        <p class="af-nota">{{ \App\Support\Formato::fechaLarga($this->fechaElegida()) }}</p>

        @php($tablero = $this->tablero())
        @if ($tablero === [])
            <p class="af-vacio">{{ __('limpieza.vacio') }}</p>
        @endif

        <div class="af-limpieza">
            @foreach ($tablero as $fila)
                <section @class(['af-unidad-tarjeta', 'af-unidad-tarjeta--'.$fila['limpieza']->value, 'af-unidad-tarjeta--fuera' => $fila['fuera']]) wire:key="unidad-{{ $fila['unidad']->id }}">
                    <header class="af-unidad-tarjeta__cabecera">
                        <h2 class="af-unidad-tarjeta__nombre">{{ $fila['unidad']->nombre }}</h2>
                        <span class="af-pastilla af-pastilla--{{ $fila['limpieza']->value }}">{{ $fila['limpieza']->etiqueta() }}</span>
                    </header>
                    <p class="af-nota">
                        @if ($fila['sale']) {{ __('limpieza.sale_hoy') }} @endif
                        @if ($fila['llega']) {{ __('limpieza.llega_hoy') }} @endif
                        @if ($fila['alojada']) {{ __('limpieza.alojada') }} @endif
                        @unless ($fila['sale'] || $fila['llega'] || $fila['alojada']) {{ __('limpieza.libre') }} @endunless
                    </p>
                    @if ($fila['fuera'])
                        <p class="af-aviso af-aviso--error">{{ __('limpieza.fuera_de_servicio') }}@if ($fila['nota']): {{ $fila['nota'] }}@endif</p>
                    @endif

                    @foreach ($fila['tareas'] as $tarea)
                        <div class="af-tarea" wire:key="tarea-{{ $tarea->id }}">
                            <div>
                                <div class="af-tarea__titulo">@if ($tarea->prioridad > 0)<span class="af-pastilla af-pastilla--sucia">{{ __('limpieza.urgente_corto') }}</span> @endif{{ $tarea->titulo }}</div>
                                @if ($tarea->descripcion)<div class="af-nota">{{ $tarea->descripcion }}</div>@endif
                                @if ($tarea->puntos)
                                    <ul class="af-puntos" aria-label="{{ __('equipo.listas.puntos') }}">
                                        @foreach ($tarea->puntos as $indice => $punto)
                                            <li wire:key="tarea-{{ $tarea->id }}-punto-{{ $indice }}">
                                                <label class="af-casilla">
                                                    <input type="checkbox" @checked($punto['hecho'] ?? false) wire:click="marcarPunto({{ $tarea->id }}, {{ $indice }})">
                                                    <span>{{ $punto['texto'] ?? '' }}</span>
                                                </label>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                                @foreach ($tarea->archivos as $foto)
                                    <a class="af-enlace" href="{{ route('archivos.ver', $foto) }}" target="_blank" rel="noopener">{{ __('limpieza.ver_foto', ['n' => $loop->iteration]) }}</a>
                                @endforeach
                            </div>
                            <div class="af-grupo-botones">
                                @if ($tarea->estado === EstadoTarea::Pendiente)
                                    <x-filament::button color="gray" wire:click="empezar({{ $tarea->id }})">{{ __('limpieza.empezar') }}</x-filament::button>
                                @endif
                                <x-filament::button color="success" icon="heroicon-m-check" wire:click="terminar({{ $tarea->id }})">{{ __('limpieza.terminar') }}</x-filament::button>
                            </div>
                        </div>
                    @endforeach

                    <div class="af-grupo-botones">
                        @if ($fila['limpieza'] !== EstadoLimpieza::Limpia)
                            <x-filament::button color="success" outlined wire:click="marcar({{ $fila['unidad']->id }}, 'limpia')">{{ __('limpieza.marcar_limpia') }}</x-filament::button>
                        @endif
                        @if ($fila['limpieza'] !== EstadoLimpieza::Sucia)
                            <x-filament::button color="gray" outlined wire:click="marcar({{ $fila['unidad']->id }}, 'sucia')">{{ __('limpieza.marcar_sucia') }}</x-filament::button>
                        @endif
                        {{ ($this->incidenciaAction)(['unidad' => $fila['unidad']->id]) }}
                        <x-filament::button color="gray" outlined size="sm" wire:click="alternarServicio({{ $fila['unidad']->id }})">{{ $fila['fuera'] ? __('limpieza.volver_servicio') : __('limpieza.poner_fuera') }}</x-filament::button>
                    </div>
                </section>
            @endforeach
        </div>
    </div>
</x-filament-panels::page>
