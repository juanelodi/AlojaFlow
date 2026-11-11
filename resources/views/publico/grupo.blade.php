{{-- AlojaFlow · Copyright (C) 2026 Juan Galeana Durán · SPDX-License-Identifier: FSL-1.1-ALv2 --}}
@use('App\Enums\EstadoComprobante')
@use('App\Support\Formato')
@extends('publico.layout')
@section('titulo', __('grupos.publico.titulo'))
@section('contenido')
    @if (session('estado'))
        <p class="mensaje bien" role="status">{{ session('estado') }}</p>
    @endif

    <section class="tarjeta">
        <h2>{{ $grupo->nombre }} · {{ $grupo->codigo }}</h2>
        <table>
            <tr><th>{{ __('ui.comun.total') }}</th><td class="num"><strong>{{ Formato::dinero($saldo['total'], $saldo['moneda']) }}</strong></td></tr>
            <tr><th>{{ __('ui.comun.pagado') }}</th><td class="num">{{ Formato::dinero($saldo['pagado'], $saldo['moneda']) }}</td></tr>
            @if ($saldo['por_confirmar'] > 0)
                <tr><th>{{ __('grupos.publico.por_confirmar') }}</th><td class="num">{{ Formato::dinero($saldo['por_confirmar'], $saldo['moneda']) }}</td></tr>
            @endif
            <tr><th>{{ __('ui.comun.pendiente') }}</th><td class="num">{{ Formato::dinero(max(0, $saldo['pendiente']), $saldo['moneda']) }}</td></tr>
        </table>
        <div role="progressbar" aria-valuenow="{{ $progreso }}" aria-valuemin="0" aria-valuemax="100" aria-label="{{ __('grupos.publico.progreso') }}" style="height:10px;border-radius:5px;background:var(--borde);margin-top:12px;overflow:hidden">
            <div style="height:100%;width:{{ $progreso }}%;background:var(--marca)"></div>
        </div>
        <p class="gris" style="margin:6px 0 0">{{ __('grupos.publico.progreso_texto', ['porcentaje' => $progreso]) }}</p>
        <form method="post" action="{{ route('grupo.salir', ['token' => $token, 'idioma' => app()->getLocale()]) }}" style="margin-top:12px">
            @csrf
            <button type="submit" class="boton secundario">{{ __('grupos.publico.salir') }}</button>
        </form>
    </section>

    @if ($calendario['cuotas'] !== [])
        <section class="tarjeta">
            <h2>{{ __('grupos.cuotas.titulo') }}</h2>
            <table>
                @foreach ($calendario['cuotas'] as $fila)
                    <tr>
                        <td>{{ $fila['cuota']->concepto }}<br><span class="gris">{{ Formato::fecha($fila['cuota']->vence_en) }}</span></td>
                        <td class="num">{{ Formato::dinero($fila['cuota']->importe, $saldo['moneda']) }}<br><span class="gris">{{ __('grupos.cuotas.estados.'.$fila['estado']) }}</span></td>
                    </tr>
                @endforeach
            </table>
        </section>
    @endif

    @if ($instrucciones)
        <section class="tarjeta">
            <h2>{{ __('motor.instrucciones') }}</h2>
            <p style="white-space:pre-line;margin:0">{{ $instrucciones }}</p>
        </section>
    @endif

    <section class="tarjeta" id="pagos">
        <h2>{{ __('grupos.publico.pagos_titulo') }}</h2>
        <p class="gris">{{ __('grupos.publico.pagos_ayuda') }}</p>
        @if ($errors->pago->any())
            <div class="mensaje error" role="alert">
                @foreach ($errors->pago->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif
        <form method="post" enctype="multipart/form-data" action="{{ route('grupo.pago', ['token' => $token, 'idioma' => app()->getLocale()]) }}">
            @csrf
            <div class="rejilla">
                <div class="campo">
                    <label for="importe">{{ __('ui.pagos.importe') }}</label>
                    <input id="importe" type="number" name="importe" min="0.01" step="any" value="{{ old('importe') }}" required>
                </div>
                <div class="campo">
                    <label for="fecha">{{ __('ui.pagos.fecha') }}</label>
                    <input id="fecha" type="date" name="fecha" value="{{ old('fecha', $propiedad->hoy()->format('Y-m-d')) }}" required>
                </div>
                <div class="campo">
                    <label for="referencia">{{ __('ui.pagos.referencia') }}</label>
                    <input id="referencia" type="text" name="referencia" value="{{ old('referencia') }}" maxlength="100">
                </div>
            </div>
            <div class="campo">
                <label for="comprobante">{{ __('grupos.comprobante.archivo') }}</label>
                <input id="comprobante" type="file" name="comprobante" accept="image/jpeg,image/png,image/webp" required>
                <div class="gris">{{ __('grupos.comprobante.archivo_ayuda') }}</div>
            </div>
            <button type="submit">{{ __('grupos.publico.pago_mandar') }}</button>
        </form>
        @if ($comprobantes->isNotEmpty())
            <table style="margin-top:14px">
                @foreach ($comprobantes as $comprobante)
                    <tr>
                        <td>
                            {{ Formato::fecha($comprobante->fecha) }} · {{ Formato::dinero($comprobante->importe, $comprobante->moneda) }}
                            @if ($comprobante->archivo_id)
                                · <a href="{{ route('grupo.comprobante', ['token' => $token, 'comprobante' => $comprobante->id, 'idioma' => app()->getLocale()]) }}" target="_blank" rel="noopener">{{ __('grupos.comprobante.ver') }}</a>
                            @endif
                            @if ($comprobante->estado === EstadoComprobante::Rechazado && $comprobante->motivo_rechazo)
                                <br><span class="gris">{{ $comprobante->motivo_rechazo }}</span>
                            @endif
                        </td>
                        <td class="num">{{ $comprobante->estado->etiqueta() }}</td>
                    </tr>
                @endforeach
            </table>
        @endif
    </section>

    <section id="habitaciones">
        <div class="tarjeta">
            <h2>{{ __('grupos.publico.habitaciones_titulo') }}</h2>
            <p class="gris" style="margin:0">
                {{ __('grupos.publico.habitaciones_ayuda') }}
                @if ($grupo->fecha_limite_lista)
                    {{ __($grupo->liberar_sin_responsable ? 'grupos.publico.fecha_limite_libera' : 'grupos.publico.fecha_limite', ['fecha' => Formato::fecha($grupo->fecha_limite_lista)]) }}
                @endif
            </p>
        </div>
        @foreach ($reservas as $reserva)
            @php($bolsa = 'habitacion_'.$reserva->id)
            @php($datos = $reserva->habitacionGrupo)
            <div class="tarjeta" id="habitacion-{{ $reserva->id }}">
                <h2>{{ $reserva->unidad->nombre }}</h2>
                <p class="gris">{{ Formato::fecha($reserva->llegada) }} → {{ Formato::fecha($reserva->salida) }}@if ($reserva->unidad->capacidad > 0) · {{ __('grupos.publico.capacidad', ['personas' => $reserva->unidad->capacidad]) }}@endif</p>
                @if ($errors->getBag($bolsa)->any())
                    <div class="mensaje error" role="alert">
                        @foreach ($errors->getBag($bolsa)->all() as $error)
                            <div>{{ $error }}</div>
                        @endforeach
                    </div>
                @endif
                <form method="post" action="{{ route('grupo.habitacion', ['token' => $token, 'reserva' => $reserva->id, 'idioma' => app()->getLocale()]) }}">
                    @csrf
                    <div class="rejilla">
                        <div class="campo">
                            <label for="responsable-{{ $reserva->id }}">{{ __('grupos.lista.responsable') }}</label>
                            <input id="responsable-{{ $reserva->id }}" type="text" name="responsable" value="{{ $datos?->responsable }}" required maxlength="150">
                        </div>
                        <div class="campo">
                            <label for="personas-{{ $reserva->id }}">{{ __('grupos.lista.personas') }}</label>
                            <input id="personas-{{ $reserva->id }}" type="number" name="personas" min="1" @if ($reserva->unidad->capacidad > 0) max="{{ $reserva->unidad->capacidad }}" @endif value="{{ $reserva->personas }}" required>
                        </div>
                        <div class="campo">
                            <label for="telefono-{{ $reserva->id }}">{{ __('grupos.lista.telefono') }}</label>
                            <input id="telefono-{{ $reserva->id }}" type="tel" name="telefono" value="{{ $datos?->telefono }}" maxlength="50">
                        </div>
                        <div class="campo">
                            <label for="correo-{{ $reserva->id }}">{{ __('grupos.lista.correo') }}</label>
                            <input id="correo-{{ $reserva->id }}" type="email" name="correo" value="{{ $datos?->correo }}" maxlength="150">
                        </div>
                    </div>
                    <div class="campo">
                        <label for="acompanantes-{{ $reserva->id }}">{{ __('grupos.lista.acompanantes') }}</label>
                        <textarea id="acompanantes-{{ $reserva->id }}" name="acompanantes" rows="2" maxlength="2000">{{ $datos?->acompanantes }}</textarea>
                    </div>
                    <button type="submit">{{ __('grupos.publico.guardar') }}</button>
                    @if ($whatsapp[$reserva->id])
                        <a class="boton secundario" href="{{ $whatsapp[$reserva->id] }}" target="_blank" rel="noopener">{{ __('grupos.publico.whatsapp_boton') }}</a>
                    @endif
                </form>
            </div>
        @endforeach
    </section>

    <section class="tarjeta" id="mensajes">
        <h2>{{ __('grupos.mensajes.titulo') }}</h2>
        @foreach ($mensajes as $mensaje)
            <div style="padding:8px 0;border-bottom:1px solid var(--borde)">
                <div class="gris">{{ $mensaje->del_coordinador ? __('grupos.mensajes.tu') : $propiedad->nombre }} · {{ Formato::momento($mensaje->creado_en, $propiedad->zona_horaria) }}</div>
                <div style="white-space:pre-line">{{ $mensaje->texto }}</div>
            </div>
        @endforeach
        @if ($errors->mensaje->any())
            <div class="mensaje error" role="alert" style="margin-top:12px">{{ $errors->mensaje->first() }}</div>
        @endif
        <form method="post" action="{{ route('grupo.mensaje', ['token' => $token, 'idioma' => app()->getLocale()]) }}" style="margin-top:12px">
            @csrf
            <div class="campo">
                <label for="texto">{{ __('grupos.mensajes.texto') }}</label>
                <textarea id="texto" name="texto" rows="3" maxlength="2000" required>{{ old('texto') }}</textarea>
            </div>
            <button type="submit">{{ __('grupos.mensajes.mandar') }}</button>
        </form>
    </section>

    @if ($aceptaciones->isNotEmpty())
        <section class="tarjeta">
            <h2>{{ __('grupos.publico.documentos_titulo') }}</h2>
            <table>
                @foreach ($aceptaciones as $aceptacion)
                    <tr>
                        <td>{{ $aceptacion->documento->titulo }}<br><span class="gris">{{ Formato::momento($aceptacion->aceptada_en, $propiedad->zona_horaria) }}</span></td>
                        <td class="num">
                            @if ($aceptacion->copia_archivo_id)
                                <a href="{{ route('grupo.copia', ['token' => $token, 'aceptacion' => $aceptacion->id, 'idioma' => app()->getLocale()]) }}" target="_blank" rel="noopener">{{ __('grupos.publico.copia') }}</a>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </table>
        </section>
    @endif
@endsection
