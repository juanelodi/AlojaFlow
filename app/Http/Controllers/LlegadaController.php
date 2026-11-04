<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\EstadoRegistroLlegada;
use App\Enums\VerificacionAceptacion;
use App\Llegada\GestorLlegadas;
use App\Models\RegistroLlegada;
use App\Models\Reserva;
use App\Paises\Paises;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;
use Throwable;

/**
 * El formulario que recibe el huésped por un enlace. Quien tiene el enlace ve solo esa reserva.
 */
class LlegadaController extends Controller
{
    private const PERSONAS_MAXIMAS = 20;

    public function __construct(private readonly GestorLlegadas $llegadas)
    {
    }

    public function formulario(Request $request, string $token): View|RedirectResponse
    {
        $reserva = $this->reserva($request, $token);
        $registro = RegistroLlegada::query()->where('reserva_id', $reserva->id)->first();
        if ($registro !== null && $registro->estado !== EstadoRegistroLlegada::Pendiente) {
            return redirect()->route('llegada.listo', ['token' => $token, 'idioma' => app()->getLocale()]);
        }

        return view('publico.llegada-formulario', $this->datosVista($reserva, $token));
    }

    public function guardar(Request $request, string $token): RedirectResponse
    {
        $reserva = $this->reserva($request, $token);
        $propiedad = $reserva->propiedad;
        $pais = Paises::de($propiedad);
        $documentos = $this->llegadas->documentosPara($propiedad, app()->getLocale());

        $reglas = [
            'personas' => ['required', 'array', 'min:1', 'max:'.self::PERSONAS_MAXIMAS],
            'personas.*.nombre' => ['required', 'string', 'max:100'],
            'personas.*.apellido1' => ['required', 'string', 'max:100'],
            'personas.*.apellido2' => ['nullable', 'string', 'max:100'],
            'personas.*.sexo' => ['nullable', 'string', 'max:10'],
            'personas.*.fecha_nacimiento' => ['nullable', 'date', 'before:today'],
            'personas.*.nacionalidad_texto' => ['nullable', 'string', 'max:100'],
            'personas.*.documento_tipo' => ['nullable', Rule::in(array_keys($pais->tiposDocumento()))],
            'personas.*.documento_numero' => ['nullable', 'string', 'max:50'],
            'personas.*.documento_vencimiento' => ['nullable', 'date'],
            'personas.*.foto' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:8192'],
            'personas.0.correo' => ['required', 'email', 'max:150'],
            'personas.0.telefono' => ['required', 'string', 'max:50'],
            'personas.0.domicilio_calle' => ['required', 'string', 'max:255'],
            'personas.0.domicilio_ciudad' => ['required', 'string', 'max:100'],
            'personas.0.domicilio_region' => ['nullable', 'string', 'max:100'],
            'personas.0.domicilio_codigo_postal' => ['nullable', 'string', 'max:20'],
            'personas.0.domicilio_pais_texto' => ['required', 'string', 'max:100'],
            'personas.0.documento_tipo' => ['required', Rule::in(array_keys($pais->tiposDocumento()))],
            'personas.0.documento_numero' => ['required', 'string', 'max:50'],
            'hora_llegada' => ['nullable', 'date_format:H:i'],
            'notas' => ['nullable', 'string', 'max:2000'],
            'confirmo' => ['accepted'],
            // Campo trampa: las personas no lo ven.
            'sitio_web' => ['prohibited'],
        ];
        foreach ($pais->camposLlegada() as $campo => $definicion) {
            $llave = $definicion['solo_titular'] ? 'personas.0.'.$campo : 'personas.*.'.$campo;
            $reglas[$llave] = [$definicion['requerido'] ? 'required' : 'nullable', 'string', 'max:100'];
        }
        if ($pais->documentoDeTodos()) {
            $reglas['personas.*.documento_tipo'] = ['required', Rule::in(array_keys($pais->tiposDocumento()))];
            $reglas['personas.*.documento_numero'] = ['required', 'string', 'max:50'];
        }
        foreach ($documentos as $documento) {
            $reglas['aceptar.'.$documento->id] = ['accepted'];
        }

        $datos = $request->validate($reglas, [], [
            'personas.0.correo' => __('llegada.correo'),
            'personas.0.telefono' => __('llegada.telefono'),
            'personas.*.nombre' => __('llegada.nombre'),
            'personas.*.apellido1' => __('llegada.apellido1'),
            'personas.*.documento_numero' => __('llegada.documento_numero'),
            'personas.0.documento_numero' => __('llegada.documento_numero'),
            'personas.0.documento_tipo' => __('llegada.documento_tipo'),
            'personas.0.domicilio_calle' => __('llegada.domicilio_calle'),
            'personas.0.domicilio_ciudad' => __('llegada.domicilio_ciudad'),
            'personas.0.domicilio_pais_texto' => __('llegada.domicilio_pais'),
            'confirmo' => __('llegada.confirmo'),
        ]);

        $fotos = [];
        foreach ((array) $request->file('personas', []) as $i => $archivos) {
            $fotos[(int) $i] = $archivos['foto'] ?? null;
        }

        try {
            $registro = $this->llegadas->guardar($reserva, $datos, $fotos, $documentos->modelKeys(), app()->getLocale());
        } catch (RuntimeException) {
            return redirect()->route('llegada.listo', ['token' => $token, 'idioma' => app()->getLocale()]);
        }

        if ($this->llegadas->usaCodigo($propiedad) && $documentos->isNotEmpty()) {
            try {
                $this->llegadas->mandarCodigo($registro->load('personas'));

                return redirect()->route('llegada.verificar', ['token' => $token, 'idioma' => app()->getLocale()]);
            } catch (Throwable $e) {
                // Sin poder mandar el código, el huésped no debe quedarse atascado.
                report($e);
            }
        }

        $this->llegadas->completar($registro, VerificacionAceptacion::Casilla, null, $request->ip(), $request->userAgent());

        return redirect()->route('llegada.listo', ['token' => $token, 'idioma' => app()->getLocale()]);
    }

    public function verificar(Request $request, string $token): View|RedirectResponse
    {
        $reserva = $this->reserva($request, $token);
        $registro = RegistroLlegada::query()->with('personas')->where('reserva_id', $reserva->id)->first();
        if ($registro === null || $registro->estado !== EstadoRegistroLlegada::Pendiente) {
            return redirect()->route('llegada.formulario', ['token' => $token]);
        }

        return view('publico.llegada-verificar', ['propiedad' => $reserva->propiedad, 'token' => $token, 'correo' => $this->llegadas->correoDe($registro)]);
    }

    public function comprobar(Request $request, string $token): RedirectResponse
    {
        $reserva = $this->reserva($request, $token);
        $registro = RegistroLlegada::query()->with('personas')->where('reserva_id', $reserva->id)->firstOrFail();
        $datos = $request->validate(['codigo' => ['required', 'digits:6']]);

        $otp = $this->llegadas->verificarCodigo($registro, (string) $datos['codigo']);
        if ($otp === null) {
            return back()->withErrors(['codigo' => __('llegada.codigo_incorrecto')]);
        }
        $this->llegadas->completar($registro, VerificacionAceptacion::OtpCorreo, $otp, $request->ip(), $request->userAgent());

        return redirect()->route('llegada.listo', ['token' => $token, 'idioma' => app()->getLocale()]);
    }

    public function reenviar(Request $request, string $token): RedirectResponse
    {
        $reserva = $this->reserva($request, $token);
        $registro = RegistroLlegada::query()->with('personas')->where('reserva_id', $reserva->id)->firstOrFail();
        if ($registro->estado === EstadoRegistroLlegada::Pendiente) {
            try {
                $this->llegadas->mandarCodigo($registro);
            } catch (Throwable $e) {
                report($e);

                return back()->withErrors(['codigo' => __('llegada.codigo_no_enviado')]);
            }
        }

        return back()->with('aviso_llegada', __('llegada.codigo_reenviado'));
    }

    public function listo(Request $request, string $token): View
    {
        $reserva = $this->reserva($request, $token);

        return view('publico.llegada-listo', ['propiedad' => $reserva->propiedad, 'reserva' => $reserva]);
    }

    /**
     * @return array<string, mixed>
     */
    private function datosVista(Reserva $reserva, string $token): array
    {
        $propiedad = $reserva->propiedad;
        $pais = Paises::de($propiedad);
        $titular = $reserva->titular;

        return [
            'propiedad' => $propiedad,
            'reserva' => $reserva,
            'token' => $token,
            'pais' => $pais,
            'cuantas' => min(self::PERSONAS_MAXIMAS, max(1, $reserva->personas)),
            'pedirFoto' => $this->llegadas->pedirFoto($propiedad),
            'documentos' => $this->llegadas->documentosPara($propiedad, app()->getLocale()),
            'titular' => [
                'nombre' => $titular->nombre ?? '',
                'apellido1' => $titular->apellido1 ?? '',
                'apellido2' => $titular->apellido2 ?? '',
            ],
        ];
    }

    private function reserva(Request $request, string $token): Reserva
    {
        $reserva = $this->llegadas->reservaDelToken($token);
        abort_if($reserva === null, 404);
        $reserva->load(['propiedad', 'titular.contactos']);

        $idiomas = (array) config('alojaflow.idiomas');
        $idioma = $request->query('idioma');
        if (! in_array($idioma, $idiomas, true)) {
            $idioma = (string) ($reserva->getAttribute('idioma') ?: ($request->headers->has('Accept-Language') ? substr((string) $request->getPreferredLanguage($idiomas), 0, 2) : $reserva->propiedad->idioma));
        }
        app()->setLocale($idioma);

        return $reserva;
    }
}
