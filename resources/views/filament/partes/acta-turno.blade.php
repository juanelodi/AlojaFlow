{{-- AlojaFlow · SPDX-License-Identifier: AGPL-3.0-or-later --}}
@use('App\Enums\MetodoPago')
@use('App\Support\Formato')
@php($moneda = $turno->moneda)
@php($zona = $turno->propiedad->zona_horaria)
@php($resumen = $turno->resumen ?? [])
<dl class="af-kpis">
    <div class="af-kpi"><dt>{{ __('caja.turnos.abierto') }}</dt><dd>{{ Formato::momento($turno->abierto_en, $zona) }}</dd></div>
    <div class="af-kpi"><dt>{{ __('caja.turnos.abrio') }}</dt><dd>{{ $turno->abridor?->name ?? '—' }}</dd></div>
    <div class="af-kpi"><dt>{{ __('caja.turnos.cerrado') }}</dt><dd>{{ Formato::momento($turno->cerrado_en, $zona) }}</dd></div>
    <div class="af-kpi"><dt>{{ __('caja.turnos.cerro') }}</dt><dd>{{ $turno->cerrador?->name ?? '—' }}</dd></div>
    <div class="af-kpi"><dt>{{ __('caja.turnos.fondo') }}</dt><dd>{{ Formato::dinero($turno->fondo_inicial, $moneda) }}</dd></div>
    <div class="af-kpi"><dt>{{ __('caja.turnos.entradas') }}</dt><dd>{{ Formato::dinero((int) ($resumen['entradas'] ?? 0), $moneda) }}</dd></div>
    <div class="af-kpi"><dt>{{ __('caja.turnos.salidas') }}</dt><dd>{{ Formato::dinero((int) ($resumen['salidas'] ?? 0), $moneda) }}</dd></div>
    <div class="af-kpi"><dt>{{ __('caja.turnos.esperado') }}</dt><dd>{{ Formato::dinero((int) $turno->esperado, $moneda) }}</dd></div>
    {{-- Lo que se recibió en efectivo en la segunda moneda, para contarlo aparte (D-157). --}}
    @foreach ((array) ($resumen['otras_monedas'] ?? []) as $otra => $importe)
        <div class="af-kpi"><dt>{{ __('dinero.segunda.efectivo_en', ['moneda' => $otra]) }}</dt><dd>{{ Formato::dinero((int) $importe, (string) $otra) }}</dd></div>
    @endforeach
    <div class="af-kpi"><dt>{{ __('caja.turnos.contado') }}</dt><dd>{{ Formato::dinero((int) $turno->contado, $moneda) }}</dd></div>
    <div class="af-kpi"><dt>{{ __('caja.turnos.diferencia') }}</dt><dd>{{ Formato::dinero((int) $turno->diferencia, $moneda) }}</dd></div>
    @foreach ((array) ($resumen['por_metodo'] ?? []) as $metodo => $importe)
        <div class="af-kpi"><dt>{{ MetodoPago::tryFrom((string) $metodo)?->etiqueta() ?? $metodo }}</dt><dd>{{ Formato::dinero((int) $importe, $moneda) }}</dd></div>
    @endforeach
</dl>
@if ($turno->notas)
    <p style="margin-top: 1rem; white-space: pre-line">{{ $turno->notas }}</p>
@endif
