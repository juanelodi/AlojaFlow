{{-- AlojaFlow · SPDX-License-Identifier: AGPL-3.0-or-later --}}
@use('App\Filament\Resources\Reservas\ReservaResource')
@use('App\Support\Formato')
<ul class="af-lista-simple">
    @foreach ($getRecord()->reservas()->with('unidad')->orderBy('id')->get() as $reserva)
        <li>
            <a class="af-enlace" href="{{ ReservaResource::getUrl('view', ['record' => $reserva]) }}">{{ $reserva->numero }}</a>
            · {{ $reserva->unidad->nombre }} · {{ Formato::fecha($reserva->llegada) }} → {{ Formato::fecha($reserva->salida) }}
        </li>
    @endforeach
</ul>
