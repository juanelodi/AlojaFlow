{{-- AlojaFlow · SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Una tira de fotos que se recorre con el dedo; sin JavaScript. La primera carga enseguida, y las demás cuando hagan falta. --}}
@if ($fotos->isNotEmpty())
    <div class="galeria" tabindex="0" role="group" aria-label="{{ __('portal.fotos_de', ['nombre' => $nombre]) }}">
        @foreach ($fotos as $foto)
            <a href="{{ $foto->url() }}" target="_blank" rel="noopener">
                <img src="{{ $foto->url('chica') }}" alt="{{ $foto->textoPara($nombre) }}" width="640" height="427" @if (! $loop->first) loading="lazy" @endif decoding="async">
            </a>
        @endforeach
    </div>
@endif
