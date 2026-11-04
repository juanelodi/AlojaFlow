{{-- AlojaFlow · SPDX-License-Identifier: AGPL-3.0-or-later --}}
@use('App\Support\Formato')
<x-filament-panels::page>
    @php
        $datos = $this->datos();
        $dias = $datos['dias'];
        $hoy = $this->hoy()->format('Y-m-d');
        $propiedadAnterior = null;
        $variasPropiedades = count($this->propiedadesElegidas()) > 1;
        $gestionables = $this->propiedadesGestionables();
    @endphp
    <div class="af-pila">
        <div class="af-barra-herramientas">
            @include('filament.partes.selector-propiedad')
            <label class="af-campo">
                <span>{{ __('ui.calendario.mes') }}</span>
                <x-filament::input.wrapper>
                    <x-filament::input type="month" wire:model.live="mes" />
                </x-filament::input.wrapper>
            </label>
            <div class="af-grupo-botones">
                <x-filament::button color="gray" icon="heroicon-m-chevron-left" wire:click="moverMes(-1)" :label-sr-only="true" :tooltip="__('ui.comun.anterior')">
                    {{ __('ui.comun.anterior') }}
                </x-filament::button>
                <x-filament::button color="gray" wire:click="irAEsteMes">{{ __('ui.calendario.este_mes') }}</x-filament::button>
                <x-filament::button color="gray" icon="heroicon-m-chevron-right" wire:click="moverMes(1)" :label-sr-only="true" :tooltip="__('ui.comun.siguiente')">
                    {{ __('ui.comun.siguiente') }}
                </x-filament::button>
            </div>
        </div>

        <div class="af-leyenda" aria-label="{{ __('ui.calendario.leyenda') }}">
            <span><i class="af-barra--confirmada"></i>{{ __('ui.calendario.confirmada') }}</span>
            <span><i class="af-barra--sin-confirmar"></i>{{ __('ui.calendario.sin_confirmar') }}</span>
            <span><i class="af-barra--en-casa"></i>{{ __('ui.calendario.en_casa') }}</span>
            <span><i class="af-barra--salio"></i>{{ __('ui.calendario.salio') }}</span>
            <span><i class="af-barra--bloqueo"></i>{{ __('ui.calendario.bloqueo') }}</span>
            @if ($this->haySubordinadas())
                <span class="af-nota">{{ __('ui.comun.solo_consulta') }}</span>
            @endif
            @if ($gestionables !== [])
                <span class="af-nota">{{ __('ui.calendario.ayuda_nueva') }} {{ __('ui.calendario.ayuda_mover') }}</span>
            @endif
        </div>

        @if ($datos['filas'] === [])
            <x-filament::empty-state :heading="__('ui.calendario.sin_unidades')" icon="heroicon-o-calendar-days" />
        @else
            <div class="af-calendario" x-data="{ moviendo: null }" :class="moviendo && 'af-arrastrando'" style="--af-dias: {{ count($dias) }}" role="region" aria-label="{{ __('ui.calendario.titulo') }} {{ Formato::fecha($this->inicioMes(), 'MMMM YYYY') }}" tabindex="0">
                <div class="af-cal-fila af-cal-fila--cabecera">
                    <div class="af-cal-unidad">{{ Formato::fecha($this->inicioMes(), 'MMMM YYYY') }}</div>
                    @foreach ($dias as $i => $dia)
                        <div @class(['af-cal-cabecera-dia', 'af-cal-dia--finde' => $dia->isWeekend(), 'af-cal-dia--hoy' => $dia->format('Y-m-d') === $hoy]) style="grid-column: {{ $i + 2 }}">
                            {{ $dia->locale(app()->getLocale())->isoFormat('dd') }}<strong>{{ $dia->day }}</strong>
                        </div>
                    @endforeach
                </div>

                @foreach ($datos['filas'] as $fila)
                    @php($unidad = $fila['unidad'])
                    @if ($variasPropiedades && $propiedadAnterior !== $unidad->propiedad_id)
                        @php($propiedadAnterior = $unidad->propiedad_id)
                        <div class="af-cal-fila af-cal-fila--grupo"><div>{{ $unidad->propiedad->nombre }}</div></div>
                    @endif
                    <div class="af-cal-fila" wire:key="unidad-{{ $unidad->id }}">
                        <div class="af-cal-unidad">
                            {{ $unidad->nombre }}
                            @if ($unidad->capacidad > 0)<span class="af-secundario">{{ $unidad->capacidad }} <span class="af-sr">{{ __('ui.comun.personas') }}</span><span aria-hidden="true">👤</span></span>@endif
                        </div>
                        @foreach ($dias as $i => $dia)
                            @if (in_array($unidad->propiedad_id, $gestionables, true))
                                {{-- Las barras van encima, así que solo se puede pulsar en lo que está libre. --}}
                                <a href="{{ \App\Filament\Resources\Reservas\ReservaResource::getUrl('create', ['unidad' => $unidad->id, 'llegada' => $dia->format('Y-m-d'), 'salida' => $dia->addDay()->format('Y-m-d')]) }}"
                                   draggable="false"
                                   x-on:dragover.prevent=""
                                   x-on:drop.prevent="if (moviendo) { $wire.moverReserva(moviendo, {{ $unidad->id }}, '{{ $dia->format('Y-m-d') }}'); moviendo = null; }"
                                   @class(['af-cal-dia', 'af-cal-dia--libre', 'af-cal-dia--finde' => $dia->isWeekend(), 'af-cal-dia--hoy' => $dia->format('Y-m-d') === $hoy])
                                   style="grid-column: {{ $i + 2 }}"
                                   title="{{ __('ui.calendario.nueva_en', ['unidad' => $unidad->nombre, 'fecha' => Formato::fechaCorta($dia)]) }}"
                                   aria-label="{{ __('ui.calendario.nueva_en', ['unidad' => $unidad->nombre, 'fecha' => Formato::fechaCorta($dia)]) }}"></a>
                            @else
                                <div @class(['af-cal-dia', 'af-cal-dia--finde' => $dia->isWeekend(), 'af-cal-dia--hoy' => $dia->format('Y-m-d') === $hoy]) style="grid-column: {{ $i + 2 }}"></div>
                            @endif
                        @endforeach
                        @foreach ($fila['externos'] as $externo)
                            <div @class(['af-barra', 'af-barra--externo', 'af-barra--sigue-antes' => $externo['sigue_antes'], 'af-barra--sigue-despues' => $externo['sigue_despues']])
                                 style="grid-column: {{ $externo['columna'] + 1 }} / span {{ $externo['ancho'] }}"
                                 title="{{ __('ical.calendario_externo', ['fuente' => $externo['fuente']]) }}">{{ $externo['fuente'] }}</div>
                        @endforeach
                        @foreach ($fila['barras'] as $barra)
                            @php($reserva = $barra['reserva'])
                            @php($texto = $reserva->tipo === \App\Enums\TipoReserva::Bloqueo ? ($reserva->origen_texto ?? __('ui.calendario.bloqueo')) : ($reserva->titular?->nombreCompleto() ?? $reserva->numero))
                            {{-- Sin permiso de datos, la barra no lleva a la ficha: un enlace sin destino no se puede seguir. --}}
                            <a @if (\App\Seguridad\Acceso::puede(\App\Seguridad\Acceso::usuarioActual(), \App\Seguridad\Acceso::DATOS, $reserva->propiedad_id)) href="{{ \App\Filament\Resources\Reservas\ReservaResource::getUrl('view', ['record' => $reserva]) }}" @endif
                               @if (! $reserva->sincronizada && in_array($reserva->propiedad_id, $gestionables, true) && $reserva->salida_registrada_en === null)
                                   draggable="true"
                                   x-on:dragstart="$event.dataTransfer.effectAllowed = 'move'; $event.dataTransfer.setData('text/plain', '{{ $reserva->id }}'); setTimeout(() => moviendo = {{ $reserva->id }}, 0)"
                                   x-on:dragend="moviendo = null"
                               @endif
                               @class(['af-barra', $barra['clase'], 'af-barra--marcada' => $barra['color'], 'af-barra--sigue-antes' => $barra['sigue_antes'], 'af-barra--sigue-despues' => $barra['sigue_despues']])
                               style="grid-column: {{ $barra['columna'] + 1 }} / span {{ $barra['ancho'] }}@if ($barra['color']); --af-marca: {{ $barra['color'] }}@endif"
                               title="{{ __('ui.calendario.detalle', ['huesped' => $texto, 'noches' => $reserva->noches, 'llegada' => Formato::fechaCorta($reserva->llegada), 'salida' => Formato::fechaCorta($reserva->salida)]) }}"
                            >{{ $texto }}</a>
                        @endforeach
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-filament-panels::page>
