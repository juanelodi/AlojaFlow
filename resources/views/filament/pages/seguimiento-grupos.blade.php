{{-- AlojaFlow · Copyright (C) 2026 Juan Galeana Durán · SPDX-License-Identifier: FSL-1.1-ALv2 --}}
@use('App\Filament\Resources\Grupos\GrupoResource')
@use('App\Support\Formato')
<x-filament-panels::page>
    <div class="af-pila">
        <div class="af-barra-herramientas">
            @include('filament.partes.selector-propiedad')
        </div>
        @php($resumen = $this->resumen())

        <x-filament::section :heading="__('grupos.seguimiento.comprobantes')">
            @if ($resumen['comprobantes']->isEmpty())
                <p class="af-nota">{{ __('grupos.seguimiento.nada') }}</p>
            @else
                <ul class="af-lista-simple">
                    @foreach ($resumen['comprobantes'] as $comprobante)
                        <li wire:key="comprobante-{{ $comprobante->id }}">
                            <a class="af-enlace" href="{{ GrupoResource::getUrl('view', ['record' => $comprobante->grupo]) }}">{{ $comprobante->grupo->nombre }}</a>
                            · {{ Formato::fecha($comprobante->fecha) }} · {{ Formato::dinero($comprobante->importe, $comprobante->moneda) }}
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-filament::section>

        <x-filament::section :heading="__('grupos.seguimiento.cuotas')">
            @if ($resumen['cuotas'] === [])
                <p class="af-nota">{{ __('grupos.seguimiento.nada') }}</p>
            @else
                <div class="af-tabla-contenedor" tabindex="0" role="region" aria-label="{{ __('grupos.seguimiento.cuotas') }}">
                    <table class="af-tabla">
                        <caption class="af-sr">{{ __('grupos.seguimiento.cuotas') }}</caption>
                        <thead>
                            <tr>
                                <th scope="col">{{ __('ui.grupos.modelo') }}</th>
                                <th scope="col">{{ __('grupos.cuotas.concepto') }}</th>
                                <th scope="col">{{ __('grupos.cuotas.vence') }}</th>
                                <th scope="col">{{ __('ui.comun.pendiente') }}</th>
                                <th scope="col">{{ __('ui.comun.estado') }}</th>
                                <th scope="col"><span class="af-sr">{{ __('permisos.sesiones.acciones') }}</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($resumen['cuotas'] as $fila)
                                <tr wire:key="cuota-{{ $fila['cuota']->id }}">
                                    <td><a class="af-enlace" href="{{ GrupoResource::getUrl('view', ['record' => $fila['grupo']]) }}">{{ $fila['grupo']->nombre }}</a></td>
                                    <td>{{ $fila['cuota']->concepto }}</td>
                                    <td data-orden="{{ $fila['cuota']->vence_en?->format('Y-m-d') }}">{{ Formato::fecha($fila['cuota']->vence_en) }}</td>
                                    <td>{{ Formato::dinero($fila['cuota']->importe - $fila['pagado'], $fila['grupo']->propiedad->moneda) }}</td>
                                    <td>{{ __('grupos.cuotas.estados.'.$fila['estado']) }}</td>
                                    <td>
                                        @if ($fila['whatsapp'])
                                            <a class="af-enlace" href="{{ $fila['whatsapp'] }}" target="_blank" rel="noopener">{{ __('operacion.mensajes.whatsapp') }}</a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>

        <x-filament::section :heading="__('grupos.seguimiento.listas')">
            @if ($resumen['listas'] === [])
                <p class="af-nota">{{ __('grupos.seguimiento.nada') }}</p>
            @else
                <ul class="af-lista-simple">
                    @foreach ($resumen['listas'] as $fila)
                        <li wire:key="lista-{{ $fila['grupo']->id }}">
                            <a class="af-enlace" href="{{ GrupoResource::getUrl('view', ['record' => $fila['grupo']]) }}">{{ $fila['grupo']->nombre }}</a>
                            · {{ trans_choice('grupos.seguimiento.faltan', $fila['faltan'], ['n' => $fila['faltan']]) }}
                            @if ($fila['grupo']->fecha_limite_lista)
                                · {{ __('grupos.seguimiento.limite', ['fecha' => Formato::fecha($fila['grupo']->fecha_limite_lista)]) }}
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-filament::section>

        <x-filament::section :heading="__('grupos.seguimiento.llegadas')">
            @if ($resumen['llegadas'] === [])
                <p class="af-nota">{{ __('grupos.seguimiento.nada') }}</p>
            @else
                <ul class="af-lista-simple">
                    @foreach ($resumen['llegadas'] as $fila)
                        <li wire:key="llegada-{{ $fila['grupo']->id }}">
                            <a class="af-enlace" href="{{ GrupoResource::getUrl('view', ['record' => $fila['grupo']]) }}">{{ $fila['grupo']->nombre }}</a>
                            · {{ Formato::fecha($fila['llegada']) }}
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-filament::section>

        <x-filament::section :heading="__('grupos.seguimiento.mensajes')">
            @if ($resumen['mensajes']->isEmpty())
                <p class="af-nota">{{ __('grupos.seguimiento.nada') }}</p>
            @else
                <ul class="af-lista-simple">
                    @foreach ($resumen['mensajes'] as $mensaje)
                        <li wire:key="mensaje-{{ $mensaje->id }}">
                            <a class="af-enlace" href="{{ GrupoResource::getUrl('view', ['record' => $mensaje->grupo]) }}">{{ $mensaje->grupo->nombre }}</a>
                            · {{ \Illuminate\Support\Str::limit($mensaje->texto, 120) }}
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
