{{-- AlojaFlow · Copyright (C) 2026 Juan Galeana Durán · SPDX-License-Identifier: FSL-1.1-ALv2 --}}
@use('App\Enums\CanalEnvio')
@use('App\Enums\EstadoEnvio')
<div class="af-pila">
    @if ($envio->estado === EstadoEnvio::Manual)
        <p class="af-aviso">{{ __('mensajes.envios.manual_ayuda') }}</p>
    @endif
    <p class="af-nota">{{ __('mensajes.envios.destino') }}: {{ $envio->destino ?? '—' }} · {{ $envio->estado->etiqueta() }}</p>
    @if ($envio->ultimo_error)
        <p class="af-aviso af-aviso--error">{{ __('mensajes.envios.error') }}: {{ $envio->ultimo_error }}</p>
    @endif
    @if ($envio->canal !== CanalEnvio::Webhook && $envio->cuerpo)
        <pre class="af-mensaje">{{ $envio->cuerpo }}</pre>
        <div class="af-grupo-botones">
            @if ($envio->canal === CanalEnvio::Correo && $envio->destino)
                <x-filament::button tag="a" color="gray" target="_blank" :href="'mailto:'.rawurlencode($envio->destino).'?subject='.rawurlencode((string) $envio->asunto).'&body='.rawurlencode(mb_substr($envio->cuerpo, 0, 1500))">{{ __('mensajes.envios.abrir_correo') }}</x-filament::button>
            @endif
            <x-filament::button tag="a" color="gray" target="_blank" :href="\App\Filament\Resources\Envios\EnvioResource::enlaceWhatsapp($envio)">{{ __('mensajes.envios.abrir_whatsapp') }}</x-filament::button>
        </div>
    @elseif ($envio->cuerpo)
        <pre class="af-mensaje">{{ $envio->cuerpo }}</pre>
    @endif
</div>
