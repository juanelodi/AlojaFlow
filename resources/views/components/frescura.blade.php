{{-- AlojaFlow · SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Antigüedad de los datos de la principal: siempre visible (producto §6, principio 2). --}}
@props(['principal'])
@if ($principal?->fecha_copia)
    @php($minutos = (int) $principal->fecha_copia->diffInMinutes(now()))
    @php($vieja = $minutos > 2 * max(15, $principal->frecuencia_minutos))
    <span @class(['af-frescura', 'af-frescura--vieja' => $vieja]) title="{{ \App\Support\Formato::momento($principal->fecha_copia) }}">
        {{ __('ui.comun.datos_al', ['fecha' => $principal->fecha_copia->locale(app()->getLocale())->diffForHumans()]) }}
    </span>
@else
    <span class="af-frescura af-frescura--vieja">{{ __('ui.comun.sin_sincronizar') }}</span>
@endif
