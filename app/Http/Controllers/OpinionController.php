<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Comunicacion\Opiniones;
use App\Models\Opinion;
use App\Models\Reserva;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * La encuesta del huésped (D-087). Responde en la misma página, como las demás páginas públicas (D-056).
 */
class OpinionController extends Controller
{
    public function __construct(private readonly Opiniones $opiniones)
    {
    }

    public function ver(Request $request, string $token): View
    {
        $reserva = $this->reserva($request, $token);

        return $this->pagina($token, $reserva, Opinion::query()->where('reserva_id', $reserva->id)->first());
    }

    public function guardar(Request $request, string $token): View
    {
        $reserva = $this->reserva($request, $token);
        $validador = Validator::make($request->all(), [
            'calificacion' => ['required', 'integer', 'between:1,5'],
            'comentario' => ['nullable', 'string', 'max:2000'],
        ], [], ['calificacion' => __('operacion.opiniones.calificacion'), 'comentario' => __('operacion.opiniones.comentario')]);
        if ($validador->fails()) {
            return $this->pagina($token, $reserva, null, $validador->errors()->all());
        }
        $opinion = $this->opiniones->guardar($reserva, (int) $request->input('calificacion'), $request->string('comentario')->toString());

        return $this->pagina($token, $reserva, $opinion, [], true);
    }

    /**
     * @param  list<string>  $errores
     */
    private function pagina(string $token, Reserva $reserva, ?Opinion $opinion, array $errores = [], bool $gracias = false): View
    {
        return view('publico.opinion', [
            'token' => $token,
            'propiedad' => $reserva->propiedad,
            'opinion' => $opinion,
            'errores' => $errores,
            'gracias' => $gracias,
            'enlacePublico' => Opiniones::enlacePublicoDe($reserva->propiedad_id),
        ]);
    }

    private function reserva(Request $request, string $token): Reserva
    {
        $reserva = $this->opiniones->reservaDe($token);
        abort_if($reserva === null, 404);
        $idioma = (string) $request->query('idioma');
        app()->setLocale(in_array($idioma, (array) config('alojaflow.idiomas'), true) ? $idioma : $reserva->propiedad->idioma);

        return $reserva;
    }
}
