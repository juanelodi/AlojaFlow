{{-- AlojaFlow · SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- «Reportar o sugerir» y «Mis reportes» (D-150). --}}
@php
    $reportes = $this->reportes();
    $esAdministrador = (bool) \App\Seguridad\Acceso::usuarioActual()?->es_administrador;
@endphp
<x-filament-panels::page>
    <div class="af-pila">
        <p class="af-prosa">{{ __('mejora.reportar.intro') }}</p>

        {{-- El tamaño de la pantalla ayuda a repetir un error de diseño; se toma al abrir. --}}
        <form wire:submit="enviar" class="af-pila" x-data x-init="$wire.$set('datos.resolucion', window.innerWidth + 'x' + window.innerHeight, false)">
            {{ $this->form }}
            <div><x-filament::button type="submit" icon="heroicon-o-paper-airplane">{{ __('mejora.reportar.enviar') }}</x-filament::button></div>
        </form>

        <x-filament::section :heading="$esAdministrador ? __('mejora.reportar.reportes_instalacion') : __('mejora.reportar.mis_reportes')">
            @if ($reportes->isEmpty())
                <p class="af-secundario">{{ __('mejora.reportar.vacio') }}</p>
            @else
                <div class="af-tabla-contenedor" tabindex="0" role="region" aria-label="{{ __('mejora.reportar.mis_reportes') }}">
                    <table class="af-tabla">
                        <thead>
                            <tr>
                                <th scope="col">{{ __('mejora.reportar.columnas.folio') }}</th>
                                <th scope="col">{{ __('mejora.reportar.columnas.fecha') }}</th>
                                <th scope="col">{{ __('mejora.reportar.columnas.tipo') }}</th>
                                <th scope="col">{{ __('mejora.reportar.columnas.titulo') }}</th>
                                <th scope="col">{{ __('mejora.reportar.columnas.estado') }}</th>
                                <th scope="col">{{ __('mejora.reportar.columnas.respuesta') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($reportes as $reporte)
                                <tr>
                                    <td>{{ $reporte->folio ?? '—' }}</td>
                                    <td data-orden="{{ $reporte->creado_en?->format('Y-m-d H:i') }}">{{ $reporte->creado_en === null ? '—' : \App\Support\Formato::momento($reporte->creado_en) }}</td>
                                    <td>{{ __('mejora.reportar.tipos.'.$reporte->tipo) }}</td>
                                    <td>
                                        {{ $reporte->titulo }}
                                        @if ($esAdministrador && $reporte->usuario !== null)
                                            <span class="af-secundario" style="display: block">{{ $reporte->usuario->name }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($reporte->envio !== \App\Models\ReporteMejora::ENVIO_ENVIADO)
                                            <x-filament::badge :color="$reporte->envio === \App\Models\ReporteMejora::ENVIO_RECHAZADO ? 'danger' : 'warning'">{{ __('mejora.reportar.envio.'.$reporte->envio) }}</x-filament::badge>
                                        @else
                                            <x-filament::badge :color="in_array($reporte->estado, ['resuelto'], true) ? 'success' : 'info'">{{ __('mejora.reportar.estados.'.($reporte->estado ?? 'nuevo')) }}</x-filament::badge>
                                        @endif
                                        @if ($reporte->resuelto_en_version !== null)
                                            <span class="af-secundario" style="display: block">{{ __('mejora.reportar.resuelto_en', ['version' => $reporte->resuelto_en_version]) }}</span>
                                        @endif
                                    </td>
                                    <td style="white-space: pre-wrap; overflow-wrap: anywhere">{{ $reporte->respuesta ?? __('mejora.reportar.sin_respuesta') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
