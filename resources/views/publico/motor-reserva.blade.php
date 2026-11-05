{{-- AlojaFlow · SPDX-License-Identifier: AGPL-3.0-or-later --}}
@use('App\Support\Formato')
@use('App\Enums\EstadoReserva')
@extends('publico.layout')
@section('titulo', __('motor.tu_reserva'))
@section('contenido')
    @if ($reserva->estado === EstadoReserva::Cancelada)
        <p class="mensaje error" role="status">{{ __('motor.estado_cancelada') }}</p>
    @elseif ($reserva->estado === EstadoReserva::Confirmada)
        <p class="mensaje bien" role="status">{{ __('motor.estado_confirmada') }}</p>
    @elseif ($porCobrar > 0 && $pasarelas !== [])
        <p class="mensaje" role="status">{{ __('motor.estado_pagar', ['fecha' => Formato::momento($reserva->expira_en, $propiedad->zona_horaria)]) }}</p>
    @else
        <p class="mensaje" role="status">{{ __('motor.estado_pendiente') }}</p>
    @endif

    <section class="tarjeta">
        <h2>{{ __('motor.tu_reserva') }} {{ $reserva->codigo_publico }}</h2>
        <table>
            <tr><th>{{ __('ui.comun.huesped') }}</th><td>{{ $reserva->titular?->nombreCompleto() }}</td></tr>
            <tr><th>{{ __('ui.comun.llegada') }}</th><td>{{ Formato::fecha($reserva->llegada, 'dddd LL') }}</td></tr>
            <tr><th>{{ __('ui.comun.salida') }}</th><td>{{ Formato::fecha($reserva->salida, 'dddd LL') }}</td></tr>
            <tr><th>{{ __('ui.reservas.tarifa') }}</th><td>{{ $reserva->tarifa_nombre }}</td></tr>
            <tr><th>{{ __('ui.comun.personas') }}</th><td>{{ $reserva->personas }}</td></tr>
            @foreach ($reserva->cargos as $cargo)
                <tr><th>{{ $cargo->nombre }}</th><td>{{ Formato::dinero($cargo->importe, $reserva->moneda) }}</td></tr>
            @endforeach
            <tr><th>{{ __('ui.comun.total') }}</th><td><strong>{{ Formato::dinero($reserva->total, $reserva->moneda) }}</strong></td></tr>
            <tr><th>{{ __('ui.comun.pagado') }}</th><td>{{ Formato::dinero($reserva->pagado, $reserva->moneda) }}</td></tr>
            <tr><th>{{ __('ui.comun.pendiente') }}</th><td>{{ Formato::dinero($reserva->pendiente, $reserva->moneda) }}</td></tr>
        </table>
        <p style="margin:12px 0 0"><a class="boton secundario" href="{{ route('motor.pdf', ['codigo' => $propiedad->codigo, 'publico' => $reserva->codigo_publico, 'idioma' => app()->getLocale()]) }}">{{ __('documentos.descargar_confirmacion') }}</a></p>
    </section>

    @if ($porCobrar > 0 && $pasarelas !== [])
        <section class="tarjeta">
            <h2>{{ __('motor.pagar_titulo', ['importe' => Formato::dinero($porCobrar, $reserva->moneda)]) }}</h2>
            <p class="gris">{{ __('motor.pagar_ayuda') }}</p>
            @foreach ($pasarelas as $pasarela)
                <form method="post" action="{{ route('motor.pagar', ['codigo' => $propiedad->codigo, 'publico' => $reserva->codigo_publico, 'pasarela' => $pasarela, 'idioma' => app()->getLocale()]) }}" target="_top" style="display:inline-block;margin:4px 8px 4px 0">
                    <button type="submit">{{ __('motor.pagar_con.'.$pasarela) }}</button>
                </form>
            @endforeach
        </section>
    @endif

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
                <form method="post" enctype="multipart/form-data" action="{{ route('motor.comprobante', ['codigo' => $propiedad->codigo, 'publico' => $reserva->codigo_publico, 'idioma' => app()->getLocale()]) }}">
                    <div class="rejilla">
                        <div class="campo">
                            <label for="importe">{{ __('ui.pagos.importe') }}</label>
                            <input id="importe" type="number" name="importe" min="0.01" step="any" value="{{ \App\Support\Dinero::aDecimal($reserva->pendiente, $reserva->moneda) }}" required>
                        </div>
                        <div class="campo">
                            <label for="fecha">{{ __('ui.pagos.fecha') }}</label>
                            <input id="fecha" type="date" name="fecha" value="{{ $propiedad->hoy()->format('Y-m-d') }}" required>
                        </div>
                        <div class="campo">
                            <label for="referencia">{{ __('ui.pagos.referencia') }}</label>
                            <input id="referencia" type="text" name="referencia" maxlength="100">
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
@endsection
