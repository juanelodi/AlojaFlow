{{-- AlojaFlow · Copyright (C) 2026 Juan Galeana Durán · SPDX-License-Identifier: FSL-1.1-ALv2 --}}
@use('App\Support\Formato')
<div class="af-pila">
    <p class="af-nota">{{ __('propietarios.acceso.ayuda') }}</p>
    <div>
        <strong>{{ __('grupos.acceso.enlace') }}</strong>
        <p style="overflow-wrap: anywhere"><a class="af-enlace" href="{{ $url }}" target="_blank" rel="noopener">{{ $url }}</a></p>
    </div>
    <div>
        <strong>{{ __('grupos.acceso.clave') }}</strong>
        @if ($clave)
            <p style="font-family: monospace; font-size: 1.125rem">{{ $clave }}</p>
            <p class="af-nota">{{ __('grupos.acceso.clave_visible') }}</p>
        @else
            <p class="af-nota">{{ __('grupos.acceso.clave_cambiada') }}</p>
        @endif
    </div>
    <p class="af-nota">
        {{ $propietario->acceso_activo ? __('grupos.acceso.activo') : __('grupos.acceso.inactivo') }}
        @if ($propietario->ultimo_acceso_en)
            · {{ __('grupos.acceso.ultimo', ['momento' => Formato::momento($propietario->ultimo_acceso_en)]) }}
        @endif
        @if ($propietario->bloqueado_hasta?->isFuture())
            · {{ __('grupos.acceso.bloqueado', ['momento' => Formato::momento($propietario->bloqueado_hasta)]) }}
        @endif
    </p>
    @if ($whatsapp)
        <div><x-filament::button tag="a" color="gray" :href="$whatsapp" target="_blank" rel="noopener">{{ __('grupos.acceso.mandar_whatsapp') }}</x-filament::button></div>
    @endif
</div>
