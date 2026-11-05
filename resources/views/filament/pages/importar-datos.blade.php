{{-- AlojaFlow · SPDX-License-Identifier: AGPL-3.0-or-later --}}
@use('App\Reservas\ImportadorDatos')
<x-filament-panels::page>
    {{--
        El texto se queda en el navegador y viaja una vez, cuando se revisa o se importa. En una propiedad de Livewire iría dos veces en cada
        petición, y pasar de 1 MB da un error que no dice nada (D-127). Se lee aquí porque Excel en Windows guarda en otra codificación.
    --}}
    <div class="af-pila" x-data="{
        texto: '',
        revisado: null,
        demasiado: false,
        entrada: null,
        leer(entrada) {
            this.entrada = entrada;
            this.demasiado = false;
            const archivo = entrada.files[0];
            if (! archivo) { return; }
            if (archivo.size > {{ ImportadorDatos::BYTES_MAXIMOS }}) { this.demasiado = true; return; }
            archivo.arrayBuffer().then((bytes) => {
                const inicio = new Uint8Array(bytes.slice(0, 2));
                if (inicio[0] === 0xFF && inicio[1] === 0xFE) { this.texto = new TextDecoder('utf-16le').decode(bytes); return; }
                try { this.texto = new TextDecoder('utf-8', { fatal: true }).decode(bytes); } catch (e) { this.texto = new TextDecoder('windows-1252').decode(bytes); }
            });
        },
        revisar() {
            this.demasiado = new Blob([this.texto]).size > {{ ImportadorDatos::BYTES_MAXIMOS }};
            if (this.demasiado) { return; }
            const texto = this.texto;
            this.$wire.revisar(texto).then(() => { this.revisado = texto; });
        },
        importar() {
            if (! confirm(@js(__('importar.confirmar')))) { return; }
            this.$wire.importar(this.texto).then((quedan) => {
                this.texto = quedan;
                this.revisado = quedan;
                if (this.entrada) { this.entrada.value = ''; }
            });
        },
    }">
        <x-filament::section>
            <p class="af-nota" style="margin-block-end: .75rem">{{ $tipo === ImportadorDatos::RESERVAS ? __('importar.ayuda_reservas') : __('importar.ayuda_huespedes') }}</p>
            <div class="af-barra-herramientas">
                @if (count($this->propiedades()) > 1)
                    <label class="af-campo">
                        <span>{{ __('ui.comun.propiedad') }}</span>
                        <x-filament::input.wrapper>
                            <x-filament::input.select wire:model.live="propiedadId">
                                <option value="">—</option>
                                @foreach ($this->propiedades() as $id => $nombre)
                                    <option value="{{ $id }}">{{ $nombre }}</option>
                                @endforeach
                            </x-filament::input.select>
                        </x-filament::input.wrapper>
                    </label>
                @endif
                <label class="af-campo">
                    <span>{{ __('importar.que') }}</span>
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="tipo">
                            <option value="{{ ImportadorDatos::RESERVAS }}">{{ __('importar.tipo_reservas') }}</option>
                            <option value="{{ ImportadorDatos::HUESPEDES }}">{{ __('importar.tipo_huespedes') }}</option>
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </label>
                <x-filament::button color="gray" icon="heroicon-m-arrow-down-tray" wire:click="descargarPlantilla">{{ __('importar.plantilla') }}</x-filament::button>
            </div>

            <div style="margin-block-start: 1rem">
                <label class="af-campo">
                    <span>{{ __('importar.archivo') }}</span>
                    <input type="file" accept=".csv,.txt,text/csv,text/plain" x-on:change="leer($event.target)">
                </label>
                <p class="af-aviso af-aviso--error" role="alert" x-show="demasiado" x-cloak>{{ __('importar.error_tamano', ['filas' => ImportadorDatos::FILAS_MAXIMAS]) }}</p>
                <label class="af-campo" style="margin-block-start: .75rem">
                    <span>{{ __('importar.pegar') }}</span>
                    <x-filament::input.wrapper><textarea class="fi-input" style="width: 100%" rows="8" x-model="texto" spellcheck="false"></textarea></x-filament::input.wrapper>
                </label>
            </div>
            <div style="margin-block-start: 1rem">
                <x-filament::button x-on:click="revisar()" wire:target="revisar" :disabled="$this->propiedadElegida() === null">{{ __('importar.revisar') }}</x-filament::button>
            </div>
        </x-filament::section>

        @if ($filas !== [])
            @php($correctas = count(array_filter($filas, fn (array $f): bool => $f['error'] === null && $f['aviso'] === null)))
            {{-- Si cambió el texto, esto ya no es lo que se revisó. --}}
            <div x-show="texto === revisado">
                <x-filament::section :heading="__('importar.vista_previa')" :description="trans_choice('importar.resumen', $correctas, ['n' => $correctas, 'total' => count($filas)])">
                    <p class="af-nota" style="margin-block-end: .75rem">{{ __('importar.revisa_lectura') }}</p>
                    <div class="af-tabla-contenedor" tabindex="0" role="region" aria-label="{{ __('importar.vista_previa') }}">
                        <table class="af-tabla">
                            <caption class="af-sr">{{ __('importar.vista_previa') }}</caption>
                            <thead>
                                <tr>
                                    <th scope="col">{{ __('importar.linea') }}</th>
                                    <th scope="col">{{ __('importar.que_trae') }}</th>
                                    <th scope="col">{{ __('ui.comun.estado') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($filas as $fila)
                                    <tr wire:key="fila-{{ $fila['linea'] }}">
                                        <td>{{ $fila['linea'] }}</td>
                                        <td>{{ $fila['resumen'] }}</td>
                                        <td @class(['af-vencido' => $fila['error'] !== null])>{{ $fila['error'] ?? $fila['aviso'] ?? __('importar.correcta') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if ($correctas > 0)
                        <div style="margin-block-start: 1rem">
                            <x-filament::button color="primary" x-on:click="importar()" wire:target="importar">{{ __('importar.importar') }}</x-filament::button>
                        </div>
                    @endif
                </x-filament::section>
            </div>
        @endif
    </div>
</x-filament-panels::page>
