{{-- AlojaFlow · SPDX-License-Identifier: AGPL-3.0-or-later --}}
@use('App\Enums\EstadoReserva')
@use('App\Filament\Resources\Reservas\ReservaResource')
@use('App\Support\Formato')
@php
    $ficha = \App\Servicios\FichaHuesped::de($getRecord(), \App\Seguridad\Acceso::usuarioActual());
    $cuentas = $ficha['cuentas'];
    $moneda = (string) $cuentas['moneda'];
@endphp
<div class="af-ficha">
    <dl class="af-cifras af-ficha__cifras">
        <div class="af-cifra"><dt>{{ __('ui.ficha.estancias') }}</dt><dd>{{ $cuentas['estancias'] }}</dd></div>
        <div class="af-cifra"><dt>{{ __('ui.comun.noches') }}</dt><dd>{{ $cuentas['noches'] }}</dd></div>
        <div class="af-cifra"><dt>{{ __('ui.comun.total') }}</dt><dd>{{ Formato::dinero($cuentas['total'], $moneda) }}</dd></div>
        <div class="af-cifra"><dt>{{ __('ui.ficha.pagado') }}</dt><dd>{{ Formato::dinero($cuentas['pagado'], $moneda) }}</dd></div>
        <div @class(['af-cifra', 'af-cifra--destacada' => $cuentas['pendiente'] > 0])><dt>{{ __('ui.comun.pendiente') }}</dt><dd>{{ Formato::dinero($cuentas['pendiente'], $moneda) }}</dd></div>
    </dl>
    @if ($cuentas['estancias'] > 0)
        <p class="af-nota">
            @if ($cuentas['primera']){{ __('ui.ficha.primera') }}: {{ Formato::fecha($cuentas['primera']->llegada) }}@endif
            @if ($cuentas['ultima']) · {{ __('ui.ficha.ultima') }}: {{ Formato::fecha($cuentas['ultima']->llegada) }}@endif
            @if ($cuentas['proxima']) · {{ __('ui.ficha.proxima') }}: {{ Formato::fecha($cuentas['proxima']->llegada) }}@endif
        </p>
    @endif

    {{-- Plegado de entrada: un cliente frecuente son años de reservas. Cada grupo dice lo que lleva, para saber sin abrirlo si interesa. --}}
    @forelse ($ficha['anios'] as $anio => $delAnio)
        <details class="af-plegable af-ficha__anio" wire:ignore.self>
            <summary>
                <span class="af-ficha__titulo">{{ $anio }}</span>
                <span class="af-nota">{{ trans_choice('ui.ficha.n_reservas', $delAnio['reservas'], ['n' => $delAnio['reservas']]) }} · {{ $delAnio['noches'] }} {{ mb_strtolower(__('ui.comun.noches')) }}@if ($delAnio['total'] > 0) · {{ Formato::dinero($delAnio['total'], $moneda) }}@endif</span>
            </summary>
            <div class="af-ficha__meses">
                @foreach ($delAnio['meses'] as $mes)
                    <details class="af-plegable af-ficha__mes" wire:ignore.self>
                        <summary>
                            <span class="af-ficha__mes-nombre">{{ Formato::fecha($mes['fecha'], 'MMMM') }}</span>
                            <span class="af-nota">{{ trans_choice('ui.ficha.n_reservas', $mes['reservas']->count(), ['n' => $mes['reservas']->count()]) }} · {{ $mes['noches'] }} {{ mb_strtolower(__('ui.comun.noches')) }}@if ($mes['total'] > 0) · {{ Formato::dinero($mes['total'], $moneda) }}@endif</span>
                        </summary>
                    <div class="af-tabla-contenedor" tabindex="0" role="region" aria-label="{{ Formato::fecha($mes['fecha'], 'MMMM YYYY') }}">
                        <table class="af-tabla af-tabla--tarjetas">
                            <thead>
                                <tr>
                                    <th scope="col">{{ __('ui.reservas.numero') }}</th>
                                    <th scope="col">{{ __('ui.comun.llegada') }}</th>
                                    <th scope="col">{{ __('ui.comun.salida') }}</th>
                                    <th scope="col">{{ __('ui.comun.unidad') }}</th>
                                    <th scope="col" class="af-num">{{ __('ui.comun.noches') }}</th>
                                    <th scope="col" class="af-num">{{ __('ui.comun.personas') }}</th>
                                    <th scope="col">{{ __('ui.comun.estado') }}</th>
                                    <th scope="col" class="af-num">{{ __('ui.comun.total') }}</th>
                                    <th scope="col" class="af-num">{{ __('ui.ficha.pagado') }}</th>
                                    <th scope="col" class="af-num">{{ __('ui.comun.pendiente') }}</th>
                                    <th scope="col">{{ __('ui.comun.origen') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($mes['reservas'] as $reserva)
                                    @php($esTitular = in_array($reserva->id, $ficha['titular'], true))
                                    <tr>
                                        <td data-etiqueta="{{ __('ui.reservas.numero') }}" data-orden="{{ $reserva->numero }}">
                                            @if (ReservaResource::canView($reserva))
                                                <a class="af-enlace" href="{{ ReservaResource::getUrl('view', ['record' => $reserva]) }}">{{ $reserva->numero }}</a>
                                            @else
                                                {{ $reserva->numero }}
                                            @endif
                                            @unless ($esTitular)
                                                <span class="af-secundario">{{ __('ui.ficha.acompanante') }}</span>
                                            @endunless
                                        </td>
                                        <td data-etiqueta="{{ __('ui.comun.llegada') }}" data-orden="{{ $reserva->llegada->format('Y-m-d') }}">{{ Formato::fecha($reserva->llegada) }}</td>
                                        <td data-etiqueta="{{ __('ui.comun.salida') }}" data-orden="{{ $reserva->salida->format('Y-m-d') }}">{{ Formato::fecha($reserva->salida) }}</td>
                                        <td data-etiqueta="{{ __('ui.comun.unidad') }}">{{ $reserva->unidad->nombre ?? '—' }}</td>
                                        <td class="af-num af-solo-ancho" data-etiqueta="{{ __('ui.comun.noches') }}">{{ (int) $reserva->llegada->diffInDays($reserva->salida) }}</td>
                                        <td class="af-num af-solo-ancho" data-etiqueta="{{ __('ui.comun.personas') }}">{{ $reserva->personas }}</td>
                                        <td data-etiqueta="{{ __('ui.comun.estado') }}">
                                            <x-filament::badge :color="$reserva->eliminada_en_principal_en ? 'danger' : match ($reserva->estado) { EstadoReserva::Cancelada => 'danger', EstadoReserva::SinConfirmar => 'warning', default => 'success' }">
                                                {{ $reserva->eliminada_en_principal_en ? __('ui.reservas.eliminada') : $reserva->estado->etiqueta() }}
                                            </x-filament::badge>
                                        </td>
                                        @if ($esTitular)
                                            <td class="af-num" data-etiqueta="{{ __('ui.comun.total') }}" data-orden="{{ $reserva->total }}">{{ Formato::dinero($reserva->total, $reserva->moneda) }}</td>
                                            <td class="af-num af-solo-ancho" data-etiqueta="{{ __('ui.ficha.pagado') }}" data-orden="{{ $reserva->pagado }}">{{ Formato::dinero($reserva->pagado, $reserva->moneda) }}</td>
                                            <td class="af-num" data-etiqueta="{{ __('ui.comun.pendiente') }}" data-orden="{{ $reserva->pendiente }}">{{ $reserva->pendiente > 0 ? Formato::dinero($reserva->pendiente, $reserva->moneda) : '—' }}</td>
                                        @else
                                            <td class="af-num" data-etiqueta="{{ __('ui.comun.total') }}">—</td>
                                            <td class="af-num af-solo-ancho" data-etiqueta="{{ __('ui.ficha.pagado') }}">—</td>
                                            <td class="af-num" data-etiqueta="{{ __('ui.comun.pendiente') }}">—</td>
                                        @endif
                                        <td class="af-solo-ancho" data-etiqueta="{{ __('ui.comun.origen') }}">{{ $reserva->origen_texto ?: '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    </details>
                @endforeach
            </div>
        </details>
    @empty
        <p class="af-vacio-tabla">{{ __('ui.ficha.sin_reservas') }}</p>
    @endforelse
</div>
