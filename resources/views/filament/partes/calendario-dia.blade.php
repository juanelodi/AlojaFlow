{{-- AlojaFlow · SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Un día del calendario, unidad por unidad: en el teléfono no hay barras que atinar ni arrastre que choque con desplazar. --}}
@use('App\Enums\TipoReserva')
@use('App\Seguridad\Acceso')
@use('App\Seguridad\Limites')
@use('App\Servicios\Calendario')
@use('App\Support\Formato')
@php
    $delDia = $this->datosDelDia();
    $elegido = $this->diaElegido();
    $usuario = Acceso::usuarioActual();
    $conDatos = Acceso::propiedades($usuario, Acceso::DATOS);
@endphp
<div class="af-pila" style="gap: .75rem">
    <div>
        <h2 class="af-dia__fecha">{{ Formato::fechaLarga($elegido) }}</h2>
        @if ($this->haySubordinadas())
            <p class="af-nota">{{ __('ui.comun.solo_consulta') }}</p>
        @endif
        @if ($delDia['filas'] !== [])
            <dl class="af-cifras af-dia__cifras">
                <div class="af-cifra"><dt>{{ __('ui.calendario.libres') }}</dt><dd>{{ $delDia['libres'] }}</dd></div>
                <div class="af-cifra"><dt>{{ __('ui.calendario.ocupadas') }}</dt><dd>{{ $delDia['ocupadas'] }}</dd></div>
                <div class="af-cifra"><dt>{{ __('ui.hoy.llegan') }}</dt><dd>{{ $delDia['llegan'] }}</dd></div>
                <div class="af-cifra"><dt>{{ __('ui.hoy.salen') }}</dt><dd>{{ $delDia['salen'] }}</dd></div>
            </dl>
        @endif
    </div>

    @if ($delDia['filas'] === [])
        <x-filament::empty-state :heading="__('ui.calendario.sin_unidades')" icon="heroicon-o-calendar-days" />
    @else
        <ul class="af-dia-lista">
            @foreach ($delDia['filas'] as $fila)
                @php($unidad = $fila['unidad'])
                @php($gestionable = in_array($unidad->propiedad_id, $gestionables, true))
                <li wire:key="dia-unidad-{{ $unidad->id }}" @class(['af-dia-unidad', 'af-dia-unidad--libre' => $fila['libre']])>
                    <div class="af-dia-unidad__cabecera">
                        <span class="af-dia-unidad__nombre">
                            {{ $unidad->nombre }}
                            @if ($variasPropiedades)<span class="af-secundario">{{ $unidad->propiedad->nombre }}</span>@endif
                        </span>
                        @if ($fila['libre'])
                            <span class="af-dia-unidad__libre">
                                {{-- Si alguien sale ese día, «libre» a secas parecería decir que ya se fue. --}}
                                <x-filament::badge color="success">{{ __($fila['reservas'] === [] ? 'ui.calendario.libre' : 'ui.calendario.libre_noche') }}</x-filament::badge>
                                @if ($gestionable)
                                    <x-filament::button tag="a" color="gray" icon="heroicon-m-plus" :label-sr-only="true"
                                        :href="$urlNueva.'?unidad='.$unidad->id.'&llegada='.$elegido->format('Y-m-d').'&salida='.$elegido->addDay()->format('Y-m-d')"
                                        :tooltip="__('ui.calendario.nueva_en', ['unidad' => $unidad->nombre, 'fecha' => Formato::fechaCorta($elegido)])">
                                        {{ __('ui.calendario.nueva_en', ['unidad' => $unidad->nombre, 'fecha' => Formato::fechaCorta($elegido)]) }}
                                    </x-filament::button>
                                @endif
                            </span>
                        @endif
                    </div>
                    @foreach ($fila['reservas'] as $renglon)
                        @php($reserva = $renglon['reserva'])
                        @php($ajena = ! Limites::puedeVer($usuario, $reserva))
                        <x-tarjeta-reserva
                            wire:key="dia-reserva-{{ $reserva->id }}"
                            :reserva="$reserva"
                            :titulo="Calendario::textoDe($reserva, $usuario)"
                            :ver-datos="! $ajena && $reserva->tipo === TipoReserva::Reserva && in_array($reserva->propiedad_id, $conDatos, true)"
                            :mostrar-unidad="false"
                            :ajena="$ajena"
                            :momento="__('ui.calendario.'.$renglon['momento'])"
                        />
                    @endforeach
                    @foreach ($fila['externos'] as $externo)
                        <p class="af-dia-externo" wire:key="dia-externo-{{ $externo->id }}">
                            <strong>{{ __('ical.calendario_externo', ['fuente' => $externo->fuente->nombre]) }}</strong>
                            <span class="af-secundario">{{ Formato::fechaCorta($externo->llegada) }} → {{ Formato::fechaCorta($externo->salida) }}</span>
                        </p>
                    @endforeach
                </li>
            @endforeach
        </ul>
    @endif
</div>
