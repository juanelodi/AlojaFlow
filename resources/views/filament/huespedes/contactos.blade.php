{{-- AlojaFlow · Copyright (C) 2026 Juan Galeana Durán · SPDX-License-Identifier: FSL-1.1-ALv2 --}}
@if ($getRecord()->contactos->isEmpty())
    <p class="af-nota">{{ __('ui.ficha.sin_contactos') }}</p>
@else
    @include('filament.partes.contactos', ['contactos' => $getRecord()->contactos, 'propiedad' => $getRecord()->propiedad])
@endif
