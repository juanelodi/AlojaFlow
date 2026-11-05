{{-- AlojaFlow · SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Eligió pagar en OXXO o por transferencia: falta que lo haga. La ficha la da la pasarela. --}}
@use('App\Support\Formato')
@use('Illuminate\Support\Carbon')
<section class="tarjeta">
    <h2>{{ __('motor.espera.titulo') }}</h2>
    <p class="gris">
        {{ __('motor.espera.texto') }}
        @if (is_numeric($espera->datos['vence'] ?? null))
            {{ __('motor.espera.hasta', ['fecha' => Formato::momento(Carbon::createFromTimestamp((int) $espera->datos['vence']), $propiedad->zona_horaria)]) }}
        @endif
    </p>
    @if (is_string($espera->datos['instrucciones'] ?? null))
        <a class="boton" href="{{ $espera->datos['instrucciones'] }}" target="_blank" rel="noopener">{{ __('motor.espera.boton') }}</a>
    @endif
</section>
