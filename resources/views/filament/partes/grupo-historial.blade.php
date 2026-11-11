{{-- AlojaFlow · Copyright (C) 2026 Juan Galeana Durán · SPDX-License-Identifier: FSL-1.1-ALv2 --}}
@use('App\Models\RegistroAuditoria')
@use('App\Support\Formato')
@php
    $grupo = $getRecord();
    $filas = RegistroAuditoria::query()->with('usuario')
        ->where(fn ($q) => $q->where('modelo_tipo', 'Grupo')->where('modelo_id', $grupo->id))
        ->orWhere(fn ($q) => $q->where('modelo_tipo', 'ComprobantePago')->whereIn('modelo_id', $grupo->comprobantes()->select('id')))
        ->latest('id')->limit(50)->get();
@endphp
@if ($filas->isEmpty())
    <p class="af-nota">{{ __('grupos.historial.vacio') }}</p>
@else
    <ul class="af-lista-simple">
        @foreach ($filas as $fila)
            @php($clave = 'grupos.historial.acciones.'.str_replace('.', '_', $fila->accion))
            <li>{{ Formato::momento($fila->creado_en) }} · {{ \Illuminate\Support\Facades\Lang::has($clave) ? __($clave) : $fila->accion }} · {{ $fila->usuario->name ?? __('ui.grupos.coordinador') }}</li>
        @endforeach
    </ul>
@endif
