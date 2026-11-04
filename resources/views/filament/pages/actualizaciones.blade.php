{{-- AlojaFlow · SPDX-License-Identifier: AGPL-3.0-or-later --}}
@use('App\Support\Formato')
@use('App\Enums\EstadoActualizacion')
@use('Illuminate\Support\Number')
@use('Illuminate\Support\Str')
<x-filament-panels::page>
    <div class="af-pila">
        @if (session('actualizacion_ok'))
            <x-filament::callout color="success" icon="heroicon-o-check-circle" :heading="__('actualizaciones.completada', ['version' => session('actualizacion_ok')])" />
        @endif
        @if (session('actualizacion_error'))
            <x-filament::callout color="danger" icon="heroicon-o-x-circle" :heading="__('actualizaciones.fallo')" :description="session('actualizacion_error')" />
        @endif
        @if ($enlace = $this->enlaceParaTerminar())
            <x-filament::callout color="warning" icon="heroicon-o-exclamation-triangle" :heading="__('actualizaciones.pendiente_finalizar')">
                <x-slot name="footer">
                    <x-filament::button tag="a" :href="$enlace" color="warning">{{ __('actualizaciones.finalizar_ahora') }}</x-filament::button>
                </x-slot>
            </x-filament::callout>
        @endif

        <x-filament::section>
            <p class="af-prosa"><strong>{{ __('actualizaciones.version_instalada', ['version' => $this->versionInstalada()]) }}</strong></p>
            <p class="af-nota">{{ __('actualizaciones.explicacion') }}</p>
            @if ($this->bloqueadaPorGit())
                <p class="af-aviso" style="margin-top: .75rem">{{ __('actualizaciones.error.copia_git') }}</p>
            @endif
        </x-filament::section>

        <x-filament::section :heading="__('actualizaciones.paso_subir')">
            <form wire:submit="subir" class="af-pila">
                {{ $this->form }}
                <div>
                    <x-filament::button type="submit" icon="heroicon-m-arrow-up-tray">{{ __('actualizaciones.subir_boton') }}</x-filament::button>
                </div>
            </form>
            <p class="af-nota" style="margin-top: .75rem">{{ __('actualizaciones.ftp', ['carpeta' => $this->carpetaEntrada()]) }}</p>
        </x-filament::section>

        <x-filament::section :heading="__('actualizaciones.paso_revisar')">
            @php($disponibles = $this->disponibles())
            @if ($disponibles === [])
                <p class="af-nota">{{ __('actualizaciones.sin_paquetes') }}</p>
            @else
                <div class="af-barra-herramientas">
                    <label class="af-campo" style="min-width: 16rem">
                        <span>{{ __('actualizaciones.paquete') }}</span>
                        <x-filament::input.wrapper>
                            <x-filament::input.select wire:model.live="paquete">
                                @foreach ($disponibles as $nombre)
                                    <option value="{{ $nombre }}">{{ $nombre }}</option>
                                @endforeach
                            </x-filament::input.select>
                        </x-filament::input.wrapper>
                    </label>
                    <label class="af-campo" style="flex-direction: row; align-items: center; gap: .5rem; min-width: auto">
                        <x-filament::input.checkbox wire:model.live="reinstalar" />
                        <span>{{ __('actualizaciones.reinstalar') }}</span>
                    </label>
                    <x-filament::button color="gray" icon="heroicon-m-magnifying-glass" wire:click="revisar">{{ __('actualizaciones.revisar') }}</x-filament::button>
                    {{ $this->eliminarPaqueteAction }}
                </div>
            @endif

            @if ($r = $this->revision)
                <div class="af-pila" style="margin-top: 1rem">
                    @foreach ($r['errores'] as $error)
                        <p class="af-aviso af-aviso--error" role="alert">{{ $error }}</p>
                    @endforeach
                    @foreach ($r['avisos'] as $aviso)
                        <p class="af-aviso">{{ $aviso }}</p>
                    @endforeach

                    @if ($r['valida'])
                        <dl class="af-kpis">
                            <div class="af-kpi"><dt>{{ __('actualizaciones.de_a') }}</dt><dd>{{ $r['version_actual'] }} → {{ $r['version'] }}</dd></div>
                            <div class="af-kpi"><dt>{{ __('actualizaciones.nuevos') }}</dt><dd>{{ $r['nuevos'] }}</dd></div>
                            <div class="af-kpi"><dt>{{ __('actualizaciones.cambiados') }}</dt><dd>{{ $r['cambiados'] }}</dd></div>
                            <div class="af-kpi"><dt>{{ __('actualizaciones.borrar') }}</dt><dd>{{ $r['borrar'] }}</dd></div>
                            <div class="af-kpi"><dt>{{ __('actualizaciones.iguales') }}</dt><dd>{{ $r['iguales'] }}</dd></div>
                            <div class="af-kpi"><dt>{{ __('actualizaciones.tamano') }}</dt><dd>{{ Number::fileSize((int) $r['bytes'], 1) }}</dd></div>
                        </dl>
                        @if ($r['notas'])
                            <div class="af-prosa">{!! Str::markdown((string) $r['notas'], ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}</div>
                        @endif
                        <p class="af-nota">{{ __('actualizaciones.que_hara') }}</p>
                        <div>{{ $this->actualizarAction }}</div>
                    @endif
                </div>
            @endif
        </x-filament::section>

        <x-filament::section :heading="__('actualizaciones.historial')">
            @php($historial = $this->historial())
            @if ($historial->isEmpty())
                <p class="af-nota">{{ __('actualizaciones.sin_historial') }}</p>
            @else
                <div class="af-tabla-contenedor" tabindex="0" role="region" aria-label="{{ __('actualizaciones.historial') }}">
                    <table class="af-tabla">
                        <caption class="af-sr">{{ __('actualizaciones.historial') }}</caption>
                        <thead>
                            <tr>
                                <th scope="col">{{ __('actualizaciones.fecha') }}</th>
                                <th scope="col">{{ __('actualizaciones.de_a') }}</th>
                                <th scope="col">{{ __('actualizaciones.estado') }}</th>
                                <th scope="col">{{ __('actualizaciones.usuario') }}</th>
                                <th scope="col">{{ __('actualizaciones.detalle') }}</th>
                                <th scope="col">{{ __('actualizaciones.copia_bd') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($historial as $a)
                                <tr wire:key="actualizacion-{{ $a->id }}">
                                    <td>{{ Formato::momento($a->iniciada_en ?? $a->creado_en) }}</td>
                                    <td>{{ $a->version_anterior }} → {{ $a->version_nueva }}</td>
                                    <td>
                                        <x-filament::badge :color="match ($a->estado) {
                                            EstadoActualizacion::Completada => 'success',
                                            EstadoActualizacion::Fallida, EstadoActualizacion::Revertida => 'danger',
                                            default => 'warning',
                                        }">{{ $a->estado->etiqueta() }}</x-filament::badge>
                                    </td>
                                    <td>{{ $a->usuario?->name ?? '—' }}</td>
                                    <td>{{ $a->detalle ? Str::limit($a->detalle, 160) : '—' }}</td>
                                    <td>
                                        @if ($this->tieneCopia($a))
                                            <a href="{{ route('actualizaciones.copia-base-datos', $a) }}">{{ __('actualizaciones.descargar') }}</a>
                                        @else
                                            —
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
