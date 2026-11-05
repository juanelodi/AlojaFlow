{{-- AlojaFlow · SPDX-License-Identifier: AGPL-3.0-or-later --}}
<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<title>{{ $titulo }}</title>
<style>
    @page { margin: 1.5cm; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #0b1f2a; line-height: 1.4; }
    h1 { font-size: 16px; margin: 0 0 2px; color: #0f766e; }
    .gris { color: #475569; margin-bottom: 12px; }
    table { width: 100%; border-collapse: collapse; }
    th { text-align: left; font-weight: bold; color: #475569; border-bottom: 1.5px solid #0f766e; padding: 4px 6px 4px 0; }
    td { border-bottom: 1px solid #e2e8f0; padding: 3px 6px 3px 0; vertical-align: top; }
    tr { page-break-inside: avoid; }
    thead { display: table-header-group; }
</style>
</head>
<body>
    <h1>{{ $titulo }}</h1>
    <div class="gris">{{ $propiedades }} · {{ $periodo }}</div>
    <table>
        <thead>
            <tr>
                @foreach ($columnas as $columna)
                    <th scope="col">{{ $columna }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($filas as $fila)
                <tr>
                    @foreach ($fila as $celda)
                        <td>{{ $celda }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
