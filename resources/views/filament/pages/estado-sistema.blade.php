{{-- AlojaFlow · SPDX-License-Identifier: AGPL-3.0-or-later --}}
<x-filament-panels::page>
    <div class="af-pila">
        <x-filament::section>
            <div class="af-tabla-contenedor" tabindex="0" role="region" aria-label="{{ __('ui.estado.titulo') }}">
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

        {{-- Lo pide el navegador, como lo pediría cualquiera desde fuera: desde el servidor, muchos hosting no se ven a sí mismos. --}}
        <x-filament::section :heading="__('ui.estado.expuestos_titulo')">
            <div x-data="{ estado: 'revisando', expuestos: [] }"
                 x-init="Promise.all(['.env', 'composer.json', 'artisan', 'storage/app/private/instalado.json', 'vendor/autoload.php'].map((ruta) => fetch(@js(url('/').'/') + ruta + '?comprobar=' + Date.now(), { credentials: 'omit', cache: 'no-store' }).then((r) => (r.ok && ! r.redirected) ? ruta : null).catch(() => null))).then((rutas) => { expuestos = rutas.filter(Boolean); estado = expuestos.length ? 'mal' : 'bien'; })">
                <p class="af-prosa" x-show="estado === 'revisando'">{{ __('ui.estado.expuestos_revisando') }}</p>
                <p class="af-prosa" x-show="estado === 'bien'" x-cloak>{{ __('ui.estado.expuestos_bien') }}</p>
                <p class="af-aviso af-aviso--error" role="alert" x-show="estado === 'mal'" x-cloak>
                    {{ __('ui.estado.expuestos_mal') }} <span x-text="expuestos.join(', ')"></span>
                </p>
            </div>
        </x-filament::section>

        @if ($this->muestraServidor())
            <x-filament::section :heading="__('ui.estado.cron_titulo')">
                <p class="af-prosa">{{ __('ui.estado.cron_texto') }}</p>
                <pre class="af-comando"><code>{{ $this->comandoCron() }}</code></pre>
                <p class="af-nota">{{ __('ui.estado.cron_nota') }}</p>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
