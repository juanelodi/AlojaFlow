{{-- AlojaFlow · Copyright (C) 2026 Juan Galeana Durán · SPDX-License-Identifier: FSL-1.1-ALv2 --}}
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
