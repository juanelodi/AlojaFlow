{{-- AlojaFlow · SPDX-License-Identifier: AGPL-3.0-or-later --}}
@use('App\Support\Formato')
@use('App\Enums\EstadoReserva')
    @if ($ajustes->instrucciones() && $reserva->estado !== EstadoReserva::Cancelada && $reserva->pendiente > 0)
        <section class="tarjeta">
            <h2>{{ __('motor.instrucciones') }}</h2>
            <p style="white-space:pre-line;margin:0">{{ $ajustes->instrucciones() }}</p>
        </section>
    @endif
    @if ($reserva->estado !== EstadoReserva::Cancelada && $ajustes->aceptaComprobantes() && ($reserva->pendiente > 0 || $comprobantes->isNotEmpty()))
        <section class="tarjeta" id="comprobante">
            <h2>{{ __('grupos.comprobante.titulo') }}</h2>
            @if (in_array(request()->query('aviso'), ['comprobante_datos', 'comprobante_imagen'], true))
                <p class="mensaje error" role="alert">{{ __('grupos.comprobante.aviso.'.request()->query('aviso')) }}</p>
            @elseif (request()->query('aviso') === 'comprobante_recibido')
                <p class="mensaje bien" role="status">{{ __('grupos.comprobante.aviso.comprobante_recibido') }}</p>
            @endif
            @if ($reserva->pendiente > 0)
                <p class="gris">{{ __('grupos.comprobante.ayuda') }}</p>
                <form method="post" enctype="multipart/form-data" action="{{ $accionComprobante }}">
                    @if ($conCsrf ?? false)
                        @csrf
                    @endif
                    <div class="rejilla">
                        <div class="campo">
                            <label for="importe">{{ __('ui.pagos.importe') }}</label>
                            <input id="importe" type="number" name="importe" min="0.01" step="any" inputmode="decimal" autocomplete="off" value="{{ \App\Support\Dinero::aDecimal($porCobrar > 0 ? $porCobrar : $reserva->pendiente, $reserva->moneda) }}" required>
                        </div>
                        <div class="campo">
                            <label for="fecha">{{ __('ui.pagos.fecha') }}</label>
                            <input id="fecha" type="date" name="fecha" autocomplete="off" value="{{ $propiedad->hoy()->format('Y-m-d') }}" required>
                        </div>
                        <div class="campo">
                            <label for="referencia">{{ __('ui.pagos.referencia') }} <span class="opcional">({{ __('motor.opcional') }})</span></label>
                            <input id="referencia" type="text" name="referencia" maxlength="100" autocomplete="off">
                        </div>
                    </div>
                    <div class="campo">
                        <label for="archivo-comprobante">{{ __('grupos.comprobante.archivo') }}</label>
                        <input id="archivo-comprobante" type="file" name="comprobante" accept="image/jpeg,image/png,image/webp" required>
                        <div class="gris">{{ __('grupos.comprobante.archivo_ayuda') }}</div>
                    </div>
                    <button type="submit">{{ __('grupos.comprobante.mandar') }}</button>
                </form>
            @endif
            @if ($comprobantes->isNotEmpty())
                <table style="margin-top:14px">
                    @foreach ($comprobantes as $comprobante)
                        <tr>
                            <td>
                                {{ Formato::fecha($comprobante->fecha) }} · {{ Formato::dinero($comprobante->importe, $comprobante->moneda) }}
                                @if ($comprobante->motivo_rechazo)
                                    <br><span class="gris">{{ $comprobante->motivo_rechazo }}</span>
                                @endif
                            </td>
                            <td class="num">{{ $comprobante->estado->etiqueta() }}</td>
                        </tr>
                    @endforeach
                </table>
            @endif
        </section>
    @endif
