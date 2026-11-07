{{-- AlojaFlow · SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- La fila de una reserva en el teléfono: lo que se busca de un vistazo. Las demás columnas se ven desde tableta. --}}
@use('App\Enums\EstadoReserva')
@use('App\Enums\TipoReserva')
@use('App\Support\Formato')
@php($reserva = $getRecord())
<div class="af-fila-movil">
    <div class="af-fila-movil__cabecera">
        <strong>{{ $reserva->titular?->nombreCompleto() ?? ($reserva->tipo === TipoReserva::Bloqueo ? TipoReserva::Bloqueo->etiqueta() : '—') }}</strong>
        @unless ($reserva->tipo === TipoReserva::Bloqueo)
        <x-filament::badge :color="match (true) {
            $reserva->eliminada_en_principal_en !== null => 'danger',
            $reserva->estado === EstadoReserva::Cancelada => 'danger',
            $reserva->estado === EstadoReserva::SinConfirmar => 'warning',
            default => 'success',
        }">{{ $reserva->eliminada_en_principal_en ? __('ui.reservas.eliminada') : $reserva->estado->etiqueta() }}</x-filament::badge>
        @endunless
    </div>
    <div>{{ Formato::fechaCorta($reserva->llegada) }} → {{ Formato::fechaCorta($reserva->salida) }} · {{ trans_choice('ui.comun.n_noches', (int) $reserva->llegada->diffInDays($reserva->salida)) }}</div>
    <div class="af-secundario">{{ $reserva->unidad->nombre ?? '—' }} · {{ __('ui.reservas.numero') }} {{ $reserva->numero }}@if ($reserva->origen_texto) · {{ $reserva->origen_texto }}@endif</div>
    @if ($reserva->pendiente > 0)
        <div class="af-fila-movil__saldo">{{ __('ui.comun.pendiente') }}: <strong>{{ Formato::dinero($reserva->pendiente, $reserva->moneda) }}</strong></div>
    @endif
</div>
