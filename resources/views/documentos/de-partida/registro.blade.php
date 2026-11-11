{{-- AlojaFlow · Copyright (C) 2026 Juan Galeana Durán · SPDX-License-Identifier: FSL-1.1-ALv2 --}}
<p>{{ __('documentos_propios.base.registro.intro') }}</p>
<table>
    <tbody>
        <tr>
            <th><p>{{ __('documentos_propios.base.registro.persona') }}</p></th>
            <th><p>{{ __('documentos_propios.base.registro.nacimiento') }}</p></th>
            <th><p>{{ __('documentos_propios.base.registro.nacionalidad') }}</p></th>
            <th><p>{{ __('documentos_propios.base.registro.documento') }}</p></th>
        </tr>
        <tr>
            <td><p>{h_nombre}</p></td>
            <td><p>{h_nacimiento}</p></td>
            <td><p>{h_nacionalidad}</p></td>
            <td><p>{h_documento_tipo} {h_documento}</p></td>
        </tr>
    </tbody>
</table>
<p>{{ __('documentos_propios.base.registro.firma') }}</p>
