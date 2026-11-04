{{-- AlojaFlow · SPDX-License-Identifier: AGPL-3.0-or-later --}}
@use('App\Support\Formato')
<x-filament-panels::page>
    @php($registro = $this->registro())
    @php($tipos = $this->tiposDocumento())
    @php($campos = $this->camposPais())
    <div class="af-pila">
        <x-filament::section>
            <p>
                <a class="af-enlace" href="{{ \App\Filament\Resources\Reservas\ReservaResource::getUrl('view', ['record' => $registro->reserva_id]) }}">{{ __('llegada.panel.reserva') }} {{ $registro->reserva->numero }}</a>
                · {{ Formato::fecha($registro->reserva->llegada, 'ddd D MMM') }} → {{ Formato::fecha($registro->reserva->salida, 'ddd D MMM') }}
                · {{ $registro->reserva->unidad->nombre }}
                · <strong>{{ $registro->estado->etiqueta() }}</strong>
            </p>
            @if ($registro->hora_llegada_estimada)<p class="af-nota">{{ __('llegada.hora') }} {{ $registro->hora_llegada_estimada }}</p>@endif
            @if ($registro->notas_huesped)<p class="af-nota" style="white-space: pre-line">{{ $registro->notas_huesped }}</p>@endif
        </x-filament::section>

        <div class="af-limpieza">
            @foreach ($registro->personas as $persona)
                <x-filament::section :heading="$persona->es_titular ? __('llegada.titular') : __('llegada.acompanante', ['n' => $persona->orden])">
                    <dl class="af-ficha">
                        <dt>{{ __('llegada.nombre') }}</dt><dd>{{ $persona->nombreCompleto() }}</dd>
                        <dt>{{ __('llegada.fecha_nacimiento') }}</dt><dd>{{ Formato::fecha($persona->fecha_nacimiento) }}</dd>
                        <dt>{{ __('llegada.nacionalidad') }}</dt><dd>{{ $persona->nacionalidad_texto ?? '—' }}</dd>
                        <dt>{{ __('llegada.panel.documento') }}</dt><dd>{{ $tipos[$persona->documento_tipo] ?? $persona->documento_tipo ?? '—' }} {{ $persona->documento_numero }}</dd>
                        @if ($persona->es_titular)
                            <dt>{{ __('llegada.correo') }}</dt><dd>{{ $persona->correo }}</dd>
                            <dt>{{ __('llegada.telefono') }}</dt><dd>{{ $persona->telefono }}</dd>
                            <dt>{{ __('llegada.domicilio') }}</dt><dd>{{ collect([$persona->domicilio_calle, $persona->domicilio_ciudad, $persona->domicilio_region, $persona->domicilio_codigo_postal, $persona->domicilio_pais_texto])->filter()->implode(', ') }}</dd>
                        @endif
                        @foreach ((array) $persona->datos_pais as $campo => $valor)
                            <dt>{{ $campos[$campo]['etiqueta'] ?? $campo }}</dt><dd>{{ $valor }}</dd>
                        @endforeach
                    </dl>
                </x-filament::section>
            @endforeach
        </div>

        @if ($registro->archivos->isNotEmpty())
            <x-filament::section :heading="__('llegada.panel.fotos')">
                <div class="af-enlaces">
                    @foreach ($registro->archivos as $archivo)
                        <a href="{{ route('archivos.ver', $archivo) }}" target="_blank" rel="noopener">{{ __('llegada.panel.ver_foto', ['n' => $loop->iteration]) }}</a>
                    @endforeach
                </div>
            </x-filament::section>
        @endif

        @if ($registro->aceptaciones->isNotEmpty())
            <x-filament::section :heading="__('llegada.panel.aceptaciones')">
                <div class="af-tabla-contenedor" tabindex="0" role="region" aria-label="{{ $this->getTitle() }}">
                    <table class="af-tabla">
                        <tbody>
                            @foreach ($registro->aceptaciones as $aceptacion)
                                <tr>
                                    <td>{{ $aceptacion->documento->titulo }} (v{{ $aceptacion->documento->version }})</td>
                                    <td>{{ $aceptacion->firmante_nombre }}</td>
                                    <td>{{ Formato::momento($aceptacion->aceptada_en, $registro->propiedad->zona_horaria) }}</td>
                                    <td>{{ $aceptacion->verificacion->etiqueta() }}</td>
                                    <td>@if ($aceptacion->copia_archivo_id)<a class="af-enlace" href="{{ route('archivos.ver', $aceptacion->copia_archivo_id) }}" target="_blank" rel="noopener">{{ __('llegada.panel.ver_copia') }}</a>@endif</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
