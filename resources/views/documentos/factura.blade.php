{{-- AlojaFlow · SPDX-License-Identifier: AGPL-3.0-or-later --}}
@use('App\Support\Formato')
@use('App\Enums\TipoDocumentoFiscal')
<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<title>{{ $factura->folio() }}</title>
<style>
    @page { margin: 2cm; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #0b1f2a; line-height: 1.45; }
    h1 { font-size: 18px; margin: 0; color: #0f766e; }
    h2 { font-size: 11px; margin: 12px 0 3px; color: #475569; }
    .cabecera { width: 100%; margin-bottom: 12px; }
    .cabecera td { vertical-align: top; }
    .derecha { text-align: right; }
    .aviso { border: 2px solid #b91c1c; color: #b91c1c; padding: 6px 8px; font-weight: bold; margin-bottom: 12px; }
    table.lineas { width: 100%; border-collapse: collapse; margin-top: 8px; }
    table.lineas th { text-align: left; border-bottom: 2px solid #0f766e; padding: 3px 4px; }
    table.lineas td { border-bottom: 1px solid #e2e8f0; padding: 3px 4px; vertical-align: top; }
    .num { text-align: right; white-space: nowrap; }
    tr.total td { font-weight: bold; font-size: 12px; border-bottom: 0; }
    .gris { color: #475569; }
</style>
</head>
<body>
    @if ($sinValidez)
        <div class="aviso">{{ $sinValidez }}</div>
    @endif
    <table class="cabecera">
        <tr>
            <td>
                <h1>{{ $factura->emisor['nombre'] }}</h1>
                <div>{{ $factura->emisor['identificador_fiscal'] }}</div>
                @if (filled($factura->emisor['domicilio'] ?? null))<div class="gris">{{ $factura->emisor['domicilio'] }}</div>@endif
                @if (filled($factura->emisor['regimen_fiscal'] ?? null))<div class="gris">{{ __('dinero.facturas.regimen') }}: {{ $factura->emisor['regimen_fiscal'] }}</div>@endif
            </td>
            <td class="derecha">
                <h1>{{ $factura->tipo === TipoDocumentoFiscal::Factura ? __('dinero.facturas.factura') : __('dinero.facturas.recibo') }}</h1>
                <div><strong>{{ $factura->folio() }}</strong></div>
                <div class="gris">{{ Formato::momento($factura->emitida_en, $factura->propiedad->zona_horaria) }}</div>
                @if ($factura->uuid_fiscal)<div class="gris">{{ $factura->uuid_fiscal }}</div>@endif
            </td>
        </tr>
    </table>

    <h2>{{ __('dinero.facturas.receptor') }}</h2>
    <div><strong>{{ $factura->receptor['nombre'] }}</strong> · {{ $factura->receptor['identificador_fiscal'] }}</div>
    @if (filled($factura->receptor['codigo_postal'] ?? null))<div class="gris">{{ __('dinero.facturas.codigo_postal') }}: {{ $factura->receptor['codigo_postal'] }}@if (filled($factura->receptor['uso'] ?? null)) · {{ __('dinero.facturas.uso') }}: {{ $factura->receptor['uso'] }}@endif</div>@endif

    <table class="lineas">
        <thead><tr><th>{{ __('dinero.facturas.concepto') }}</th><th class="num">{{ __('dinero.facturas.importe') }}</th></tr></thead>
        <tbody>
            @foreach ($factura->lineas as $linea)
                <tr><td>{{ $linea->concepto }}</td><td class="num">{{ Formato::dinero($linea->importe, $factura->moneda) }}</td></tr>
            @endforeach
            <tr><td class="num">{{ __('dinero.facturas.subtotal') }}</td><td class="num">{{ Formato::dinero($factura->subtotal, $factura->moneda) }}</td></tr>
            @foreach ($impuestos as $nombre => $importe)
                <tr><td class="num">{{ $nombre }}</td><td class="num">{{ Formato::dinero($importe, $factura->moneda) }}</td></tr>
            @endforeach
            <tr class="total"><td class="num">{{ __('dinero.facturas.total') }}</td><td class="num">{{ Formato::dinero($factura->total, $factura->moneda) }}</td></tr>
        </tbody>
    </table>
    @if ($factura->forma_pago)<p class="gris">{{ __('dinero.facturas.forma_pago') }}: {{ $factura->forma_pago }}@if ($factura->metodo_pago) · {{ __('dinero.facturas.metodo_pago') }}: {{ $factura->metodo_pago }}@endif</p>@endif
</body>
</html>
