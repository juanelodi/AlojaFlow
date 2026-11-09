{{-- AlojaFlow · SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Sistema → Mejora continua (D-150): qué se manda, el permiso y su estado. --}}
@php
    $estado = $this->estado();
@endphp
<x-filament-panels::page>
    <div class="af-pila">
        <x-filament::section :heading="__('mejora.que_se_manda')">
            <div class="af-prosa">
                <p>{{ __('mejora.intro') }}</p>
                <ul>
                    <li>{{ __('mejora.lista.entorno') }}</li>
                    <li>{{ __('mejora.lista.uso') }}</li>
                    <li>{{ __('mejora.lista.errores') }}</li>
                </ul>
                <p><strong>{{ __('mejora.que_no') }}</strong></p>
            </div>
        </x-filament::section>

        @if (\App\MejoraContinua\Permiso::porContrato())
            <x-filament::section>
                <p class="af-prosa">{{ __('mejora.por_contrato') }}</p>
            </x-filament::section>
        @else
            <form wire:submit="guardar" class="af-pila">
                {{ $this->form }}
                <div><x-filament::button type="submit">{{ __('filament-panels::resources/pages/edit-record.form.actions.save.label') }}</x-filament::button></div>
            </form>
        @endif

        <x-filament::section :heading="__('mejora.estado.titulo')">
            <div class="af-prosa">
                @if (! $estado['receptor'])
                    <p>{{ __('mejora.estado.sin_receptor') }}</p>
                @elseif (! $estado['decidido'])
                    <p>{{ __('mejora.estado.sin_decidir') }}</p>
                @elseif (! $estado['activa'])
                    <p>{{ __('mejora.estado.apagado') }}</p>
                @else
                    <p>{{ $estado['ultimo'] === null ? __('mejora.estado.nunca') : __('mejora.estado.ultimo_ok', ['cuando' => $estado['ultimo']]) }}</p>
                    @if ($estado['fallo'] !== null)
                        <p>{{ __('mejora.estado.ultimo_fallo', ['motivo' => $estado['fallo']]) }}</p>
                    @endif
                @endif
                @if ($estado['uuid'] !== null)
                    <p class="af-secundario">{{ __('mejora.estado.identificador', ['uuid' => $estado['uuid']]) }}</p>
                @endif
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
