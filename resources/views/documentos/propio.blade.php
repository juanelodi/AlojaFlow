{{-- AlojaFlow · SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Un documento que escribió el alojamiento (D-123). El texto ya viene limpio y con los datos escapados: MotorDocumentos. --}}
@extends('documentos.base')
@section('titulo', $titulo)
@section('contenido')
    <style>
        .propio p { margin: 0 0 8px; }
        .propio ul, .propio ol { margin: 0 0 8px; padding-left: 18px; }
        .propio table { width: 100%; border-collapse: collapse; margin: 0 0 10px; }
        .propio th, .propio td { border: 1px solid #cbd5e1; padding: 4px 6px; text-align: left; vertical-align: top; overflow-wrap: anywhere; }
        .propio th { background: #f1f5f9; }
        .propio td p, .propio th p { margin: 0; }
    </style>
    <div class="propio">{!! $cuerpo !!}</div>
@endsection
