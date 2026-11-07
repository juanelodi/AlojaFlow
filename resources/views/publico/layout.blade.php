{{-- AlojaFlow · SPDX-License-Identifier: AGPL-3.0-or-later --}}
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('titulo') · {{ $propiedad->nombre }}</title>
<meta name="description" content="{{ __('motor.meta_descripcion', ['propiedad' => $propiedad->nombre]) }}">
@if ($propiedad->es_demo)<meta name="robots" content="noindex">@endif
<script src="{{ asset('js/alojaflow/alojaflow.js') }}?v={{ (int) @filemtime(public_path('js/alojaflow/alojaflow.js')) }}" defer></script>
<style>
:root{--marca:{{ $propiedad->color ?: '#0f766e' }};--tinta:#0b1f2a;--gris:#475569;--borde:#cbd5e1;--fondo:#f8fafc;--tarjeta:#fff;--campo:#fff;--rojo:#b91c1c;--verde:#15803d}
@media (prefers-color-scheme:dark){:root{--tinta:#e2e8f0;--gris:#94a3b8;--borde:#334155;--fondo:#020617;--tarjeta:#0f172a;--campo:#1e293b;--rojo:#f87171;--verde:#4ade80}}
*{box-sizing:border-box}
body{margin:0;background:var(--fondo);color:var(--tinta);font:16px/1.5 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif}
main{max-width:760px;margin:0 auto;padding:20px 16px 48px}
header.marca{display:flex;justify-content:space-between;gap:12px;align-items:baseline;flex-wrap:wrap;border-bottom:3px solid var(--marca);padding-bottom:10px;margin-bottom:20px}
header.marca h1{font-size:1.35rem;margin:0}
h2{font-size:1.1rem;margin:0 0 8px}
.tarjeta{background:var(--tarjeta);border:1px solid var(--borde);border-radius:12px;padding:18px;margin-bottom:14px}
label{display:block;font-size:.875rem;font-weight:600;margin:0 0 4px}
input[type=text],input[type=email],input[type=tel],input[type=date],input[type=number],input[type=password],input[type=file],textarea,select{width:100%;padding:11px 12px;border:1px solid var(--borde);border-radius:8px;font:inherit;background:var(--campo);color:var(--tinta)}
input:focus-visible,textarea:focus-visible,button:focus-visible,a:focus-visible{outline:3px solid var(--marca);outline-offset:2px}
.rejilla{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;align-items:end}
.campo{margin-bottom:12px}
button,.boton{display:inline-block;min-height:44px;padding:10px 18px;border:0;border-radius:8px;background:var(--marca);color:#fff;font:inherit;font-weight:600;cursor:pointer;text-decoration:none;text-align:center}
.boton.secundario{background:transparent;color:var(--tinta);border:1px solid var(--borde)}
.gris{color:var(--gris);font-size:.9375rem}
.precio{font-size:1.35rem;font-weight:700}
.oferta{display:flex;justify-content:space-between;gap:16px;flex-wrap:wrap;align-items:center}
.oferta>div:first-child{flex:1 1 240px;min-width:0}
.mensaje{padding:12px;border-radius:8px;margin:0 0 14px;border:1px solid var(--borde)}
.mensaje.error{border-color:var(--rojo);color:var(--rojo)}
.mensaje.bien{border-color:var(--verde);color:var(--verde)}
.casilla{display:flex;gap:10px;align-items:flex-start;font-weight:400;margin:8px 0}
.casilla input{margin-top:4px;width:18px;height:18px;flex:none}
table{width:100%;border-collapse:collapse}
td,th{padding:6px 0;text-align:left;border-bottom:1px solid var(--borde);vertical-align:top}
td.num,th.num{text-align:right;white-space:nowrap}
tr.total td{font-weight:700;border-bottom:0}
.oculto{position:absolute;left:-9999px}
footer{margin-top:24px;font-size:.8125rem;color:var(--gris)}
.idiomas{display:flex;flex-wrap:wrap;gap:4px 12px;margin:-10px 0 16px;font-size:.8125rem}
.idiomas a{color:var(--gris);text-decoration:none;padding:4px 0}
.idiomas a[aria-current]{color:var(--tinta);font-weight:700}
footer a{color:inherit}
.galeria{display:flex;gap:8px;overflow-x:auto;scroll-snap-type:x mandatory;margin:0 0 14px;padding-bottom:6px}
.galeria a{flex:0 0 82%;max-width:420px;scroll-snap-align:start}
.galeria img{display:block;width:100%;height:220px;object-fit:cover;border-radius:10px;background:var(--borde)}
.galeria:focus-visible{outline:3px solid var(--marca);outline-offset:2px}
.plano{position:relative;overflow:hidden;border-radius:8px}
.plano img{display:block;width:100%;height:auto}
.lugar{position:absolute;min-width:32px;min-height:32px;padding:0 8px;border-radius:16px;font-size:.8125rem;line-height:32px;text-align:center;text-decoration:none;box-shadow:0 1px 3px rgb(0 0 0 / .35)}
.lugar.libre{background:var(--marca);color:#fff;font-weight:700}
.lugar.ocupado{background:#fff;color:var(--gris)}
.franja{display:grid;grid-template-columns:repeat(30,1fr);gap:1px}
.dia{display:block;height:20px;background:var(--borde)}
.dia.reserva{background:var(--marca)}
.dia.dueno{background:#0b1f2a}
.dia.bloqueo{background:#94a3b8}
.dia.muestra{display:inline-block;width:12px;height:12px;vertical-align:middle;margin-left:8px;border-radius:2px}
.calendario{width:100%;border-collapse:collapse;table-layout:fixed}
.calendario th{font-size:.8125rem;font-weight:600;padding:4px 0;color:var(--gris);text-align:center}
.calendario td{padding:2px;text-align:center}
.calendario td a,.calendario td span{display:block;min-height:44px;line-height:44px;border-radius:6px}
.calendario td a{font-weight:700;color:var(--tinta);border:2px solid var(--marca);line-height:40px;text-decoration:none}
.calendario td.lleno span{background:var(--borde);color:var(--tinta);text-decoration:line-through}
.calendario td.pasado span{color:var(--gris)}
.calendario-muestra{display:inline-block;width:14px;height:14px;vertical-align:middle;margin-left:8px;border-radius:3px;border:1px solid var(--borde)}
.calendario-muestra.libre{border:2px solid var(--marca)}
.calendario-muestra.lleno{background:var(--borde)}
details.filtros{margin-top:12px}
details.filtros summary{cursor:pointer;font-weight:600;min-height:32px}
</style>
@yield('cabeza')
</head>
<body>
<main>
    <header class="marca">
        <h1>{{ $propiedad->nombre }}</h1>
        @if ($propiedad->telefono)<span class="gris">{{ $propiedad->telefono }}</span>@endif
    </header>
    @if ($propiedad->es_demo)
        <p class="mensaje" role="status">{{ __('motor.demo_aviso') }}</p>
    @endif
    @if (request()->isMethod('get'))
        <nav class="idiomas" aria-label="{{ __('motor.idioma') }}">
            @foreach (\App\Support\Formato::idiomas() as $codigo => $nombre)
                <a href="{{ request()->fullUrlWithQuery(['idioma' => $codigo]) }}" lang="{{ $codigo }}" @if ($codigo === app()->getLocale()) aria-current="true" @endif>{{ $nombre }}</a>
            @endforeach
        </nav>
    @endif
    @if (in_array(request()->query('aviso'), ['sin_lugar', 'pasarela', 'nada_que_cobrar'], true))
        <p class="mensaje error" role="alert">{{ __('motor.error.'.request()->query('aviso')) }}</p>
    @endif
    @yield('contenido')
    <footer>
        @include('componentes.registro-turistico', ['registros' => \App\Propiedades\RegistrosTuristicos::visibles($propiedad)])
        {{ __('motor.pie') }} · <a href="{{ route('codigo-fuente') }}">{{ __('ui.pie.codigo_fuente') }}</a>
    </footer>
</main>
</body>
</html>
