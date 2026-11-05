<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\EnlacePago;
use App\Models\PagoEnLinea;
use App\Motor\Pasarelas;
use App\Pagos\CobrosEnLinea;
use App\Pagos\ErrorPago;
use App\Pagos\GestorEnlacesPago;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * La página pública de un enlace de pago (D-085): sin sesión, y el huésped solo ve el concepto y el importe.
 */
class EnlacePagoController extends Controller
{
    public function __construct(private readonly GestorEnlacesPago $enlaces, private readonly CobrosEnLinea $cobros)
    {
    }

    public function ver(Request $request, string $token): View
    {
        $enlace = $this->enlace($request, $token);

        return view('publico.pagar', [
            'token' => $token,
            'enlace' => $enlace,
            'propiedad' => $enlace->reserva->propiedad,
            'pasarelas' => $enlace->estado() === 'vigente' ? array_keys((new Pasarelas($enlace->reserva->propiedad))->disponibles()) : [],
            'aviso' => $request->query('aviso'),
            'espera' => $enlace->estado() === 'vigente' ? $this->cobros->enEspera($enlace->reserva, $enlace) : null,
        ]);
    }

    public function pagar(Request $request, string $token, string $pasarela): RedirectResponse
    {
        $enlace = $this->enlace($request, $token);
        $propiedad = $enlace->reserva->propiedad;
        $volver = route('pagar.ver', ['token' => $token, 'idioma' => app()->getLocale()]);
        if ($enlace->estado() !== 'vigente') {
            return redirect($volver);
        }
        $proveedor = (new Pasarelas($propiedad))->proveedor($pasarela);
        abort_if($proveedor === null, 404);
        try {
            $url = $this->cobros->iniciar(
                $enlace->reserva,
                $proveedor,
                __('operacion.enlaces.concepto', ['propiedad' => $propiedad->nombre, 'concepto' => $enlace->concepto]),
                route('pagar.retorno', ['token' => $token, 'pasarela' => $pasarela, 'idioma' => app()->getLocale()]),
                $volver,
                route('motor.aviso', ['codigo' => $propiedad->codigo, 'pasarela' => $pasarela]),
                $enlace,
            );
        } catch (ErrorPago $e) {
            report($e);

            return redirect($volver.'&aviso=pasarela');
        }

        return redirect()->away($url);
    }

    /**
     * Lo que diga la dirección no vale: se consulta a la pasarela.
     */
    public function retorno(Request $request, string $token, string $pasarela): RedirectResponse
    {
        $enlace = $this->enlace($request, $token);
        $propiedad = $enlace->reserva->propiedad;
        $proveedor = (new Pasarelas($propiedad))->proveedor($pasarela);
        $cobro = PagoEnLinea::query()->where('enlace_pago_id', $enlace->id)->where('pasarela', $pasarela)->find($request->integer('cobro'));
        if ($proveedor !== null && $cobro !== null && $cobro->referencia !== null) {
            $resultado = $proveedor->consultarRetorno($request, $cobro->referencia);
            if ($resultado !== null) {
                $this->cobros->confirmar($proveedor, $resultado, $propiedad->id);
            }
        }

        return redirect()->route('pagar.ver', ['token' => $token, 'idioma' => app()->getLocale()]);
    }

    private function enlace(Request $request, string $token): EnlacePago
    {
        $enlace = $this->enlaces->buscar($token);
        abort_if($enlace === null, 404);
        $idioma = (string) $request->query('idioma');
        app()->setLocale(in_array($idioma, (array) config('alojaflow.idiomas'), true) ? $idioma : $enlace->reserva->propiedad->idioma);

        return $enlace;
    }
}
