{{-- AlojaFlow · SPDX-License-Identifier: AGPL-3.0-or-later --}}
<x-filament-panels::page>
    <form wire:submit="guardar" class="af-pila">
        {{ $this->form }}
        <div><x-filament::button type="submit">{{ __('filament-panels::resources/pages/edit-record.form.actions.save.label') }}</x-filament::button></div>
    </form>
</x-filament-panels::page>
