{{-- AlojaFlow · Copyright (C) 2026 Juan Galeana Durán · SPDX-License-Identifier: FSL-1.1-ALv2 --}}
<p>{{ __('documentos_propios.base.llegadas.intro') }}</p>
<table>
    <tbody>
        <tr>
            <th><p>{{ __('documentos_propios.base.llegadas.llegada') }}</p></th>
            <th><p>{{ __('documentos_propios.base.llegadas.salida') }}</p></th>
            <th><p>{{ __('documentos_propios.base.llegadas.unidad') }}</p></th>
            <th><p>{{ __('documentos_propios.base.llegadas.huesped') }}</p></th>
            <th><p>{{ __('documentos_propios.base.llegadas.personas') }}</p></th>
            <th><p>{{ __('documentos_propios.base.llegadas.pendiente') }}</p></th>
        </tr>
        <tr>
            <td><p>{llegada_corta}</p></td>
            <td><p>{salida_corta}</p></td>
            <td><p>{unidad}</p></td>
            <td><p>{huesped}</p></td>
            <td><p>{personas}</p></td>
            <td><p>{pendiente}</p></td>
        </tr>
    </tbody>
</table>
