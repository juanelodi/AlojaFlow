{{-- AlojaFlow · SPDX-License-Identifier: AGPL-3.0-or-later --}}
<x-filament-panels::page>
    <div class="af-pila">
        <x-filament::section>
            <div class="af-tabla-contenedor">
                <table class="af-tabla">
                    <caption class="af-sr">{{ __('ui.estado.titulo') }}</caption>
                    <tbody>
                        @foreach ($this->filas() as $fila)
                            <tr>
                                <th scope="row">{{ $fila['titulo'] }}</th>
                                <td>
                                    <x-filament::badge :color="match ($fila['estado']) { 'ok' => 'success', 'aviso' => 'warning', default => 'danger' }">{{ $fila['valor'] }}</x-filament::badge>
                                    @if ($fila['ayuda'])
                                        <span class="af-secundario">{{ $fila['ayuda'] }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>

        <x-filament::section :heading="__('ui.estado.cron_titulo')">
            <p class="af-prosa">{{ __('ui.estado.cron_texto') }}</p>
            <pre class="af-comando"><code>{{ $this->comandoCron() }}</code></pre>
            <p class="af-nota">{{ __('ui.estado.cron_nota') }}</p>
        </x-filament::section>
    </div>
</x-filament-panels::page>
