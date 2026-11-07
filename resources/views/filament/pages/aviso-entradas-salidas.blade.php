{{-- AlojaFlow · SPDX-License-Identifier: AGPL-3.0-or-later --}}
<x-filament-panels::page>
    <div class="af-pila">
        <div class="af-barra-herramientas">
            @include('filament.partes.selector-propiedad')
            <label class="af-campo">
                <span>{{ __('equipo.aviso.desde') }}</span>
                <x-filament::input.wrapper>
                    <x-filament::input type="date" :value="$desde" wire:model.live="desde" data-af-rango-inicio="aviso" />
                </x-filament::input.wrapper>
            </label>
            <label class="af-campo">
                <span>{{ __('equipo.aviso.hasta') }}</span>
                <x-filament::input.wrapper>
                    <x-filament::input type="date" :value="$hasta" wire:model.live="hasta" data-af-rango-fin="aviso" />
                </x-filament::input.wrapper>
            </label>
            <div class="af-grupo-botones" role="group" aria-label="{{ __('equipo.aviso.rapidos') }}">
                <x-filament::button color="gray" size="sm" wire:click="rango(1)">{{ __('equipo.aviso.un_dia') }}</x-filament::button>
                <x-filament::button color="gray" size="sm" wire:click="rango(7)">{{ trans_choice('equipo.aviso.n_dias', 7) }}</x-filament::button>
                <x-filament::button color="gray" size="sm" wire:click="rango(30)">{{ trans_choice('equipo.aviso.n_dias', 30) }}</x-filament::button>
            </div>
        </div>
        @if ($excedido)
            <p class="af-aviso" role="status">{{ __('equipo.aviso.maximo', ['dias' => \App\Limpieza\AvisoEquipo::DIAS_MAXIMOS]) }}</p>
        @endif

        <div class="af-aviso-rejilla">
            <div class="af-pila">
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

                <details class="af-plegable" open>
                    <summary>{{ __('equipo.aviso.formato') }}</summary>
                    <div class="af-formato">
                        <label class="af-campo af-formato__ancho">
                            <span>{{ __('equipo.aviso.fecha_formato') }}</span>
                            <x-filament::input.wrapper>
                                <x-filament::input.select wire:model.live="formato.fecha">
                                    @foreach ($this->ejemplosFecha() as $clave => $ejemplo)
                                        <option value="{{ $clave }}" @selected(($formato['fecha'] ?? '') === $clave)>{{ $ejemplo }}</option>
                                    @endforeach
                                </x-filament::input.select>
                            </x-filament::input.wrapper>
                        </label>
                        @foreach (['icono_dia' => \App\Limpieza\FormatoAviso::ICONOS_DIA, 'icono_salen' => \App\Limpieza\FormatoAviso::ICONOS_SALEN, 'icono_llegan' => \App\Limpieza\FormatoAviso::ICONOS_LLEGAN, 'icono_limpieza' => \App\Limpieza\FormatoAviso::ICONOS_LIMPIEZA] as $campo => $iconos)
                            <label class="af-campo">
                                <span>{{ __('equipo.aviso.'.$campo) }}</span>
                                <x-filament::input.wrapper>
                                    <x-filament::input.select wire:model.live="formato.{{ $campo }}">
                                        @foreach ($iconos as $icono)
                                            <option value="{{ $icono }}" @selected(($formato[$campo] ?? '') === $icono)>{{ $icono === '' ? __('equipo.aviso.sin_icono') : $icono }}</option>
                                        @endforeach
                                    </x-filament::input.select>
                                </x-filament::input.wrapper>
                            </label>
                        @endforeach
                        <label class="af-campo">
                            <span>{{ __('equipo.aviso.separador') }}</span>
                            <x-filament::input.wrapper>
                                <x-filament::input.select wire:model.live="formato.separador">
                                    @foreach (\App\Limpieza\FormatoAviso::SEPARADORES as $separador)
                                        <option value="{{ $separador }}" @selected(($formato['separador'] ?? '') === $separador)>{{ __('equipo.aviso.separadores.'.$separador) }}</option>
                                    @endforeach
                                </x-filament::input.select>
                            </x-filament::input.wrapper>
                        </label>
                        <label class="af-campo">
                            <span>{{ __('equipo.aviso.unidades') }}</span>
                            <x-filament::input.wrapper>
                                <x-filament::input.select wire:model.live="formato.unidades">
                                    @foreach (\App\Limpieza\FormatoAviso::UNIDADES as $unidades)
                                        <option value="{{ $unidades }}" @selected(($formato['unidades'] ?? '') === $unidades)>{{ __('equipo.aviso.unidades_'.$unidades) }}</option>
                                    @endforeach
                                </x-filament::input.select>
                            </x-filament::input.wrapper>
                        </label>
                        <label class="af-campo">
                            <span>{{ __('equipo.aviso.orden') }}</span>
                            <x-filament::input.wrapper>
                                <x-filament::input.select wire:model.live="formato.orden">
                                    @foreach (\App\Limpieza\FormatoAviso::ORDENES as $orden)
                                        <option value="{{ $orden }}" @selected(($formato['orden'] ?? '') === $orden)>{{ __('equipo.aviso.orden_'.$orden) }}</option>
                                    @endforeach
                                </x-filament::input.select>
                            </x-filament::input.wrapper>
                        </label>
                        @foreach (['salen', 'llegan', 'limpieza'] as $titulo)
                            <label class="af-campo">
                                <span>{{ __('equipo.aviso.texto_'.$titulo) }}</span>
                                <x-filament::input.wrapper>
                                    <x-filament::input type="text" maxlength="30" :value="$formato['texto_'.$titulo] ?? ''" wire:model.live.debounce.500ms="formato.texto_{{ $titulo }}" placeholder="{{ __('equipo.aviso.'.$titulo) }}" />
                                </x-filament::input.wrapper>
                            </label>
                        @endforeach
                        <label class="af-casilla af-formato__ancho"><x-filament::input.checkbox wire:model.live="formato.negritas" /><span>{{ __('equipo.aviso.negritas') }}</span></label>
                    </div>
                    <div class="af-grupo-botones">
                        <x-filament::button size="sm" wire:click="guardarFormato">{{ __('equipo.aviso.guardar_formato') }}</x-filament::button>
                        <x-filament::button size="sm" color="gray" wire:click="restablecerFormato">{{ __('equipo.aviso.restablecer') }}</x-filament::button>
                    </div>
                    <p class="af-nota">{{ __('equipo.aviso.formato_ayuda') }}</p>
                </details>
            </div>

            <div class="af-pila" x-data="avisoEquipo(@js(__('modulos.pasos.copiado')))">
                <label class="af-campo">
                    <span>{{ __('equipo.aviso.texto') }}</span>
                    <textarea class="af-aviso-texto" rows="18" wire:model="texto" x-ref="texto"></textarea>
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
