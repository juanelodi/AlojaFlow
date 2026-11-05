{{-- AlojaFlow · SPDX-License-Identifier: AGPL-3.0-or-later --}}
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
