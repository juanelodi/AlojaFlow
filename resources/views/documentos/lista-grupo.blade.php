{{-- AlojaFlow · Copyright (C) 2026 Juan Galeana Durán · SPDX-License-Identifier: FSL-1.1-ALv2 --}}
@use('App\Support\Formato')
<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<title>{{ __('grupos.lista.pdf_titulo') }}</title>
<style>
    @page { margin: 1.5cm; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #0b1f2a; line-height: 1.4; }
    h1 { font-size: 16px; margin: 0 0 2px; color: #0f766e; }
    .gris { color: #475569; margin-bottom: 12px; }
    table { width: 100%; border-collapse: collapse; }
    th { text-align: left; font-weight: bold; color: #475569; border-bottom: 1.5px solid #0f766e; padding: 4px 6px 4px 0; }
    td { border-bottom: 1px solid #e2e8f0; padding: 5px 6px 5px 0; vertical-align: top; }
    tr { page-break-inside: avoid; }
    thead { display: table-header-group; }
</style>
</head>
<body>
    <h1>{{ __('grupos.lista.pdf_titulo') }}</h1>
    <div class="gris">{{ $grupo->nombre }} · {{ $grupo->codigo }} · {{ $grupo->propiedad->nombre }}</div>
    <table>
        <thead>
            <tr>
                <th scope="col">{{ __('grupos.lista.unidad') }}</th>
                <th scope="col">{{ __('grupos.lista.llegada') }}</th>
                <th scope="col">{{ __('grupos.lista.salida') }}</th>
                <th scope="col">{{ __('grupos.lista.responsable') }}</th>
                <th scope="col">{{ __('grupos.lista.telefono') }}</th>
                <th scope="col">{{ __('grupos.lista.personas') }}</th>
                <th scope="col">{{ __('grupos.lista.acompanantes') }}</th>
                <th scope="col">{{ __('grupos.lista.llego') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($reservas as $reserva)
                <tr>
                    <td>{{ $reserva->unidad->nombre }}</td>
                    <td>{{ Formato::fecha($reserva->llegada) }}</td>
                    <td>{{ Formato::fecha($reserva->salida) }}</td>
                    <td>{{ $reserva->habitacionGrupo?->responsable }}</td>
                    <td>{{ $reserva->habitacionGrupo?->telefono }}</td>
                    <td>{{ $reserva->personas }}</td>
                    <td>{{ $reserva->habitacionGrupo?->acompanantes }}</td>
                    <td style="width:40px">&nbsp;</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
