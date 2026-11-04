{{-- AlojaFlow · SPDX-License-Identifier: AGPL-3.0-or-later --}}
<x-filament-panels::page>
    <div class="af-pila">
        @php($opciones = $this->opcionesPropiedad())
        @if (count($opciones) > 1)
            <div class="af-barra-herramientas">
                <label class="af-campo">
                    <span>{{ __('ui.comun.propiedad') }}</span>
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="propiedadId">
                            @foreach ($opciones as $id => $nombre)
                                <option value="{{ $id }}">{{ $nombre }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </label>
            </div>
        @endif

        @if ($enlace = $this->enlace())
            <x-filament::section :heading="__('motor.panel.enlace')">
                <p class="af-prosa"><a class="af-enlace" href="{{ $enlace }}" target="_blank" rel="noopener">{{ $enlace }}</a></p>
                <p class="af-nota" style="margin-top: .5rem">{{ __('motor.panel.enlace_ayuda') }}</p>
                <pre class="af-comando"><code>&lt;iframe src="{{ $enlace }}" width="100%" height="900" style="border:0" title="{{ __('motor.titulo') }}"&gt;&lt;/iframe&gt;</code></pre>
                <p class="af-nota">{{ __('motor.panel.propiedad_ayuda') }} {{ __('motor.panel.sin_tarifas') }}</p>
            </x-filament::section>
        @endif

        <form wire:submit="guardar" class="af-pila">
            {{ $this->form }}
            <p class="af-aviso">{{ __('motor.panel.sin_verificar') }}</p>
            <p class="af-nota">
                {{ __('motor.panel.avisos_url', ['url' => $this->urlAviso('stripe')]) }}<br>
                {{ __('motor.panel.avisos_url', ['url' => $this->urlAviso('mercadopago')]) }}
            </p>
            <div><x-filament::button type="submit">{{ __('filament-panels::resources/pages/edit-record.form.actions.save.label') }}</x-filament::button></div>
        </form>
    </div>
</x-filament-panels::page>
