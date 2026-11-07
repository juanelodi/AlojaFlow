{{-- AlojaFlow · SPDX-License-Identifier: AGPL-3.0-or-later --}}
@if ($getRecord()->contactos->isEmpty())
    <p class="af-nota">{{ __('ui.ficha.sin_contactos') }}</p>
@else
    @include('filament.partes.contactos', ['contactos' => $getRecord()->contactos, 'propiedad' => $getRecord()->propiedad])
@endif
