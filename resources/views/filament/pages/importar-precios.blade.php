{{-- AlojaFlow · SPDX-License-Identifier: AGPL-3.0-or-later --}}
@use('App\Reservas\ImportadorPrecios')
@use('App\Support\Formato')
<x-filament-panels::page>
    <div class="af-pila">
        <x-filament::section>
            <p class="af-nota" style="margin-block-end: .75rem">{{ __('operacion.precios.ayuda') }}</p>
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
                <x-filament::button color="gray" icon="heroicon-m-arrow-down-tray" wire:click="descargarPlantilla" :disabled="$this->propiedadElegida() === null">{{ __('operacion.precios.plantilla') }}</x-filament::button>
            </div>

            {{-- Lee el archivo aquí: Excel en Windows guarda en otra codificación, y el servidor recibe solo texto. --}}
            <div style="margin-block-start: 1rem" x-data="{
                error: false,
                leer(archivo) {
                    this.error = false;
                    if (! archivo) { return; }
                    if (archivo.size > {{ ImportadorPrecios::BYTES_MAXIMOS }}) { this.error = true; return; }
                    archivo.arrayBuffer().then((bytes) => {
                        const inicio = new Uint8Array(bytes.slice(0, 2));
                        let texto;
                        if (inicio[0] === 0xFF && inicio[1] === 0xFE) {
                            texto = new TextDecoder('utf-16le').decode(bytes);
                        } else {
                            try { texto = new TextDecoder('utf-8', { fatal: true }).decode(bytes); } catch (e) { texto = new TextDecoder('windows-1252').decode(bytes); }
                        }
                        $wire.set('texto', texto);
                    });
                },
            }">
                <label class="af-campo">
                    <span>{{ __('operacion.precios.archivo') }}</span>
                    <input type="file" accept=".csv,.txt,text/csv,text/plain" x-on:change="leer($event.target.files[0])">
                </label>
                <p class="af-aviso af-aviso--error" role="alert" x-show="error" x-cloak>{{ __('operacion.precios.error_tamano') }}</p>
                <label class="af-campo" style="margin-block-start: .75rem">
                    <span>{{ __('operacion.precios.pegar') }}</span>
                    <x-filament::input.wrapper><textarea class="fi-input" style="width: 100%" rows="8" wire:model.blur="texto" spellcheck="false" placeholder="{{ __('operacion.precios.ejemplo') }}"></textarea></x-filament::input.wrapper>
                </label>
            </div>
            <div style="margin-block-start: 1rem">
                <x-filament::button wire:click="revisar" wire:target="revisar" :disabled="$this->propiedadElegida() === null">{{ __('operacion.precios.revisar') }}</x-filament::button>
            </div>
        </x-filament::section>

        @if ($filas !== [])
            @php($moneda = $this->propiedadElegida()?->moneda ?? 'MXN')
            @php($correctas = count(array_filter($filas, fn (array $f): bool => $f['error'] === null)))
            <x-filament::section :heading="__('operacion.precios.vista_previa')" :description="trans_choice('operacion.precios.resumen', $correctas, ['n' => $correctas, 'total' => count($filas)])">
                <div class="af-tabla-contenedor" tabindex="0" role="region" aria-label="{{ __('operacion.precios.vista_previa') }}">
                    <table class="af-tabla">
                        <caption class="af-sr">{{ __('operacion.precios.vista_previa') }}</caption>
                        <thead>
                            <tr>
                                <th scope="col">{{ __('operacion.precios.linea') }}</th>
                                <th scope="col">{{ __('operacion.precios.col_tarifa') }}</th>
                                <th scope="col">{{ __('operacion.precios.col_desde') }}</th>
                                <th scope="col">{{ __('operacion.precios.col_hasta') }}</th>
                                <th scope="col">{{ __('operacion.precios.col_precio') }}</th>
                                <th scope="col">{{ __('operacion.precios.col_persona') }}</th>
                                <th scope="col">{{ __('ui.comun.estado') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($filas as $fila)
                                <tr wire:key="fila-{{ $fila['linea'] }}">
                                    <td>{{ $fila['linea'] }}</td>
                                    <td>{{ $fila['tarifa'] }}</td>
                                    <td>{{ $fila['desde'] }}</td>
                                    <td>{{ $fila['hasta'] }}</td>
                                    <td>{{ $fila['precio'] === null ? '—' : Formato::dinero($fila['precio'], $moneda) }}</td>
                                    <td>{{ $fila['persona'] === null ? '—' : Formato::dinero($fila['persona'], $moneda) }}</td>
                                    <td @class(['af-vencido' => $fila['error'] !== null])>{{ $fila['error'] ?? __('operacion.precios.correcta') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($correctas > 0)
                    <div style="margin-block-start: 1rem">
                        <x-filament::button color="primary" wire:click="importar" wire:confirm="{{ __('operacion.precios.confirmar') }}" wire:target="importar">{{ __('operacion.precios.importar') }}</x-filament::button>
                    </div>
                @endif
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
