<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Documentos\GeneradorPdf;
use App\Enums\EstadoReserva;
use App\Models\PagoEnLinea;
use App\Models\Propiedad;
use App\Models\Reserva;
use App\Models\Tarifa;
use App\Motor\AjustesMotor;
use App\Motor\MotorReservas;
use App\Motor\Pasarelas;
use App\Pagos\CobrosEnLinea;
use App\Pagos\ErrorPago;
use App\Reservas\ReservaRechazada;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\MessageBag;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Las páginas públicas del motor de reservas (D-042). Sin JavaScript obligatorio y sin depender
 * de la sesión ni de cookies, para que funcionen también dentro de un iframe en el sitio del
 * alojamiento: los errores se muestran en la misma respuesta y los avisos van en la dirección.
 */
class MotorReservasController extends Controller
{
    private const REGLAS = [
        'tarifa_id' => ['required', 'integer'],
        'llegada' => ['required', 'date_format:Y-m-d'],
        'salida' => ['required', 'date_format:Y-m-d', 'after:llegada'],
        'personas' => ['required', 'integer', 'min:1', 'max:100'],
        'nombre' => ['required', 'string', 'max:100'],
        'apellido' => ['required', 'string', 'max:100'],
        'correo' => ['required', 'email', 'max:150'],
        'telefono' => ['required', 'string', 'max:50'],
        'comentario' => ['nullable', 'string', 'max:2000'],
        'cupon' => ['nullable', 'string', 'max:40'],
        'cargos' => ['nullable', 'array', 'max:50'],
        'cargos.*' => ['integer'],
        'acepto' => ['accepted'],
        // Campo trampa: las personas no lo ven; los programas que llenan formularios sí.
        'sitio_web' => ['prohibited'],
    ];

    public function __construct(private readonly MotorReservas $motor, private readonly CobrosEnLinea $cobros)
    {
    }

    public function buscar(Request $request, string $codigo): View
    {
        $propiedad = $this->propiedad($request, $codigo);
        $hoy = $propiedad->hoy();
        $llegada = $this->fecha($request->query('llegada'));
        $salida = $this->fecha($request->query('salida'));
        $personas = max(1, min(100, $request->integer('personas', 2)));
        $error = null;
        $ofertas = null;
        if ($llegada !== null && $salida !== null) {
            $error = $this->motor->motivoFechas($propiedad, $llegada, $salida);
            if ($error === null) {
                $ofertas = $this->motor->ofertas($propiedad, $llegada, $salida, $personas);
            }
        }

        return view('publico.motor-buscar', [
            'propiedad' => $propiedad,
            'llegada' => $llegada?->format('Y-m-d') ?? $hoy->format('Y-m-d'),
            'salida' => $salida?->format('Y-m-d') ?? $hoy->addDay()->format('Y-m-d'),
            'personas' => $personas,
            'ofertas' => $ofertas,
            'noches' => $llegada !== null && $salida !== null && $salida->gt($llegada) ? (int) $llegada->diffInDays($salida) : 0,
            'error' => $error,
            'minimoFecha' => $hoy->format('Y-m-d'),
        ]);
    }

    public function datos(Request $request, string $codigo): View|RedirectResponse
    {
        $propiedad = $this->propiedad($request, $codigo);

        return $this->formularioDatos($propiedad, $request->query('llegada'), $request->query('salida'), $request->integer('personas', 1), $request->integer('tarifa'), [], new MessageBag());
    }

    public function reservar(Request $request, string $codigo): Response
    {
        $propiedad = $this->propiedad($request, $codigo);
        $validador = Validator::make($request->all(), self::REGLAS, [], [
            'nombre' => __('motor.nombre'),
            'apellido' => __('motor.apellido'),
            'correo' => __('motor.correo'),
            'telefono' => __('motor.telefono'),
            'acepto' => __('motor.condiciones'),
            'salida' => __('ui.comun.salida'),
            'llegada' => __('ui.comun.llegada'),
        ]);
        $reenviar = fn (MessageBag $errores) => $this->formularioDatos($propiedad, $request->input('llegada'), $request->input('salida'), $request->integer('personas', 1), $request->integer('tarifa_id'), $request->except('sitio_web'), $errores);
        if ($validador->fails()) {
            return $this->respuestaConError($reenviar($validador->errors()));
        }
        $datos = $validador->validated();
        try {
            $reserva = $this->motor->reservar($propiedad, [
                'tarifa_id' => (int) $datos['tarifa_id'],
                'llegada' => (string) $datos['llegada'],
                'salida' => (string) $datos['salida'],
                'personas' => (int) $datos['personas'],
                'nombre' => (string) $datos['nombre'],
                'apellido' => (string) $datos['apellido'],
                'correo' => (string) $datos['correo'],
                'telefono' => (string) $datos['telefono'],
                'comentario' => $datos['comentario'] ?? null,
                'cupon' => $datos['cupon'] ?? null,
                'cargos' => array_map('intval', array_values((array) ($datos['cargos'] ?? []))),
                'idioma' => app()->getLocale(),
            ]);
        } catch (ReservaRechazada $e) {
            return $this->respuestaConError($reenviar(new MessageBag([$e->campo === 'cupon' ? 'cupon' : 'tarifa_id' => $e->getMessage()])));
        }

        return redirect()->route('motor.reserva', ['codigo' => $codigo, 'publico' => $reserva->codigo_publico, 'idioma' => app()->getLocale()]);
    }

    public function reserva(Request $request, string $codigo, string $publico): View
    {
        $propiedad = $this->propiedad($request, $codigo, soloActivo: false);
        $reserva = $this->buscarReserva($propiedad, $publico);
        $ajustes = new AjustesMotor($propiedad);

        return view('publico.motor-reserva', [
            'propiedad' => $propiedad,
            'reserva' => $reserva,
            'ajustes' => $ajustes,
            'porCobrar' => $this->cobros->porCobrar($reserva),
            'pasarelas' => $reserva->estado === EstadoReserva::Cancelada ? [] : array_keys((new Pasarelas($propiedad))->disponibles()),
        ]);
    }

    public function pagar(Request $request, string $codigo, string $publico, string $pasarela): RedirectResponse
    {
        $propiedad = $this->propiedad($request, $codigo, soloActivo: false);
        $reserva = $this->buscarReserva($propiedad, $publico);
        $proveedor = (new Pasarelas($propiedad))->proveedor($pasarela);
        abort_if($proveedor === null || $reserva->estado === EstadoReserva::Cancelada, 404);

        $volver = route('motor.reserva', ['codigo' => $codigo, 'publico' => $publico, 'idioma' => app()->getLocale()]);
        try {
            $url = $this->cobros->iniciar(
                $reserva,
                $proveedor,
                __('motor.concepto', ['propiedad' => $propiedad->nombre, 'numero' => $reserva->numero]),
                route('motor.retorno', ['codigo' => $codigo, 'publico' => $publico, 'pasarela' => $pasarela, 'idioma' => app()->getLocale()]),
                $volver,
                route('motor.aviso', ['codigo' => $codigo, 'pasarela' => $pasarela]),
            );
        } catch (ErrorPago $e) {
            report($e);

            return redirect($volver.'&aviso=pasarela');
        }

        return redirect()->away($url);
    }

    /**
     * El huésped vuelve de la pasarela. Lo que diga la dirección no vale: se consulta a la pasarela.
     */
    public function retorno(Request $request, string $codigo, string $publico, string $pasarela): RedirectResponse
    {
        $propiedad = $this->propiedad($request, $codigo, soloActivo: false);
        $reserva = $this->buscarReserva($propiedad, $publico);
        $proveedor = (new Pasarelas($propiedad))->proveedor($pasarela);
        $cobro = PagoEnLinea::query()->where('reserva_id', $reserva->id)->where('pasarela', $pasarela)->find($request->integer('cobro'));
        if ($proveedor !== null && $cobro !== null && $cobro->referencia !== null) {
            $resultado = $proveedor->consultarRetorno($request, $cobro->referencia);
            if ($resultado !== null) {
                $this->cobros->confirmar($proveedor, $resultado, $propiedad->id);
            }
        }

        return redirect()->route('motor.reserva', ['codigo' => $codigo, 'publico' => $publico, 'idioma' => app()->getLocale()]);
    }

    /**
     * Aviso de la pasarela (webhook). Responde 200 en lo que no interesa, para que no lo reintente.
     */
    public function aviso(Request $request, string $codigo, string $pasarela): Response
    {
        $propiedad = Propiedad::query()->where('codigo', $codigo)->first();
        $proveedor = $propiedad === null ? null : (new Pasarelas($propiedad))->proveedor($pasarela);
        abort_if($proveedor === null, 404);

        try {
            $resultado = $proveedor->procesarAviso($request);
        } catch (ErrorPago) {
            return response('firma', 400);
        }
        if ($resultado !== null) {
            $this->cobros->confirmar($proveedor, $resultado, $propiedad->id);
        }

        return response('ok');
    }

    public function confirmacionPdf(Request $request, string $codigo, string $publico): Response
    {
        $propiedad = $this->propiedad($request, $codigo, soloActivo: false);
        $reserva = $this->buscarReserva($propiedad, $publico);

        return response(app(GeneradorPdf::class)->confirmacion($reserva), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="reserva-'.$reserva->codigo_publico.'.pdf"',
        ]);
    }

    private function propiedad(Request $request, string $codigo, bool $soloActivo = true): Propiedad
    {
        $propiedad = Propiedad::query()->where('codigo', $codigo)->where('activa', true)->first();
        abort_if($propiedad === null || ! $propiedad->esIndependiente() || ($soloActivo && ! (new AjustesMotor($propiedad))->activo()), 404);

        $idiomas = (array) config('alojaflow.idiomas');
        $idioma = $request->query('idioma');
        if (! in_array($idioma, $idiomas, true)) {
            $idioma = $request->headers->has('Accept-Language') ? substr((string) $request->getPreferredLanguage($idiomas), 0, 2) : $propiedad->idioma;
        }
        app()->setLocale($idioma);

        return $propiedad;
    }

    /**
     * @param  array<string, mixed>  $valores
     */
    private function formularioDatos(Propiedad $propiedad, mixed $llegadaTexto, mixed $salidaTexto, int $personasPedidas, int $tarifaId, array $valores, MessageBag $errores): View|RedirectResponse
    {
        $llegada = $this->fecha($llegadaTexto);
        $salida = $this->fecha($salidaTexto);
        $personas = max(1, min(100, $personasPedidas));
        $tarifa = Tarifa::query()->where('propiedad_id', $propiedad->id)->where('activa', true)->where('visible_motor', true)->find($tarifaId);
        if ($llegada === null || $salida === null || ! $this->motor->rangoCotizable($propiedad, $llegada, $salida) || $tarifa === null) {
            return redirect()->route('motor.buscar', ['codigo' => $propiedad->codigo]);
        }
        $oferta = collect($this->motor->ofertas($propiedad, $llegada, $salida, $personas))->first(fn ($o): bool => $o->tarifa->id === $tarifa->id);
        if ($oferta === null) {
            return redirect()->route('motor.buscar', ['codigo' => $propiedad->codigo, 'llegada' => $llegada->format('Y-m-d'), 'salida' => $salida->format('Y-m-d'), 'personas' => $personas, 'aviso' => 'sin_lugar']);
        }

        return view('publico.motor-datos', [
            'propiedad' => $propiedad,
            'ajustes' => new AjustesMotor($propiedad),
            'oferta' => $oferta,
            'tarifa' => $tarifa,
            'llegada' => $llegada,
            'salida' => $salida,
            'personas' => $personas,
            'noches' => (int) $llegada->diffInDays($salida),
            'cargos' => $this->motor->cargosOpcionales($propiedad),
            'valores' => $valores,
            'errores' => $errores,
        ]);
    }

    private function respuestaConError(View|RedirectResponse $respuesta): Response
    {
        return $respuesta instanceof View ? response($respuesta, 422) : $respuesta;
    }

    private function buscarReserva(Propiedad $propiedad, string $publico): Reserva
    {
        return Reserva::query()->with(['unidad', 'titular', 'cargos'])->where('propiedad_id', $propiedad->id)->where('codigo_publico', $publico)->firstOrFail();
    }

    private function fecha(mixed $valor): ?CarbonImmutable
    {
        if (! is_string($valor) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) !== 1) {
            return null;
        }
        try {
            return CarbonImmutable::createFromFormat('!Y-m-d', $valor) ?: null;
        } catch (Throwable) {
            return null;
        }
    }
}
