{{-- AlojaFlow · SPDX-License-Identifier: AGPL-3.0-or-later --}}
@use('App\Support\Formato')
<x-filament-panels::page>
    @php
        $vista = $this->vistaElegida();
        // Lo que no se va a ver no se calcula: elegido el día, el teléfono deja de cargar miles de celdas en cada cambio.
        $datos = $vista === 'dia' ? ['dias' => [], 'filas' => []] : $this->datos();
        $dias = $datos['dias'];
        $hoy = $this->hoy()->format('Y-m-d');
        $propiedadAnterior = null;
        $variasPropiedades = count($this->propiedadesElegidas()) > 1;
        $gestionables = $this->propiedadesGestionables();
        // Cuánto se ha pagado, en el color y en el detalle, solo con permiso de datos (D-154).
        $conDatos = \App\Seguridad\Acceso::propiedades(\App\Seguridad\Acceso::usuarioActual(), \App\Seguridad\Acceso::DATOS);
        // Un mes con muchas unidades son miles de celdas: lo de cada día se calcula una sola vez.
        $urlNueva = \App\Filament\Resources\Reservas\ReservaResource::getUrl('create');
        $columnas = [];
        foreach ($dias as $i => $dia) {
            $columnas[] = [
                'fecha' => $dia->format('Y-m-d'),
                'salida' => $dia->addDay()->format('Y-m-d'),
                'texto' => Formato::fechaCorta($dia),
                'clases' => \Illuminate\Support\Arr::toCssClasses(['af-cal-dia', 'af-cal-dia--finde' => $dia->isWeekend(), 'af-cal-dia--hoy' => $dia->format('Y-m-d') === $hoy]),
                'posicion' => 'grid-column: '.($i + 2),
            ];
        }
    @endphp
    {{-- Abre por habitaciones. Quien eligió el día lo sigue viendo, porque lo recuerda este navegador (D-143). --}}
    <div class="af-pila af-cal-vistas af-cal-vistas--{{ $vista ?? 'mes' }}"
         x-data="{
             recordar(vista) { try { localStorage.setItem(window.alojaFlowLlave('calendario.vista'), vista) } catch (error) {} },
             recordada() { try { return localStorage.getItem(window.alojaFlowLlave('calendario.vista')) } catch (error) { return null } },
         }"
         x-init="if (! $wire.vista && recordada() === 'dia') { $wire.cambiarVista('dia') }">
        <div class="af-barra-herramientas">
            @include('filament.partes.selector-propiedad')
            <div class="af-campo" style="min-width: 0">
                <span id="af-cal-vista">{{ __('ui.calendario.vista') }}</span>
                <div class="af-segmentado" role="group" aria-labelledby="af-cal-vista">
                    <button type="button" data-vista="dia" wire:click="cambiarVista('dia')" x-on:click="recordar('dia')" aria-pressed="{{ $vista === 'dia' ? 'true' : 'false' }}">{{ __('ui.calendario.dia') }}</button>
                    <button type="button" data-vista="mes" wire:click="cambiarVista('mes')" x-on:click="recordar('mes')" aria-pressed="{{ $vista !== 'dia' ? 'true' : 'false' }}">{{ __('ui.calendario.mes') }}</button>
                </div>
            </div>
            <label class="af-campo af-solo-vista-dia">
                <span>{{ __('ui.hoy.fecha') }}</span>
                <x-filament::input.wrapper>
                    <x-filament::input type="date" :value="$this->diaElegido()->format('Y-m-d')" wire:model.live="dia" />
                </x-filament::input.wrapper>
            </label>
            <div class="af-grupo-botones af-solo-vista-dia">
                <x-filament::button color="gray" icon="heroicon-m-chevron-left" wire:click="moverDia(-1)" :label-sr-only="true" :tooltip="__('ui.comun.anterior')">
                    {{ __('ui.comun.anterior') }}
                </x-filament::button>
                <x-filament::button color="gray" wire:click="irAHoy">{{ __('ui.comun.hoy') }}</x-filament::button>
                <x-filament::button color="gray" icon="heroicon-m-chevron-right" wire:click="moverDia(1)" :label-sr-only="true" :tooltip="__('ui.comun.siguiente')">
                    {{ __('ui.comun.siguiente') }}
                </x-filament::button>
            </div>
            <label class="af-campo af-solo-vista-mes">
                <span>{{ __('ui.calendario.mes') }}</span>
                <x-filament::input.wrapper>
                    <x-filament::input type="month" :value="$this->mes" wire:model.live="mes" />
                </x-filament::input.wrapper>
            </label>
            <div class="af-grupo-botones af-solo-vista-mes">
                <x-filament::button color="gray" icon="heroicon-m-chevron-left" wire:click="moverMes(-1)" :label-sr-only="true" :tooltip="__('ui.comun.anterior')">
                    {{ __('ui.comun.anterior') }}
                </x-filament::button>
                <x-filament::button color="gray" wire:click="irAEsteMes">{{ __('ui.calendario.este_mes') }}</x-filament::button>
                <x-filament::button color="gray" icon="heroicon-m-chevron-right" wire:click="moverMes(1)" :label-sr-only="true" :tooltip="__('ui.comun.siguiente')">
                    {{ __('ui.comun.siguiente') }}
                </x-filament::button>
            </div>
            {{-- Las recuerda este navegador y las aplica alojaflow.js en <html> (D-154): Livewire no las vuelve a pintar. --}}
            <details class="af-cal-opciones af-solo-vista-mes" wire:ignore>
                <summary>{{ __('ui.calendario.opciones') }}</summary>
                <div class="af-cal-opciones__panel">
                    {{-- Por pago solo quien ve los datos de las reservas. --}}
                    @if ($conDatos !== [])
                    <fieldset>
                        <legend>{{ __('ui.calendario.colorear') }}</legend>
                        <label><input type="radio" name="af-cal-colores" value="estado" data-af-cal-opcion="colores" checked> {{ __('ui.calendario.colorear_estado') }}</label>
                        <label><input type="radio" name="af-cal-colores" value="pago" data-af-cal-opcion="colores"> {{ __('ui.calendario.colorear_pago') }}</label>
                    </fieldset>
                    @endif
                    <label><input type="checkbox" data-af-cal-opcion="fijar-unidades" checked> {{ __('ui.calendario.fijar_unidades') }}</label>
                    <label><input type="checkbox" data-af-cal-opcion="fijar-dias" checked> {{ __('ui.calendario.fijar_dias') }}</label>
                    @if (! \App\Demo\ModoDemo::activo())
                        <a href="{{ filament()->getProfileUrl() }}?colores=1">{{ __('ui.calendario.personalizar_colores') }}</a>
                    @endif
                </div>
            </details>
        </div>

        @if ($vista === 'dia')
            <div class="af-solo-vista-dia">
                @include('filament.partes.calendario-dia')
            </div>
        @endif

        @if ($vista !== 'dia')
        <div class="af-solo-vista-mes af-pila">
        <p class="af-nota">{{ implode(' · ', $this->situacion()) }}</p>
        @if ($conDatos !== [])
        <div class="af-leyenda af-leyenda--pago" aria-label="{{ __('ui.calendario.leyenda') }}">
            <span><i class="af-pago--total"></i>{{ __('ui.calendario.pago_total') }}</span>
            <span><i class="af-pago--anticipo"></i>{{ __('ui.calendario.pago_anticipo') }}</span>
            <span><i class="af-pago--sin-anticipo"></i>{{ __('ui.calendario.pago_sin_anticipo') }}</span>
            <span><i class="af-pago--sin-confirmar"></i>{{ __('ui.calendario.pago_sin_confirmar') }}</span>
            <span><i class="af-barra--bloqueo"></i>{{ __('ui.calendario.bloqueo') }}</span>
        </div>
        @endif
        <div @class(["af-leyenda", "af-leyenda--estado" => $conDatos !== []]) aria-label="{{ __('ui.calendario.leyenda') }}">
            <span><i class="af-barra--confirmada"></i>{{ __('ui.calendario.confirmada') }}</span>
            <span><i class="af-barra--sin-confirmar"></i>{{ __('ui.calendario.sin_confirmar') }}</span>
            <span><i class="af-barra--en-casa"></i>{{ __('ui.calendario.en_casa') }}</span>
            <span><i class="af-barra--salio"></i>{{ __('ui.calendario.salio') }}</span>
            <span><i class="af-barra--bloqueo"></i>{{ __('ui.calendario.bloqueo') }}</span>
            @if ($this->haySubordinadas())
                <span class="af-nota">{{ __('ui.comun.solo_consulta') }}</span>
            @endif
            @if ($gestionables !== [])
                <span class="af-nota">{{ __('ui.calendario.ayuda_mover') }}</span>
            @endif
        </div>

        @if ($datos['filas'] === [])
            <x-filament::empty-state :heading="__('ui.calendario.sin_unidades')" icon="heroicon-o-calendar-days" />
        @else
            {{-- Al elegir la llegada, alojaflow.js cambia el texto (D-154). Siempre ocupa su lugar: la cuadrícula no se mueve entre los dos clics. --}}
            @if ($gestionables !== [])
                <p class="af-cal-aviso" data-af-cal-aviso data-texto="{{ __('ui.calendario.elegir_fin') }}" data-inicial="{{ __('ui.calendario.ayuda_nueva') }}" aria-live="polite">{{ __('ui.calendario.ayuda_nueva') }}</p>
            @endif
            {{-- Arrastrar se atiende aquí y no en cada celda: Alpine tendría que recorrer y preparar miles al arrancar. --}}
            <div class="af-calendario"
                 x-data="{
                     moviendo: null,
                     lado: null,
                     {{-- Sobre un texto, target puede ser el propio texto, que no tiene closest. --}}
                     buscar(evento, selector) {
                         return evento.target.closest?.(selector) ?? null;
                     },
                     empezar(evento) {
                         const barra = this.buscar(evento, '[data-reserva]');
                         if (! barra) {
                             {{-- Un enlace se puede arrastrar de fábrica, y un día no se arrastra. --}}
                             if (this.buscar(evento, '.af-cal-dia')) {
                                 evento.preventDefault();
                             }
                             return;
                         }
                         evento.dataTransfer.effectAllowed = 'move';
                         evento.dataTransfer.setData('text/plain', barra.dataset.reserva);
                         {{-- De un extremo cambia las fechas; de en medio, la mueve completa. --}}
                         const lado = this.buscar(evento, '[data-asa]')?.dataset.asa ?? null;
                         setTimeout(() => { this.moviendo = Number(barra.dataset.reserva); this.lado = lado; }, 0);
                     },
                     soltar(evento) {
                         const dia = this.buscar(evento, '[data-fecha]');
                         if (this.moviendo && dia) {
                             if (this.lado) {
                                 this.$wire.cambiarFechas(this.moviendo, this.lado, dia.dataset.fecha);
                             } else {
                                 this.$wire.moverReserva(this.moviendo, Number(dia.parentElement.dataset.unidad), dia.dataset.fecha);
                             }
                         }
                         this.moviendo = null;
                         this.lado = null;
                     },
                 }"
                 :class="moviendo && 'af-arrastrando'"
                 x-on:dragstart="empezar($event)"
                 x-on:dragend="moviendo = null"
                 x-on:dragover="if (buscar($event, '[data-fecha]')) $event.preventDefault()"
                 x-on:drop.prevent="soltar($event)"
                 style="--af-dias: {{ count($dias) }}" role="region" aria-label="{{ __('ui.calendario.titulo') }} {{ Formato::fecha($this->inicioMes(), 'MMMM YYYY') }}" tabindex="0">
                <div class="af-cal-fila af-cal-fila--cabecera">
                    <div class="af-cal-unidad">{{ Formato::fecha($this->inicioMes(), 'MMMM YYYY') }}</div>
                    @foreach ($dias as $i => $dia)
                        <div @class(['af-cal-cabecera-dia', 'af-cal-dia--finde' => $dia->isWeekend(), 'af-cal-dia--hoy' => $dia->format('Y-m-d') === $hoy]) style="grid-column: {{ $i + 2 }}">
                            {{ $dia->locale(app()->getLocale())->isoFormat('dd') }}<strong>{{ $dia->day }}</strong>
                        </div>
                    @endforeach
                </div>

                @foreach ($datos['filas'] as $fila)
                    @php($unidad = $fila['unidad'])
                    @php($gestionable = in_array($unidad->propiedad_id, $gestionables, true))
                    @if ($variasPropiedades && $propiedadAnterior !== $unidad->propiedad_id)
                        @php($propiedadAnterior = $unidad->propiedad_id)
                        <div class="af-cal-fila af-cal-fila--grupo"><div>{{ $unidad->propiedad->nombre }}</div></div>
                    @endif
                    <div class="af-cal-fila" wire:key="unidad-{{ $unidad->id }}" @if ($gestionable) data-unidad="{{ $unidad->id }}" @endif>
                        <div class="af-cal-unidad">
                            {{ $unidad->nombre }}
                            @if ($unidad->capacidad > 0)<span class="af-secundario">{{ $unidad->capacidad }} <span class="af-sr">{{ __('ui.comun.personas') }}</span><span aria-hidden="true">👤</span></span>@endif
                        </div>
                        {{--
                            Solo se puede pulsar en lo que está libre, porque las barras van encima. Lo tapado no es un enlace, pero sí
                            recibe lo que se suelta: al arrastrar, las barras dejan pasar el ratón. Va con PHP directo y sin sangría porque
                            Livewire añade comentarios a cada condición de Blade y los espacios también cuentan: aquí son miles de celdas.
                        --}}
<?php foreach ($columnas as $i => $columna): if (! $gestionable): ?>
<div class="{{ $columna['clases'] }}" style="{{ $columna['posicion'] }}"></div><?php elseif (isset($fila['tapados'][$i + 1])): ?>
<div class="{{ $columna['clases'] }}" style="{{ $columna['posicion'] }}" data-fecha="{{ $columna['fecha'] }}"></div><?php else: ?>
<a href="{{ $urlNueva.'?unidad='.$unidad->id.'&llegada='.$columna['fecha'].'&salida='.$columna['salida'] }}" class="{{ $columna['clases'] }} af-cal-dia--libre" style="{{ $columna['posicion'] }}" data-fecha="{{ $columna['fecha'] }}" title="{{ __('ui.calendario.nueva_en', ['unidad' => $unidad->nombre, 'fecha' => $columna['texto']]) }}"></a><?php endif; endforeach; ?>
                        @foreach ($fila['externos'] as $externo)
                            <div @class(['af-barra', 'af-barra--externo', 'af-barra--sigue-antes' => $externo['sigue_antes'], 'af-barra--sigue-despues' => $externo['sigue_despues']])
                                 style="grid-column: {{ $externo['columna'] + 1 }} / span {{ $externo['ancho'] }}"
                                 title="{{ __('ical.calendario_externo', ['fuente' => $externo['fuente']]) }}">{{ $externo['fuente'] }}</div>
                        @endforeach
                        @foreach ($fila['vinculadas'] as $vinculada)
                            <div @class(['af-barra', 'af-barra--vinculada', 'af-barra--sigue-antes' => $vinculada['sigue_antes'], 'af-barra--sigue-despues' => $vinculada['sigue_despues']])
                                 style="grid-column: {{ $vinculada['columna'] + 1 }} / span {{ $vinculada['ancho'] }}"
                                 title="{{ __('ui.calendario.vinculada', ['unidades' => $vinculada['unidades']]) }}">{{ $vinculada['unidades'] }}</div>
                        @endforeach
                        @foreach ($fila['barras'] as $barra)
                            @php($reserva = $barra['reserva'])
                            @php($ajena = $barra['oculta'] || ! \App\Seguridad\Limites::puedeVer(\App\Seguridad\Acceso::usuarioActual(), $reserva))
                            @php($texto = $barra['oculta'] ? __('permisos.limites.ocupada') : \App\Servicios\Calendario::textoDe($reserva, \App\Seguridad\Acceso::usuarioActual()))
                            @php($pago = ! $ajena && in_array($reserva->propiedad_id, $conDatos, true) ? $barra['pago'] : null)
                            {{-- Sin permiso de datos, la barra no lleva a la ficha: un enlace sin destino no se puede seguir. --}}
                            <a @if (! $ajena && \App\Seguridad\Acceso::puede(\App\Seguridad\Acceso::usuarioActual(), \App\Seguridad\Acceso::DATOS, $reserva->propiedad_id)) href="{{ \App\Filament\Resources\Reservas\ReservaResource::getUrl('view', ['record' => $reserva]) }}" @endif
                               @if (! $ajena && ! $reserva->sincronizada && $gestionable && $reserva->salida_registrada_en === null)
                                   draggable="true"
                                   data-reserva="{{ $reserva->id }}"
                               @endif
                               @class(['af-barra', $barra['clase'], 'af-pago--'.$pago => $pago !== null, 'af-barra--marcada' => $barra['color'], 'af-barra--grupo' => $barra['grupo'], 'af-barra--sigue-antes' => $barra['sigue_antes'], 'af-barra--sigue-despues' => $barra['sigue_despues']])
                               style="grid-column: {{ $barra['columna'] + 1 }} / span {{ $barra['ancho'] }}@if ($barra['color']); --af-marca: {{ $barra['color'] }}@endif @if ($barra['grupo']?->color); --af-grupo: {{ $barra['grupo']->color }}@endif"
                               title="{{ __('ui.calendario.detalle', ['huesped' => $texto, 'noches' => $reserva->noches, 'llegada' => Formato::fechaCorta($reserva->llegada), 'salida' => Formato::fechaCorta($reserva->salida)]) }}@if (! $ajena && $barra['grupo']) · {{ $barra['grupo']->nombre }}@endif @if ($pago !== null) · {{ __('ui.calendario.pagado_de', ['pagado' => Formato::dinero($reserva->pagado, $reserva->moneda), 'total' => Formato::dinero($reserva->total, $reserva->moneda)]) }}@endif"
                            >{{ $texto }}@if (! $ajena && ! $reserva->sincronizada && $gestionable && $reserva->salida_registrada_en === null && $barra['grupo'] === null)@if (! $barra['sigue_antes'] && $reserva->entrada_registrada_en === null)<span class="af-asa af-asa--inicio" draggable="true" data-asa="inicio" title="{{ __('ui.calendario.asa_inicio') }}"></span>@endif @if (! $barra['sigue_despues'])<span class="af-asa af-asa--fin" draggable="true" data-asa="fin" title="{{ __('ui.calendario.asa_fin') }}"></span>@endif @endif</a>
                        @endforeach
                    </div>
                @endforeach
            </div>
        @endif
        </div>
        @endif
    </div>
</x-filament-panels::page>
