{{-- AlojaFlow · Copyright (C) 2026 Juan Galeana Durán · SPDX-License-Identifier: FSL-1.1-ALv2 --}}
{{-- Barras sin JavaScript: se ven igual al imprimir. Cada valor va escrito, y la tabla de abajo tiene todos los datos. --}}
<figure class="af-grafica" aria-hidden="true">
    @if (count($grafica['series']) > 1)
        <ul class="af-grafica__leyenda">
            @foreach ($grafica['series'] as $i => $serie)
                <li><span @class(['af-grafica__muestra', 'af-grafica__barra--segunda' => $i === 1])></span>{{ $serie }}</li>
            @endforeach
        </ul>
    @endif
    @foreach ($grafica['filas'] as $fila)
        <div class="af-grafica__fila">
            <span class="af-grafica__etiqueta">{{ $fila['etiqueta'] }}</span>
            <div>
                @foreach ($fila['valores'] as $i => [$valor, $texto])
                    <div class="af-grafica__par">
                        <span @class(['af-grafica__barra', 'af-grafica__barra--segunda' => $i === 1, 'af-grafica__barra--cero' => $valor == 0]) style="width: calc((100% - 7.5rem) * {{ round(abs($valor) / $grafica['maximo'], 4) }})"></span>
                        <span class="af-grafica__valor">{{ $texto }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach
</figure>
