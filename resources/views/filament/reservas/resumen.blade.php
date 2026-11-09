{{-- AlojaFlow · SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Lo primero de la ficha de la reserva: cuándo, dónde, cuánto falta y cómo localizar al huésped. --}}
@use('App\Enums\TipoReserva')
@use('App\Filament\Resources\Huespedes\HuespedResource')
@use('App\Support\Formato')
@php
    $reserva = $getRecord();
    $esBloqueo = $reserva->tipo === TipoReserva::Bloqueo;
    $titular = $reserva->titular;
@endphp
<div class="af-ficha af-resumen-reserva">
    <div>
        <p class="af-resumen-reserva__fechas">{{ Formato::fechaCortaConAnio($reserva->llegada) }} → {{ Formato::fechaCortaConAnio($reserva->salida) }}</p>
        <div class="af-resumen-reserva__estado">
            @if ($esBloqueo)
                <x-filament::badge color="gray">{{ __('ui.calendario.bloqueo') }}</x-filament::badge>
            @else
                <x-estado-estancia :reserva="$reserva" />
            @endif
            <span class="af-nota">{{ trans_choice('ui.comun.n_noches', $reserva->noches) }} · {{ $reserva->unidad->nombre ?? '—' }}@unless ($esBloqueo) · {{ trans_choice('ui.comun.n_personas', $reserva->personas) }}@endunless</span>
        </div>
        {{-- Parte de un pedido de varias unidades del motor (D-158). --}}
        @if ($reserva->reserva_principal_id !== null || $reserva->acompanantes()->exists())
            @php($pedido = $reserva->pedido())
            <p class="af-nota" style="margin-top: .25rem">{{ __('ui.reservas.pedido_de', ['n' => $pedido->count(), 'numero' => $pedido->first()?->numero]) }}</p>
        @endif
    </div>

    @unless ($esBloqueo)
        <dl class="af-cifras af-ficha__cifras">
            {{-- Con segunda moneda, lo que sería en ella (D-157). --}}
            <div class="af-cifra"><dt>{{ __('ui.comun.total') }}</dt><dd>{{ Formato::dinero($reserva->total, $reserva->moneda) }}@if ($segunda = (new \App\Dinero\SegundaMoneda($reserva->propiedad))->texto($reserva->total))<span class="af-secundario" style="display:block">{{ $segunda }}</span>@endif</dd></div>
            <div class="af-cifra"><dt>{{ __('ui.ficha.pagado') }}</dt><dd>{{ Formato::dinero($reserva->pagado, $reserva->moneda) }}</dd></div>
            <div @class(['af-cifra', 'af-cifra--destacada' => $reserva->pendiente > 0])><dt>{{ __('ui.comun.pendiente') }}</dt><dd>{{ Formato::dinero($reserva->pendiente, $reserva->moneda) }}</dd></div>
        </dl>
    @endunless

    @if ($titular !== null)
        <div class="af-resumen-reserva__contactos">
            @if (HuespedResource::canView($titular))
                <p class="af-resumen-reserva__huesped"><a class="af-enlace" href="{{ HuespedResource::getUrl('view', ['record' => $titular]) }}">{{ __('ui.reservas.ver_huesped', ['nombre' => $titular->nombreCompleto()]) }}</a></p>
            @endif
            @if ($titular->contactos->isEmpty())
                <p class="af-nota">{{ __('ui.ficha.sin_contactos') }}</p>
            @else
                @include('filament.partes.contactos', ['contactos' => $titular->contactos, 'propiedad' => $reserva->propiedad])
            @endif
        </div>
    @endif
</div>
