{{-- AlojaFlow · SPDX-License-Identifier: AGPL-3.0-or-later --}}
@use('App\Enums\TipoContacto')
@php($propiedad = $getRecord()->propiedad)
@if ($getRecord()->contactos->isEmpty())
    <p class="af-nota">{{ __('ui.ficha.sin_contactos') }}</p>
@else
    <ul class="af-contactos">
        @foreach ($getRecord()->contactos as $contacto)
            <li>
                <span class="af-secundario">{{ $contacto->tipo->etiqueta() }}</span>
                <span class="af-contactos__fila">
                    <span class="af-contactos__valor">{{ $contacto->valor }}</span>
                    <span class="af-iconos">
                        @if ($contacto->tipo === TipoContacto::Telefono)
                            <a href="tel:{{ $contacto->valor_normalizado }}" title="{{ __('ui.comun.llamar') }}" aria-label="{{ __('ui.comun.llamar') }}: {{ $contacto->valor }}">
                                <x-filament::icon icon="heroicon-o-phone" class="h-5 w-5" />
                            </a>
                            @if ($whatsapp = $contacto->enlaceWhatsapp($propiedad->pais))
                                <a href="{{ $whatsapp }}" target="_blank" rel="noopener noreferrer" title="{{ __('ui.comun.whatsapp') }}" aria-label="{{ __('ui.comun.whatsapp') }}: {{ $contacto->valor }}">
                                    <x-filament::icon icon="heroicon-o-chat-bubble-left-ellipsis" class="h-5 w-5" />
                                </a>
                            @endif
                        @elseif ($contacto->tipo === TipoContacto::Correo)
                            <a href="mailto:{{ $contacto->valor }}" title="{{ __('ui.ficha.escribir') }}" aria-label="{{ __('ui.ficha.escribir') }}: {{ $contacto->valor }}">
                                <x-filament::icon icon="heroicon-o-envelope" class="h-5 w-5" />
                            </a>
                        @endif
                    </span>
                </span>
            </li>
        @endforeach
    </ul>
@endif
