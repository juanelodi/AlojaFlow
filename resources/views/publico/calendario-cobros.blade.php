{{-- AlojaFlow · SPDX-License-Identifier: AGPL-3.0-or-later --}}
@use('App\Support\Formato')
@if (! empty($calendario['cuotas']))
    <section class="tarjeta" id="calendario">
        <h2>{{ __('estancias.publico.titulo') }}</h2>
        <table>
            <thead>
                <tr>
                    <th scope="col">{{ __('estancias.publico.periodo') }}</th>
                    <th scope="col" class="num">{{ __('ui.pagos.importe') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($calendario['cuotas'] as $fila)
                    <tr>
                        <td>
                            {{ $fila['cuota']->periodo() }}
                            <br><span class="gris">{{ __('estancias.publico.vence', ['fecha' => Formato::fecha($fila['cuota']->vence_en)]) }}</span>
                        </td>
                        <td class="num">
                            {{ Formato::dinero($fila['cuota']->importe, $reserva->moneda) }}
                            <br><span class="gris">{{ __('estancias.cuotas.estados.'.$fila['estado']) }}</span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </section>
@endif
