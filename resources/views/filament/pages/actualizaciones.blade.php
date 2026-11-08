{{-- AlojaFlow · SPDX-License-Identifier: AGPL-3.0-or-later --}}
@use('App\Support\Formato')
@use('App\Enums\EstadoActualizacion')
@use('Illuminate\Support\Number')
@use('Illuminate\Support\Str')
<x-filament-panels::page>
    <div class="af-pila">
        @if (session('actualizacion_ok'))
            <x-filament::callout color="success" icon="heroicon-o-check-circle" :heading="__('actualizaciones.completada', ['version' => session('actualizacion_ok')])" />
        @endif
        @if (session('actualizacion_error'))
            <x-filament::callout color="danger" icon="heroicon-o-x-circle" :heading="__('actualizaciones.fallo')" :description="session('actualizacion_error')" />
        @endif
        @if ($enlace = $this->enlaceParaTerminar())
            <x-filament::callout color="warning" icon="heroicon-o-exclamation-triangle" :heading="__('actualizaciones.pendiente_finalizar')">
                <x-slot name="footer">
                    <x-filament::button tag="a" :href="$enlace" color="warning">{{ __('actualizaciones.finalizar_ahora') }}</x-filament::button>
                </x-slot>
            </x-filament::callout>
        @endif

        <x-filament::section>
            <p class="af-prosa"><strong>{{ __('actualizaciones.version_instalada', ['version' => $this->versionInstalada()]) }}</strong></p>
            <p class="af-nota">{{ __('actualizaciones.explicacion') }}</p>
            @if ($this->bloqueadaPorGit())
                <p class="af-aviso" style="margin-top: .75rem">{{ __('actualizaciones.error.copia_git') }}</p>
            @endif
            <div class="af-barra-herramientas" style="margin-top: 1rem">
                <label class="af-campo" style="flex-direction: row; align-items: center; gap: .5rem; min-width: auto">
                    <x-filament::input.checkbox wire:model.live="avisoActivo" />
                    <span>{{ __('actualizaciones.avisar') }}</span>
                </label>
                <x-filament::button color="gray" icon="heroicon-m-arrow-path" wire:click="buscarAhora" wire:target="buscarAhora">{{ __('actualizaciones.buscar_ahora') }}</x-filament::button>
            </div>
            <p class="af-nota">{{ __('actualizaciones.avisar_ayuda') }}</p>
            <p class="af-nota">{{ $this->estadoConsulta() }}</p>
        </x-filament::section>

        {{-- D-151: lo que se instala solo. --}}
        @php($automatica = $this->automatica())
        @php($ultimoIntento = $automatica->ultimoIntento())
        <x-filament::section :heading="__('actualizaciones.automaticas.titulo')">
            <div class="af-pila">
                <label class="af-campo" style="flex-direction: row; align-items: flex-start; gap: .5rem; min-width: auto">
                    <x-filament::input.checkbox wire:model="automaticas" />
                    <span><strong>{{ __('actualizaciones.automaticas.automaticas') }}</strong><br><span class="af-nota">{{ __('actualizaciones.automaticas.automaticas_ayuda', ['zona' => $zona]) }}</span></span>
                </label>
                <label class="af-campo" style="max-width: 24rem">
                    <span>{{ __('actualizaciones.automaticas.zona') }}</span>
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model="zona">
                            @foreach ($this->zonas() as $opcion)
                                <option value="{{ $opcion }}">{{ $opcion }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </label>
                <label class="af-campo" style="flex-direction: row; align-items: flex-start; gap: .5rem; min-width: auto">
                    <x-filament::input.checkbox wire:model="urgentes" />
                    <span><strong>{{ __('actualizaciones.automaticas.urgentes') }}</strong><br><span class="af-nota">{{ __('actualizaciones.automaticas.urgentes_ayuda') }}</span></span>
                </label>
                @unless ($automatica->puedeInstalarSola())
                    <p class="af-aviso">{{ __('actualizaciones.automaticas.sin_cron') }}</p>
                @endunless
                @if (isset($ultimoIntento['version']))
                    <p class="af-nota">{{ ($ultimoIntento['ok'] ?? false) ? __('actualizaciones.automaticas.ultima_ok', ['version' => $ultimoIntento['version'], 'cuando' => Formato::momento(\Illuminate\Support\Carbon::parse($ultimoIntento['en']))]) : __('actualizaciones.automaticas.ultima_fallo', ['version' => $ultimoIntento['version'], 'error' => (string) ($ultimoIntento['error'] ?? '')]) }}</p>
                @endif
                <div><x-filament::button wire:click="guardarAutomaticas" wire:target="guardarAutomaticas">{{ __('actualizaciones.automaticas.guardar') }}</x-filament::button></div>
            </div>
        </x-filament::section>

        @if ($nueva = $this->versionDisponible())
            <x-filament::section id="version-nueva" :heading="__('actualizaciones.hay_version', ['version' => $nueva['version']])">
                <div class="af-pila">
                    @if ($nueva['notas'] !== '')
                        <div class="af-prosa">{!! Str::markdown($nueva['notas'], ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}</div>
                    @endif
                    @if ($nueva['enlace'] !== null)
                        <p class="af-nota"><a href="{{ $nueva['enlace'] }}" target="_blank" rel="noopener noreferrer">{{ __('actualizaciones.ver_publicacion') }}</a></p>
                    @endif
                    @unless ($this->bloqueadaPorGit())
                        {{-- Cada pedazo es una petición aparte, como las partes de la subida (D-140); lo bajado se queda en el servidor. --}}
                        <div class="af-pila" wire:ignore
                            x-data="descargaPorPedazos({
                                url: @js(route('actualizaciones.descargar-pedazo')),
                                token: @js(csrf_token()),
                                textos: @js([
                                    'bajando' => __('actualizaciones.bajando'),
                                    'terminada' => __('actualizaciones.bajada_terminada'),
                                    'red' => __('actualizaciones.error.descarga_red'),
                                    'recarga' => __('actualizaciones.error.subida_recarga'),
                                ]),
                            })">
                            <div>
                                <x-filament::button icon="heroicon-m-arrow-down-tray" x-on:click="bajar()" x-bind:disabled="estado === 'bajando' || estado === 'revisando'">
                                    <span x-text="estado === 'error' ? @js(__('actualizaciones.reintentar')) : @js(__('actualizaciones.actualizar_ahora'))">{{ __('actualizaciones.actualizar_ahora') }}</span>
                                </x-filament::button>
                            </div>
                            <div x-show="estado === 'bajando'" x-cloak role="status" class="af-subida">
                                <div class="af-subida__barra" aria-hidden="true"><div x-bind:style="`inline-size: ${porcentaje}%`"></div></div>
                                <p class="af-nota" x-text="mensaje"></p>
                            </div>
                            <p x-show="estado === 'revisando'" x-cloak role="status" class="af-esperando">
                                <x-filament::loading-indicator class="af-esperando__icono" />
                                <span x-text="mensaje"></span>
                            </p>
                            <p x-show="estado === 'error'" x-cloak role="alert" class="af-aviso af-aviso--error" x-text="mensaje"></p>
                        </div>
                        <p class="af-nota">{{ __('actualizaciones.actualizar_ahora_nota') }}</p>
                    @endunless
                </div>
            </x-filament::section>
        @endif

        <ol class="af-pasos-barra" aria-label="{{ __('actualizaciones.pasos') }}">
            @foreach ([1 => 'barra_subir', 2 => 'barra_revisar', 3 => 'barra_instalar'] as $numero => $texto)
                <li @class(['af-pasos-barra__paso', 'af-pasos-barra__paso--actual' => $this->pasoActual() === $numero, 'af-pasos-barra__paso--hecho' => $this->pasoActual() > $numero])>
                    {{ __('actualizaciones.'.$texto) }}
                </li>
            @endforeach
        </ol>

        <x-filament::section :heading="__('actualizaciones.paso_subir')">
            {{-- Por partes, con su propia petición cada una (D-072). Se ignora a Livewire: un archivo de 30 MB no cabe en su estado. --}}
            <div class="af-pila" wire:ignore
                x-data="subidaPorPartes({
                    url: @js(route('actualizaciones.subir-parte')),
                    token: @js(csrf_token()),
                    tamano: {{ \App\Actualizacion\SubidaPorPartes::TAMANO_PARTE }},
                    maximo: {{ (int) config('alojaflow.actualizaciones.tamano_maximo_paquete') }},
                    textos: @js([
                        'subiendo' => __('actualizaciones.subiendo'),
                        'terminada' => __('actualizaciones.subida_terminada'),
                        'noZip' => __('actualizaciones.error.no_es_zip'),
                        'grande' => __('actualizaciones.error.demasiado_grande'),
                        'red' => __('actualizaciones.error.subida_red'),
                        'recarga' => __('actualizaciones.error.subida_recarga'),
                    ]),
                })">
                <label class="af-campo">
                    <span>{{ __('actualizaciones.subir') }}</span>
                    <input type="file" accept=".zip,application/zip" x-ref="archivo" x-on:change="elegir($event)" x-bind:disabled="estado === 'subiendo' || estado === 'revisando'" class="af-archivo">
                </label>
                <div>
                    <x-filament::button icon="heroicon-m-arrow-up-tray" x-on:click="subir()" x-bind:disabled="! archivo || estado === 'subiendo' || estado === 'revisando'">
                        <span x-text="estado === 'error' ? @js(__('actualizaciones.reintentar')) : @js(__('actualizaciones.subir_boton'))">{{ __('actualizaciones.subir_boton') }}</span>
                    </x-filament::button>
                </div>
                <div x-show="estado === 'subiendo'" x-cloak role="status" class="af-subida">
                    <div class="af-subida__barra" aria-hidden="true"><div x-bind:style="`inline-size: ${porcentaje}%`"></div></div>
                    <p class="af-nota" x-text="mensaje"></p>
                </div>
                <p x-show="estado === 'revisando'" x-cloak role="status" class="af-esperando">
                    <x-filament::loading-indicator class="af-esperando__icono" />
                    <span x-text="mensaje"></span>
                </p>
                <p x-show="estado === 'error'" x-cloak role="alert" class="af-aviso af-aviso--error" x-text="mensaje"></p>
            </div>
            <p class="af-nota" style="margin-top: .75rem">{{ __('actualizaciones.ftp', ['carpeta' => $this->carpetaEntrada()]) }}</p>
        </x-filament::section>

        <x-filament::section id="paso-revisar" :heading="__('actualizaciones.paso_revisar')">
            @php($disponibles = $this->disponibles())
            @if ($disponibles === [])
                <p class="af-nota">{{ __('actualizaciones.sin_paquetes') }}</p>
            @else
                <div class="af-barra-herramientas">
                    <label class="af-campo" style="min-width: 16rem">
                        <span>{{ __('actualizaciones.paquete') }}</span>
                        <x-filament::input.wrapper>
                            <x-filament::input.select wire:model.live="paquete">
                                @foreach ($disponibles as $nombre)
                                    <option value="{{ $nombre }}">{{ $nombre }}</option>
                                @endforeach
                            </x-filament::input.select>
                        </x-filament::input.wrapper>
                    </label>
                    <label class="af-campo" style="flex-direction: row; align-items: center; gap: .5rem; min-width: auto">
                        <x-filament::input.checkbox wire:model.live="reinstalar" />
                        <span>{{ __('actualizaciones.reinstalar') }}</span>
                    </label>
                    <x-filament::button color="gray" icon="heroicon-m-magnifying-glass" wire:click="revisar" wire:target="revisar">{{ __('actualizaciones.revisar') }}</x-filament::button>
                    {{ $this->eliminarPaqueteAction }}
                </div>
            @endif

            <p wire:loading.flex wire:target="revisar" class="af-esperando" role="status" style="margin-top: 1rem">
                <x-filament::loading-indicator class="af-esperando__icono" />
                <span>{{ __('actualizaciones.revisando') }}</span>
            </p>

            @if ($r = $this->revision)
                <div class="af-pila" style="margin-top: 1rem">
                    @foreach ($r['errores'] as $error)
                        <p class="af-aviso af-aviso--error" role="alert">{{ $error }}</p>
                    @endforeach
                    @foreach ($r['avisos'] as $aviso)
                        <p class="af-aviso">{{ $aviso }}</p>
                    @endforeach

                    @if ($r['valida'])
                        <dl class="af-kpis">
                            <div class="af-kpi"><dt>{{ __('actualizaciones.de_a') }}</dt><dd>{{ $r['version_actual'] }} → {{ $r['version'] }}</dd></div>
                            <div class="af-kpi"><dt>{{ __('actualizaciones.nuevos') }}</dt><dd>{{ $r['nuevos'] }}</dd></div>
                            <div class="af-kpi"><dt>{{ __('actualizaciones.cambiados') }}</dt><dd>{{ $r['cambiados'] }}</dd></div>
                            <div class="af-kpi"><dt>{{ __('actualizaciones.borrar') }}</dt><dd>{{ $r['borrar'] }}</dd></div>
                            <div class="af-kpi"><dt>{{ __('actualizaciones.iguales') }}</dt><dd>{{ $r['iguales'] }}</dd></div>
                            <div class="af-kpi"><dt>{{ __('actualizaciones.tamano') }}</dt><dd>{{ Number::fileSize((int) $r['bytes'], 1) }}</dd></div>
                        </dl>
                        @if ($r['notas'])
                            <div class="af-prosa">{!! Str::markdown((string) $r['notas'], ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}</div>
                        @endif
                        <p class="af-nota">{{ __('actualizaciones.que_hara') }}</p>
                        <p class="af-aviso" role="status">{{ __('actualizaciones.lista_para_instalar', ['version' => $r['version']]) }}</p>
                        <div>{{ $this->actualizarAction }}</div>
                    @endif
                </div>
            @endif
        </x-filament::section>

        <x-filament::section :heading="__('actualizaciones.historial')">
            @php($historial = $this->historial())
            @if ($historial->isEmpty())
                <p class="af-nota">{{ __('actualizaciones.sin_historial') }}</p>
            @else
                <div class="af-tabla-contenedor" tabindex="0" role="region" aria-label="{{ __('actualizaciones.historial') }}">
                    <table class="af-tabla">
                        <caption class="af-sr">{{ __('actualizaciones.historial') }}</caption>
                        <thead>
                            <tr>
                                <th scope="col">{{ __('actualizaciones.fecha') }}</th>
                                <th scope="col">{{ __('actualizaciones.de_a') }}</th>
                                <th scope="col">{{ __('actualizaciones.estado') }}</th>
                                <th scope="col">{{ __('actualizaciones.usuario') }}</th>
                                <th scope="col">{{ __('actualizaciones.detalle') }}</th>
                                <th scope="col">{{ __('actualizaciones.copia_bd') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($historial as $a)
                                <tr wire:key="actualizacion-{{ $a->id }}">
                                    <td data-orden="{{ $a->iniciada_en ?? $a->creado_en?->format('Y-m-d H:i:s') }}">{{ Formato::momento($a->iniciada_en ?? $a->creado_en) }}</td>
                                    <td>{{ $a->version_anterior }} → {{ $a->version_nueva }}</td>
                                    <td>
                                        <x-filament::badge :color="match ($a->estado) {
                                            EstadoActualizacion::Completada => 'success',
                                            EstadoActualizacion::Fallida, EstadoActualizacion::Revertida => 'danger',
                                            default => 'warning',
                                        }">{{ $a->estado->etiqueta() }}</x-filament::badge>
                                    </td>
                                    <td>{{ $a->usuario?->name ?? '—' }}</td>
                                    <td>{{ $a->detalle ? Str::limit($a->detalle, 160) : '—' }}</td>
                                    <td>
                                        @if ($this->tieneCopia($a))
                                            <a href="{{ route('actualizaciones.copia-base-datos', $a) }}">{{ __('actualizaciones.descargar') }}</a>
                                        @else
                                            —
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>
    </div>

    @script
    <script>
        Alpine.data('descargaPorPedazos', ({ url, token, textos }) => ({
            estado: 'libre',
            porcentaje: 0,
            mensaje: '',

            async bajar() {
                this.estado = 'bajando';
                this.mostrar(0);
                // Pulsar otra vez sigue desde donde iba: lo que ya bajó está en el servidor.
                while (true) {
                    const respuesta = await this.pedir();
                    if (! respuesta?.ok) {
                        return this.fallo(respuesta?.error ?? textos.red);
                    }
                    this.mostrar(respuesta.total > 0 ? Math.floor((100 * respuesta.llevamos) / respuesta.total) : 0);
                    if (respuesta.paquete) {
                        return this.terminar(respuesta.paquete);
                    }
                }
            },

            mostrar(porcentaje) {
                this.porcentaje = porcentaje;
                this.mensaje = textos.bajando.replace(':porcentaje', porcentaje);
            },

            async pedir() {
                // Un corte de red o un hosting ocupado no debe tirar la descarga: dos intentos más antes de avisar.
                for (let intento = 0; intento < 3; intento++) {
                    try {
                        const respuesta = await fetch(url, {
                            method: 'POST',
                            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': token },
                            credentials: 'same-origin',
                        });
                        const contenido = await respuesta.json().catch(() => null);
                        if (contenido !== null && (respuesta.ok || respuesta.status === 422)) {
                            return contenido;
                        }
                        if ([401, 403, 419].includes(respuesta.status)) {
                            return { ok: false, error: textos.recarga };
                        }
                    } catch (error) {
                        // Se reintenta abajo.
                    }
                    await new Promise((listo) => setTimeout(listo, 1500 * (intento + 1)));
                }

                return null;
            },

            async terminar(paquete) {
                this.estado = 'revisando';
                this.mensaje = textos.terminada;
                try {
                    await $wire.subidaTerminada(paquete);
                    this.estado = 'libre';
                    document.getElementById('paso-revisar')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                } catch (error) {
                    // El zip ya está arriba: al recargar sale en la lista.
                    this.fallo(textos.recarga);
                }
            },

            fallo(texto) {
                this.estado = 'error';
                this.mensaje = texto;
            },
        }));

        Alpine.data('subidaPorPartes', ({ url, token, tamano, maximo, textos }) => ({
            archivo: null,
            estado: 'libre',
            porcentaje: 0,
            mensaje: '',
            subida: null,
            siguiente: 0,

            elegir(evento) {
                this.archivo = evento.target.files[0] ?? null;
                this.subida = null;
                this.siguiente = 0;
                this.estado = 'libre';
            },

            async subir() {
                if (! this.archivo) {
                    return;
                }
                if (! this.archivo.name.toLowerCase().endsWith('.zip')) {
                    return this.fallo(textos.noZip);
                }
                if (this.archivo.size > maximo) {
                    return this.fallo(textos.grande);
                }
                // Reintentar sigue desde la parte que falló: en una red mala, empezar de cero no terminaría nunca.
                this.subida ??= Array.from(crypto.getRandomValues(new Uint8Array(16)), (b) => b.toString(16).padStart(2, '0')).join('');
                const total = Math.max(1, Math.ceil(this.archivo.size / tamano));
                this.estado = 'subiendo';
                for (let indice = this.siguiente; indice < total; indice++) {
                    this.porcentaje = Math.round((100 * indice) / total);
                    this.mensaje = textos.subiendo.replace(':porcentaje', this.porcentaje);
                    const respuesta = await this.mandar(indice, total);
                    if (! respuesta?.ok) {
                        return this.fallo(respuesta?.error ?? textos.red);
                    }
                    this.siguiente = indice + 1;
                    if (respuesta.paquete) {
                        return this.terminar(respuesta.paquete);
                    }
                }
            },

            async mandar(indice, total) {
                const datos = new FormData();
                datos.append('_token', token);
                datos.append('subida', this.subida);
                datos.append('nombre', this.archivo.name);
                datos.append('indice', indice);
                datos.append('total', total);
                datos.append('parte', this.archivo.slice(indice * tamano, (indice + 1) * tamano), 'parte.bin');
                // Un corte de red o un hosting ocupado no debe tirar una subida de 30 MB: dos intentos más antes de avisar.
                for (let intento = 0; intento < 3; intento++) {
                    try {
                        const respuesta = await fetch(url, { method: 'POST', body: datos, headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                        const contenido = await respuesta.json().catch(() => null);
                        if (contenido !== null && (respuesta.ok || respuesta.status === 422)) {
                            return contenido;
                        }
                        // Sesión caducada o sin permiso: reintentar no lo arregla.
                        if ([401, 403, 419].includes(respuesta.status)) {
                            return { ok: false, error: textos.recarga };
                        }
                    } catch (error) {
                        // Se reintenta abajo.
                    }
                    await new Promise((listo) => setTimeout(listo, 1500 * (intento + 1)));
                }

                return null;
            },

            async terminar(paquete) {
                this.estado = 'revisando';
                this.mensaje = textos.terminada;
                this.$refs.archivo.value = '';
                this.archivo = null;
                this.subida = null;
                this.siguiente = 0;
                try {
                    await $wire.subidaTerminada(paquete);
                    this.estado = 'libre';
                    document.getElementById('paso-revisar')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                } catch (error) {
                    // El zip ya está arriba: al recargar sale en la lista.
                    this.fallo(textos.recarga);
                }
            },

            fallo(texto) {
                this.estado = 'error';
                this.mensaje = texto;
            },
        }));
    </script>
    @endscript
</x-filament-panels::page>
