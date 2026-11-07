{{-- AlojaFlow · SPDX-License-Identifier: AGPL-3.0-or-later --}}
@use('App\Filament\Resources\Reservas\ReservaResource')
@use('App\Support\Formato')
<x-filament-panels::page>
    <div class="af-pila">
        <p class="af-nota">{{ __('estancias.pagina.ayuda') }}</p>
        <div class="af-barra-herramientas">
            @include('filament.partes.selector-propiedad')
        </div>

        @php($sinRecordatorios = $this->sinRecordatorios())
        @if ($sinRecordatorios !== [])
            <x-filament::section :heading="__('estancias.pagina.recordatorios')">
                <p>{{ __('estancias.pagina.recordatorios_ayuda') }}</p>
                <x-filament::button color="gray" wire:click="prenderRecordatorios" style="margin-top: .75rem">{{ __('estancias.pagina.recordatorios_prender') }}</x-filament::button>
            </x-filament::section>
        @endif

        @php($resumen = $this->resumen())
        @foreach (['vencidas' => __('estancias.pagina.vencidas'), 'proximas' => __('estancias.pagina.proximas', ['dias' => $this->diasProximas()])] as $clave => $titulo)
            <x-filament::section :heading="$titulo">
                @if ($resumen[$clave] === [])
                    <p class="af-vacio">{{ __('estancias.pagina.sin_'.$clave) }}</p>
                @else
                    <div class="af-tabla-contenedor" tabindex="0" role="region" aria-label="{{ $titulo }}">
                        <table class="af-tabla">
                            <caption class="af-sr">{{ $titulo }}</caption>
                            <thead>
                                <tr>
                                    <th scope="col">{{ __('informes.columnas.reserva') }}</th>
                                    <th scope="col">{{ __('ui.comun.huesped') }}</th>
                                    <th scope="col">{{ __('estancias.publico.periodo') }}</th>
                                    <th scope="col">{{ __('estancias.cuotas.vence') }}</th>
                                    <th scope="col">{{ __('estancias.pagina.falta') }}</th>
                                    <th scope="col"><span class="af-sr">{{ __('permisos.sesiones.acciones') }}</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($resumen[$clave] as $fila)
                                    <tr wire:key="cuota-{{ $fila['cuota']->id }}" x-data>
                                        <td><a class="af-enlace" href="{{ ReservaResource::getUrl('view', ['record' => $fila['reserva']]) }}">{{ $fila['reserva']->numero }}</a></td>
                                        <td>{{ $fila['reserva']->titular?->nombreCompleto() }}</td>
                                        <td class="af-sin-cortar">{{ $fila['cuota']->periodo() }}</td>
                                        <td @class(['af-vencido' => $clave === 'vencidas']) data-orden="{{ $fila['cuota']->vence_en?->format('Y-m-d') }}">{{ Formato::fecha($fila['cuota']->vence_en) }}</td>
                                        <td>{{ Formato::dinero($fila['falta'], $fila['reserva']->moneda) }}</td>
                                        <td><x-filament::button tag="a" size="sm" color="gray" icon="heroicon-m-chat-bubble-left-ellipsis" :href="$fila['whatsapp']" target="_blank" rel="noopener" x-on:click="$wire.anotarRecordatorio({{ $fila['cuota']->id }})">{{ __('operacion.mensajes.whatsapp') }}</x-filament::button></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-filament::section>
        @endforeach
    </div>
</x-filament-panels::page>
