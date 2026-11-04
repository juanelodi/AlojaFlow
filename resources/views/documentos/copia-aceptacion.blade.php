{{-- AlojaFlow · SPDX-License-Identifier: AGPL-3.0-or-later --}}
@use('App\Support\Formato')
<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<title>{{ $documento->titulo }}</title>
<style>
    @page { margin: 2cm; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #0b1f2a; line-height: 1.45; }
    h1 { font-size: 16px; margin: 0 0 8px; color: #0f766e; }
    .cuerpo { white-space: pre-line; }
    table { width: 100%; border-collapse: collapse; margin-top: 18px; border-top: 2px solid #0f766e; }
    th { text-align: left; width: 30%; padding: 3px 6px 3px 0; color: #475569; font-weight: bold; vertical-align: top; }
    td { padding: 3px 0; word-break: break-all; }
</style>
</head>
<body>
    <h1>{{ $documento->titulo }}</h1>
    <div class="cuerpo">{{ $documento->cuerpo }}</div>
    <table>
        <tr><th>{{ __('llegada.constancia.firmante') }}</th><td>{{ $aceptacion->firmante_nombre }}</td></tr>
        <tr><th>{{ __('llegada.constancia.reserva') }}</th><td>{{ $registro->reserva->numero }}</td></tr>
        <tr><th>{{ __('llegada.constancia.fecha') }}</th><td>{{ $aceptacion->aceptada_en->toIso8601String() }}</td></tr>
        <tr><th>{{ __('llegada.constancia.verificacion') }}</th><td>{{ $aceptacion->verificacion->etiqueta() }}</td></tr>
        <tr><th>{{ __('llegada.constancia.ip') }}</th><td>{{ $aceptacion->ip }}</td></tr>
        <tr><th>{{ __('llegada.constancia.version') }}</th><td>{{ $documento->version }} ({{ $documento->idioma }})</td></tr>
        <tr><th>{{ __('llegada.constancia.huella') }}</th><td>{{ $aceptacion->huella_documento }}</td></tr>
    </table>
</body>
</html>
