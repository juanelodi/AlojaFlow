{{-- AlojaFlow · SPDX-License-Identifier: AGPL-3.0-or-later --}}
<x-filament-panels::page>
    <div class="af-pila">
        <div class="af-barra-herramientas">
            @include('filament.partes.selector-propiedad')
            <label class="af-campo">
                <span>{{ __('equipo.aviso.desde') }}</span>
                <x-filament::input.wrapper>
                    <x-filament::input type="date" wire:model.live="desde" />
                </x-filament::input.wrapper>
            </label>
            <label class="af-campo">
                <span>{{ __('equipo.aviso.dias') }}</span>
                <x-filament::input.wrapper>
                    <x-filament::input type="number" min="1" max="{{ \App\Limpieza\AvisoEquipo::DIAS_MAXIMOS }}" wire:model.live.debounce.500ms="dias" />
                </x-filament::input.wrapper>
            </label>
        </div>

        <fieldset class="af-casillas">
            <legend class="af-nota">{{ __('equipo.aviso.anadir') }}</legend>
            @foreach (['nombre', 'personas', 'origen', 'noches'] as $casilla)
                <label class="af-casilla"><x-filament::input.checkbox wire:model.live="{{ $casilla }}" /><span>{{ __('equipo.aviso.'.$casilla) }}</span></label>
            @endforeach
            @if ($this->puedeVerContacto())
                @foreach (['telefono', 'comentario'] as $casilla)
                    <label class="af-casilla"><x-filament::input.checkbox wire:model.live="{{ $casilla }}" /><span>{{ __('equipo.aviso.'.$casilla) }}</span></label>
                @endforeach
            @endif
            <label class="af-casilla"><x-filament::input.checkbox wire:model.live="limpieza" /><span>{{ __('equipo.aviso.mostrar_limpieza') }}</span></label>
        </fieldset>

        <div class="af-pila" x-data="avisoEquipo(@js(__('modulos.pasos.copiado')))">
            <label class="af-campo">
                <span>{{ __('equipo.aviso.texto') }}</span>
                <textarea class="af-aviso-texto" rows="14" wire:model="texto" x-ref="texto"></textarea>
            </label>
            <div class="af-barra-herramientas">
                <label class="af-campo">
                    <span>{{ __('equipo.aviso.telefono_destino') }}</span>
                    <x-filament::input.wrapper>
                        <x-filament::input type="tel" autocomplete="off" x-model="destino" x-on:change="recordar()" placeholder="+52..." />
                    </x-filament::input.wrapper>
                </label>
                <x-filament::button icon="heroicon-m-chat-bubble-left-ellipsis" x-on:click="whatsapp()">{{ __('ui.comun.whatsapp') }}</x-filament::button>
                <x-filament::button color="gray" icon="heroicon-m-clipboard" x-on:click="copiar()"><span x-text="etiqueta">{{ __('modulos.pasos.copiar') }}</span></x-filament::button>
            </div>
        </div>

        <p class="af-nota">
            {{ __('equipo.aviso.omitidos_ayuda') }}
            @if ($url = $this->urlOmitidos())
                <a class="af-enlace" href="{{ $url }}">{{ __('equipo.omisiones.modelos') }}</a>
            @endif
        </p>
    </div>

    @script
    <script>
        Alpine.data('avisoEquipo', (copiado) => ({
            destino: '',
            etiqueta: @js(__('modulos.pasos.copiar')),

            init() {
                // Solo para no volver a escribirlo: se queda en este navegador.
                try {
                    this.destino = localStorage.getItem('alojaflow.aviso.telefono') ?? '';
                } catch (error) {
                    this.destino = '';
                }
            },

            recordar() {
                try {
                    localStorage.setItem('alojaflow.aviso.telefono', this.destino);
                } catch (error) {
                    // Sin almacenamiento, solo hay que volver a escribirlo.
                }
            },

            whatsapp() {
                const numero = this.destino.replace(/\D+/g, '');
                window.open(`https://wa.me/${numero}?text=${encodeURIComponent(this.$refs.texto.value)}`, '_blank', 'noopener');
            },

            copiar() {
                navigator.clipboard?.writeText(this.$refs.texto.value).then(() => {
                    this.etiqueta = copiado;
                });
            },
        }));
    </script>
    @endscript
</x-filament-panels::page>
