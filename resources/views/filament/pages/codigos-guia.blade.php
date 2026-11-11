{{-- AlojaFlow · Copyright (C) 2026 Juan Galeana Durán · SPDX-License-Identifier: FSL-1.1-ALv2 --}}
<x-filament-panels::page>
    <style>
        @media print {
            .fi-sidebar, .fi-topbar, .af-nota, .af-codigo-acciones { display: none !important; }
            .af-codigo { break-inside: avoid; }
        }
    </style>
    <div class="af-pila">
        <p class="af-nota">{{ __('portal.guia_panel.codigos_ayuda') }}</p>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:1rem">
            @foreach ($this->codigos() as $codigo)
                <x-filament::section class="af-codigo" wire:key="codigo-{{ $codigo['unidad']->id }}">
                    <strong>{{ $codigo['unidad']->nombre }}</strong>
                    <div class="af-nota">{{ $codigo['unidad']->propiedad->nombre }}</div>
                    <div style="background:#fff;padding:.5rem;margin:.5rem 0;border-radius:.5rem">{!! $codigo['qr'] !!}</div>
                    <div class="af-codigo-acciones">
                        <p class="af-prosa" style="overflow-wrap:anywhere;font-size:.75rem"><a class="af-enlace" href="{{ $codigo['url'] }}" target="_blank" rel="noopener">{{ __('portal.guia_panel.abrir') }}</a></p>
                        <x-filament::button color="gray" size="sm" wire:click="cambiar({{ $codigo['unidad']->id }})" wire:confirm="{{ __('portal.guia_panel.cambiar_confirma') }}" style="margin-top:.5rem">{{ __('portal.guia_panel.cambiar') }}</x-filament::button>
                    </div>
                </x-filament::section>
            @endforeach
        </div>
    </div>
</x-filament-panels::page>
