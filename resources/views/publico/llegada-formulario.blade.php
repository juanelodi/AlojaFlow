{{-- AlojaFlow · SPDX-License-Identifier: AGPL-3.0-or-later --}}
@use('App\Support\Formato')
@extends('publico.layout')
@section('titulo', __('llegada.titulo'))
@section('contenido')
    @if ($errors->any())
        <div class="mensaje error" role="alert">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <section class="tarjeta">
        <h2>{{ __('llegada.titulo') }}</h2>
        <p class="gris" style="margin:0">
            {{ __('motor.tu_reserva') }} ·
            {{ Formato::fecha($reserva->llegada, 'ddd D MMM') }} → {{ Formato::fecha($reserva->salida, 'ddd D MMM') }}
        </p>
        <p class="gris" style="margin:8px 0 0">{{ __('llegada.ayuda') }}</p>
    </section>

    <form method="post" action="{{ route('llegada.guardar', ['token' => $token, 'idioma' => app()->getLocale()]) }}" enctype="multipart/form-data">
        @csrf
        <div class="oculto" aria-hidden="true"><label>{{ __('motor.no_llenar') }}<input type="text" name="sitio_web" tabindex="-1" autocomplete="off"></label></div>

        @for ($i = 0; $i < $cuantas; $i++)
            @php($old = fn (string $campo, string $porOmision = ''): string => (string) old("personas.$i.$campo", $i === 0 ? ($titular[$campo] ?? $porOmision) : $porOmision))
            <section class="tarjeta">
                <h2>{{ $i === 0 ? __('llegada.titular') : __('llegada.acompanante', ['n' => $i]) }}</h2>
                <div class="rejilla">
                    <div class="campo"><label for="p{{ $i }}-nombre">{{ __('llegada.nombre') }}</label><input type="text" id="p{{ $i }}-nombre" name="personas[{{ $i }}][nombre]" value="{{ $old('nombre') }}" maxlength="100" required></div>
                    <div class="campo"><label for="p{{ $i }}-apellido1">{{ __('llegada.apellido1') }}</label><input type="text" id="p{{ $i }}-apellido1" name="personas[{{ $i }}][apellido1]" value="{{ $old('apellido1') }}" maxlength="100" required></div>
                    <div class="campo"><label for="p{{ $i }}-apellido2">{{ __('llegada.apellido2') }}</label><input type="text" id="p{{ $i }}-apellido2" name="personas[{{ $i }}][apellido2]" value="{{ $old('apellido2') }}" maxlength="100"></div>
                    <div class="campo"><label for="p{{ $i }}-nacimiento">{{ __('llegada.fecha_nacimiento') }}</label><input type="date" id="p{{ $i }}-nacimiento" name="personas[{{ $i }}][fecha_nacimiento]" value="{{ $old('fecha_nacimiento') }}"></div>
                    <div class="campo"><label for="p{{ $i }}-nacionalidad">{{ __('llegada.nacionalidad') }}</label><input type="text" id="p{{ $i }}-nacionalidad" name="personas[{{ $i }}][nacionalidad_texto]" value="{{ $old('nacionalidad_texto') }}" maxlength="100"></div>
                    <div class="campo">
                        <label for="p{{ $i }}-doctipo">{{ __('llegada.documento_tipo') }}</label>
                        <select id="p{{ $i }}-doctipo" name="personas[{{ $i }}][documento_tipo]" style="width:100%;padding:11px 12px;border:1px solid var(--borde);border-radius:8px;font:inherit;background:var(--campo);color:var(--tinta)" @required($i === 0 || $pais->documentoDeTodos())>
                            <option value="">—</option>
                            @foreach ($pais->tiposDocumento() as $codigo => $nombre)
                                <option value="{{ $codigo }}" @selected($old('documento_tipo') === $codigo)>{{ $nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="campo"><label for="p{{ $i }}-docnum">{{ __('llegada.documento_numero') }}</label><input type="text" id="p{{ $i }}-docnum" name="personas[{{ $i }}][documento_numero]" value="{{ $old('documento_numero') }}" maxlength="50" autocomplete="off" @required($i === 0 || $pais->documentoDeTodos())></div>
                    @if ($pedirFoto)
                        <div class="campo"><label for="p{{ $i }}-foto">{{ __('llegada.foto') }}</label><input type="file" id="p{{ $i }}-foto" name="personas[{{ $i }}][foto]" accept="image/*,application/pdf" style="width:100%"></div>
                    @endif
                    @if ($i === 0)
                        <div class="campo"><label for="p0-correo">{{ __('llegada.correo') }}</label><input type="email" id="p0-correo" name="personas[0][correo]" value="{{ $old('correo') }}" maxlength="150" autocomplete="email" required></div>
                        <div class="campo"><label for="p0-telefono">{{ __('llegada.telefono') }}</label><input type="tel" id="p0-telefono" name="personas[0][telefono]" value="{{ $old('telefono') }}" maxlength="50" autocomplete="tel" required></div>
                        <div class="campo"><label for="p0-calle">{{ __('llegada.domicilio_calle') }}</label><input type="text" id="p0-calle" name="personas[0][domicilio_calle]" value="{{ $old('domicilio_calle') }}" maxlength="255" autocomplete="street-address" required></div>
                        <div class="campo"><label for="p0-ciudad">{{ __('llegada.domicilio_ciudad') }}</label><input type="text" id="p0-ciudad" name="personas[0][domicilio_ciudad]" value="{{ $old('domicilio_ciudad') }}" maxlength="100" autocomplete="address-level2" required></div>
                        <div class="campo"><label for="p0-region">{{ __('llegada.domicilio_region') }}</label><input type="text" id="p0-region" name="personas[0][domicilio_region]" value="{{ $old('domicilio_region') }}" maxlength="100" autocomplete="address-level1"></div>
                        <div class="campo"><label for="p0-cp">{{ __('llegada.domicilio_cp') }}</label><input type="text" id="p0-cp" name="personas[0][domicilio_codigo_postal]" value="{{ $old('domicilio_codigo_postal') }}" maxlength="20" autocomplete="postal-code"></div>
                        <div class="campo"><label for="p0-pais">{{ __('llegada.domicilio_pais') }}</label><input type="text" id="p0-pais" name="personas[0][domicilio_pais_texto]" value="{{ $old('domicilio_pais_texto') }}" maxlength="100" autocomplete="country-name" required></div>
                    @endif
                    @foreach ($pais->camposLlegada() as $campo => $definicion)
                        @if ($i === 0 || ! $definicion['solo_titular'])
                            <div class="campo"><label for="p{{ $i }}-{{ $campo }}">{{ $definicion['etiqueta'] }}</label><input type="text" id="p{{ $i }}-{{ $campo }}" name="personas[{{ $i }}][{{ $campo }}]" value="{{ $old($campo) }}" maxlength="100" @required($definicion['requerido'])></div>
                        @endif
                    @endforeach
                </div>
            </section>
        @endfor

        <section class="tarjeta">
            <div class="rejilla">
                <div class="campo"><label for="hora">{{ __('llegada.hora') }}</label><input type="time" id="hora" name="hora_llegada" value="{{ old('hora_llegada') }}"></div>
            </div>
            <div class="campo"><label for="notas">{{ __('llegada.notas') }}</label><textarea id="notas" name="notas" rows="3" maxlength="2000">{{ old('notas') }}</textarea></div>

            @foreach ($documentos as $documento)
                <details style="margin:8px 0">
                    <summary style="cursor:pointer;font-weight:600">{{ $documento->titulo }}</summary>
                    <div class="gris" style="white-space:pre-line;max-height:320px;overflow:auto;margin-top:6px">{{ $documento->cuerpo }}</div>
                </details>
                <label class="casilla"><input type="checkbox" name="aceptar[{{ $documento->id }}]" value="1" required @checked(old('aceptar.'.$documento->id))> <span>{{ __('llegada.acepto', ['documento' => $documento->titulo]) }}</span></label>
            @endforeach
            <label class="casilla"><input type="checkbox" name="confirmo" value="1" required @checked(old('confirmo'))> <span>{{ __('llegada.confirmo') }}</span></label>
            <p class="gris" style="margin:8px 0 0">{{ __('llegada.privacidad_nota') }}</p>
            <button type="submit" style="margin-top:12px">{{ __('llegada.enviar') }}</button>
        </section>
    </form>
@endsection
