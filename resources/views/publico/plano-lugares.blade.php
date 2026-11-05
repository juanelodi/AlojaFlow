{{-- AlojaFlow · SPDX-License-Identifier: AGPL-3.0-or-later --}}
@use('App\Camping\AtributosLugar')
@use('App\Support\Formato')
@foreach ($mapas as $mapa)
    <section class="tarjeta" id="plano-{{ $mapa['plano']->id }}">
        <h2>{{ __('camping.motor.elige_lugar') }}: {{ $mapa['plano']->nombre }}</h2>
        <p class="gris" style="margin:0 0 10px">{{ __('camping.motor.elige_lugar_ayuda') }}</p>
        <div class="plano">
            <img src="{{ route('motor.plano', ['codigo' => $propiedad->codigo, 'plano' => $mapa['plano']->id]) }}" alt="{{ $mapa['plano']->nombre }}" loading="lazy">
            @foreach ($mapa['lugares'] as $lugar)
                @if ($lugar['oferta'])
                    <a class="lugar libre" style="left:{{ $lugar['x'] }}%;top:{{ $lugar['y'] }}%" href="{{ route('motor.datos', ['codigo' => $propiedad->codigo, 'tarifa' => $lugar['oferta']->tarifa->id, 'unidad' => $lugar['unidad']->id, 'llegada' => $llegada, 'salida' => $salida, 'personas' => $personas, 'idioma' => app()->getLocale()] + $filtro->parametros()) }}" title="{{ $lugar['unidad']->nombre }} · {{ Formato::dinero($lugar['oferta']->presupuesto->total(), $propiedad->moneda) }}">{{ $lugar['unidad']->codigo }}</a>
                @else
                    <span class="lugar ocupado" style="left:{{ $lugar['x'] }}%;top:{{ $lugar['y'] }}%" title="{{ __('camping.motor.no_disponible') }}">{{ $lugar['unidad']->codigo }}</span>
                @endif
            @endforeach
        </div>
        {{-- La misma elección sin tocar el mapa: con el dedo o un lector de pantalla, una lista se usa mejor que unos puntos. --}}
        <table style="margin-top:12px">
            @foreach ($mapa['lugares'] as $lugar)
                @if ($lugar['oferta'])
                    <tr>
                        <td>
                            {{ $lugar['unidad']->nombre }}
                            @php($etiquetas = AtributosLugar::etiquetas($lugar['unidad']))
                            @if ($etiquetas !== [])
                                <br><span class="gris">{{ implode(' · ', $etiquetas) }}</span>
                            @endif
                        </td>
                        <td class="num">
                            {{ Formato::dinero($lugar['oferta']->presupuesto->total(), $propiedad->moneda) }}
                            <br><a href="{{ route('motor.datos', ['codigo' => $propiedad->codigo, 'tarifa' => $lugar['oferta']->tarifa->id, 'unidad' => $lugar['unidad']->id, 'llegada' => $llegada, 'salida' => $salida, 'personas' => $personas, 'idioma' => app()->getLocale()] + $filtro->parametros()) }}">{{ __('camping.motor.elegir') }}</a>
                        </td>
                    </tr>
                @endif
            @endforeach
        </table>
    </section>
@endforeach
