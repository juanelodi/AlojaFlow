{{-- AlojaFlow · SPDX-License-Identifier: AGPL-3.0-or-later --}}
<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<title>@yield('titulo')</title>
<style>
    @page { margin: 2cm; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #0b1f2a; line-height: 1.45; }
    h1 { font-size: 20px; margin: 0 0 4px; color: #0f766e; }
    h2 { font-size: 13px; margin: 18px 0 6px; border-bottom: 1px solid #cbd5e1; padding-bottom: 3px; }
    .cabecera { width: 100%; margin-bottom: 14px; }
    .cabecera td { vertical-align: top; }
    .derecha { text-align: right; }
    .gris { color: #475569; }
    table.datos { width: 100%; border-collapse: collapse; }
    table.datos th { text-align: left; font-weight: bold; width: 32%; padding: 3px 6px 3px 0; color: #475569; vertical-align: top; }
    table.datos td { padding: 3px 0; vertical-align: top; }
    table.importes { width: 100%; border-collapse: collapse; margin-top: 4px; }
    table.importes td { padding: 4px 0; border-bottom: 1px solid #e2e8f0; }
    table.importes td.num { text-align: right; white-space: nowrap; }
    table.importes tr.total td { font-weight: bold; font-size: 13px; border-bottom: 0; border-top: 2px solid #0f766e; }
    .nota { margin-top: 18px; color: #475569; font-size: 10px; }
</style>
</head>
<body>
    <table class="cabecera">
        <tr>
            <td>
                <h1>{{ $propiedad->nombre }}</h1>
                <div class="gris">
                    {{ $propiedad->domicilio }}
                    @if ($propiedad->telefono)<br>{{ $propiedad->telefono }}@endif
                    @if ($propiedad->correo)<br>{{ $propiedad->correo }}@endif
                </div>
            </td>
            <td class="derecha">
                <strong style="font-size: 14px">@yield('titulo')</strong><br>
                <span class="gris">{{ __('documentos.numero') }} {{ $reserva->numero }}</span><br>
                <span class="gris">{{ __('documentos.codigo') }} {{ $reserva->codigo_publico }}</span>
            </td>
        </tr>
    </table>
    @yield('contenido')
</body>
</html>
