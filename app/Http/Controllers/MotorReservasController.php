<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Archivos\GestorArchivos;
use App\Camping\AtributosLugar;
use App\Camping\FiltroLugares;
use App\Comprobantes\GestorComprobantes;
use App\Dinero\GestorPoliticas;
use App\Documentos\GeneradorPdf;
use App\Enums\EstadoReserva;
use App\Enums\TipoEquipo;
use App\Http\Controllers\Concerns\RecibeComprobante;
use App\Models\Foto;
use App\Models\PagoEnLinea;
use App\Models\Plano;
use App\Models\Politica;
use App\Models\Propiedad;
use App\Models\Reserva;
use App\Models\Tarifa;
use App\Models\Unidad;
use App\Modulos\Modulo;
use App\Modulos\Modulos;
use App\Motor\AjustesMotor;
use App\Motor\MotorReservas;
use App\Motor\Pasarelas;
use App\Pagos\CobrosEnLinea;
use App\Pagos\ErrorPago;
use App\Pagos\PagoConFormulario;
use App\Publico\CalendarioPublico;
use App\Publico\PaginaPublica;
use App\Reservas\Mensualidades;
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
    use RecibeComprobante;

    private const REGLAS = [
        'tarifa_id' => ['required', 'integer'],
        'llegada' => ['required', 'date_format:Y-m-d'],
        'salida' => ['required', 'date_format:Y-m-d', 'after:llegada'],
        'personas' => ['required', 'integer', 'min:1', 'max:100'],
        'nombre' => ['required', 'string', 'max:100'],
        'apellido' => ['required', 'string', 'max:100'],
        'correo' => ['required', 'email', 'max:150'],
        // Al menos siete dígitos: sin un número con el que localizarlo, la reserva no sirve de mucho.
        'telefono' => ['required', 'string', 'max:50', 'regex:/(\D*\d){7}/'],
        'comentario' => ['nullable', 'string', 'max:2000'],
        'cupon' => ['nullable', 'string', 'max:40'],
        'cargos' => ['nullable', 'array', 'max:50'],
        'cargos.*' => ['integer'],
        'cantidades' => ['nullable', 'array', 'max:50'],
        'cantidades.*' => ['integer', 'min:0', 'max:50'],
        'unidad_id' => ['nullable', 'integer'],
        'unidades' => ['nullable', 'integer', 'min:1', 'max:10'],
        'tipo_lugar' => ['nullable', 'string', 'max:20'],
        'con_luz' => ['nullable', 'boolean'],
        'con_agua' => ['nullable', 'boolean'],
        'con_drenaje' => ['nullable', 'boolean'],
        'con_mascotas' => ['nullable', 'boolean'],
        'con_accesible' => ['nullable', 'boolean'],
        'largo' => ['nullable', 'numeric', 'min:0', 'max:1000'],
        'tipo_equipo' => ['nullable', 'string', 'max:20'],
        'vehiculos' => ['nullable', 'integer', 'min:0', 'max:20'],
        'placas' => ['nullable', 'string', 'max:100'],
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
        $primerDia = $propiedad->hoy()->addDays((new AjustesMotor($propiedad))->diasMinimosAntelacion());
        $llegada = $this->fecha($request->query('llegada'));
        $salida = $this->fecha($request->query('salida'));
        $personas = max(1, min(100, $request->integer('personas', 2)));
        $unidadesMaximas = (new AjustesMotor($propiedad))->unidadesMaximas();
        $unidades = max(1, min($unidadesMaximas, $personas, $request->integer('unidades', 1)));
        $error = null;
        $ofertas = null;
        $filtro = $this->filtro($request->query());
        $mapas = [];
        if ($llegada !== null && $salida !== null) {
            $error = $this->motor->motivoFechas($propiedad, $llegada, $salida);
            if ($error === null) {
                $ofertas = $this->motor->ofertas($propiedad, $llegada, $salida, $personas, $filtro, $unidades);
                $mapas = $unidades > 1 ? [] : $this->motor->mapas($propiedad, $ofertas, $llegada, $salida, $personas, $filtro);
            }
        }

        $pagina = new PaginaPublica($propiedad);

        return view('publico.motor-buscar', [
            'propiedad' => $propiedad,
            'pagina' => $pagina,
            'fotos' => $pagina->fotos(),
            'fotosTarifa' => Foto::query()->where('propiedad_id', $propiedad->id)->whereNotNull('tarifa_id')->orderBy('orden')->orderBy('id')->get()->groupBy('tarifa_id'),
            'politica' => $pagina->mostrarPoliticas() ? $this->politicaGeneral($propiedad) : null,
            'llegada' => $llegada?->format('Y-m-d') ?? $primerDia->format('Y-m-d'),
            'salida' => $salida?->format('Y-m-d') ?? $primerDia->addDay()->format('Y-m-d'),
            'personas' => $personas,
            'unidades' => $unidades,
            'unidadesMaximas' => $unidadesMaximas,
            'ofertas' => $ofertas,
            'conEstanciasLargas' => Modulos::activo(Modulo::EstanciasLargas),
            'conCalendario' => (new AjustesMotor($propiedad))->calendarioPublico(),
            'filtro' => $filtro,
            'conFiltros' => Modulos::activo(Modulo::Camping) && AtributosLugar::hayQueFiltrar($propiedad),
            'mapas' => $mapas,
            'unidadLargo' => AtributosLugar::unidadLargo($propiedad),
            'noches' => $llegada !== null && $salida !== null && $salida->gt($llegada) ? (int) $llegada->diffInDays($salida) : 0,
            'error' => $error,
            'minimoFecha' => $primerDia->format('Y-m-d'),
        ]);
    }

    /**
     * Qué días hay lugar, mes por mes (D-126). Desde este mes hasta donde se puede reservar: lo demás no le sirve a nadie.
     */
    public function calendario(Request $request, string $codigo): View
    {
        $propiedad = $this->propiedad($request, $codigo);
        abort_unless((new AjustesMotor($propiedad))->calendarioPublico(), 404);
        $primero = CarbonImmutable::parse($propiedad->hoy()->format('Y-m-01'));
        $ultimo = CarbonImmutable::parse($propiedad->hoy()->addDays(MotorReservas::DIAS_HORIZONTE)->format('Y-m-01'));
        $pedido = is_string($request->query('mes')) ? $this->fecha($request->query('mes').'-01') : null;
        $mes = $pedido === null || $pedido->lt($primero) || $pedido->gt($ultimo) ? $primero : $pedido;

        return view('publico.motor-calendario', [
            'propiedad' => $propiedad,
            'mes' => $mes,
            'personas' => max(1, min(100, $request->integer('personas', 2))),
            'semanas' => (new CalendarioPublico($propiedad))->semanas($mes),
            'anterior' => $mes->gt($primero) ? $mes->subMonth()->format('Y-m') : null,
            'siguiente' => $mes->lt($ultimo) ? $mes->addMonth()->format('Y-m') : null,
        ]);
    }

    public function datos(Request $request, string $codigo): View|RedirectResponse
    {
        $propiedad = $this->propiedad($request, $codigo);

        return $this->formularioDatos($propiedad, $request->query('llegada'), $request->query('salida'), $request->integer('personas', 1), $request->integer('tarifa'), $request->query(), new MessageBag());
    }

    public function reservar(Request $request, string $codigo): Response
    {
        $propiedad = $this->propiedad($request, $codigo);
        $validador = Validator::make($request->all(), self::REGLAS, [
            'nombre.required' => __('motor.validacion.nombre'),
            'apellido.required' => __('motor.validacion.apellido'),
            'correo.required' => __('motor.validacion.correo'),
            'correo.email' => __('motor.validacion.correo_formato'),
            'telefono.required' => __('motor.validacion.telefono'),
            'telefono.regex' => __('motor.validacion.telefono_formato'),
            'acepto.accepted' => __('motor.validacion.acepto'),
        ], [
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
                'cantidades' => array_map('intval', (array) ($datos['cantidades'] ?? [])),
                'idioma' => app()->getLocale(),
                'filtro' => $this->filtro($datos),
                'unidad_id' => (int) ($datos['unidad_id'] ?? 0),
                'unidades' => (int) ($datos['unidades'] ?? 1),
                'equipo' => (new AjustesMotor($propiedad))->pideEquipo() ? ['tipo' => TipoEquipo::tryFrom((string) ($datos['tipo_equipo'] ?? ''))?->value, 'largo' => $datos['largo'] ?? null, 'vehiculos' => $datos['vehiculos'] ?? null, 'placas' => $datos['placas'] ?? null] : null,
            ]);
        } catch (ReservaRechazada $e) {
            return $this->respuestaConError($reenviar(new MessageBag($e->campo === 'cupon' ? ['cupon' => $e->getMessage().' '.__('motor.error.cupon_que_hacer')] : ['tarifa_id' => $e->getMessage()])));
        }

        return redirect()->route('motor.reserva', ['codigo' => $codigo, 'publico' => $reserva->codigo_publico, 'idioma' => app()->getLocale()]);
    }

    public function reserva(Request $request, string $codigo, string $publico): View|RedirectResponse
    {
        $propiedad = $this->propiedad($request, $codigo, soloActivo: false);
        $reserva = $this->buscarReserva($propiedad, $publico);
        // Las demás de un pedido llevan a la primera, que es la que se paga (D-158).
        if ($reserva->reservaPrincipal !== null) {
            return redirect()->route('motor.reserva', ['codigo' => $codigo, 'publico' => $reserva->reservaPrincipal->codigo_publico, 'idioma' => app()->getLocale()]);
        }
        $ajustes = new AjustesMotor($propiedad);

        return view('publico.motor-reserva', [
            // Todas las del pedido (D-158): se pagan juntas desde la primera.
            'pedido' => $reserva->pedido(),
            'propiedad' => $propiedad,
            'reserva' => $reserva,
            'ajustes' => $ajustes,
            'porCobrar' => $this->cobros->porCobrar($reserva),
            'pasarelas' => $reserva->estado === EstadoReserva::Cancelada ? [] : array_keys((new Pasarelas($propiedad))->disponibles()),
            'comprobantes' => $reserva->comprobantes()->latest('id')->limit(20)->get(),
            'calendario' => $reserva->estado === EstadoReserva::Cancelada ? null : Mensualidades::de($reserva),
            'espera' => $reserva->estado === EstadoReserva::Cancelada || $this->cobros->porCobrar($reserva) <= 0 ? null : $this->cobros->enEspera($reserva),
        ]);
    }

    /**
     * La transferencia que ya hizo (D-097). Con errores vuelve a su reserva y le dice qué pasó: no hay sesión con que devolverle el formulario.
     */
    public function comprobante(Request $request, string $codigo, string $publico, GestorComprobantes $comprobantes): RedirectResponse
    {
        $propiedad = $this->propiedad($request, $codigo, soloActivo: false);
        $reserva = $this->buscarReserva($propiedad, $publico);
        $volver = fn (string $aviso): RedirectResponse => redirect()->to(route('motor.reserva', ['codigo' => $codigo, 'publico' => $publico, 'idioma' => app()->getLocale(), 'aviso' => $aviso]).'#comprobante');
        if (! (new AjustesMotor($propiedad))->aceptaComprobantes()) {
            abort(404);
        }

        return $volver($this->recibirComprobante($request, $reserva, $comprobantes));
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
     * La página que manda el formulario firmado a una pasarela que no admite un simple enlace. La firma de la referencia ya dice que el cobro es de verdad.
     */
    public function ir(Request $request, string $codigo, string $pasarela, string $referencia): View
    {
        $propiedad = $this->propiedad($request, $codigo, soloActivo: false);
        $proveedor = (new Pasarelas($propiedad))->proveedor($pasarela);
        $cobro = CobrosEnLinea::pendienteDeReferencia($referencia, $propiedad->id, $pasarela);
        abort_if(! $proveedor instanceof PagoConFormulario || $cobro === null || ! is_array($cobro->datos['formulario'] ?? null), 404);

        return view('publico.ir-a-pago', ['propiedad' => $propiedad, 'formulario' => $proveedor->formulario($cobro->datos['formulario'])]);
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

    /**
     * La que se aplicaría a quien reserva ahora, para que lo sepa antes de reservar.
     */
    private function politicaGeneral(Propiedad $propiedad): ?string
    {
        $politica = Politica::query()->where('propiedad_id', $propiedad->id)->where('predeterminada', true)->first();

        return $politica === null ? null : app(GestorPoliticas::class)->textoDe($politica);
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
            return redirect()->route('motor.buscar', ['codigo' => $propiedad->codigo, 'idioma' => app()->getLocale()]);
        }
        $filtro = $this->filtro($valores);
        $unidades = max(1, min((new AjustesMotor($propiedad))->unidadesMaximas(), $personas, (int) ($valores['unidades'] ?? 1)));
        $oferta = collect($this->motor->ofertas($propiedad, $llegada, $salida, $personas, $filtro, $unidades))->first(fn ($o): bool => $o->tarifa->id === $tarifa->id);
        $ajustes = new AjustesMotor($propiedad);
        $lugar = null;
        $unidadId = (int) ($valores['unidad_id'] ?? $valores['unidad'] ?? 0);
        if ($oferta !== null && $unidadId > 0 && $ajustes->elegirLugar() && $unidades === 1) {
            $lugar = $this->motor->libres($propiedad, $llegada, $salida, $personas, $filtro)->first(fn (Unidad $u): bool => $u->id === $unidadId && in_array($tarifa->id, $u->tarifasIds(), true));
            $oferta = $lugar === null ? null : $this->motor->conLugar($oferta, $lugar, $llegada, $salida, $personas);
        }
        if ($oferta === null) {
            return redirect()->route('motor.buscar', ['codigo' => $propiedad->codigo, 'llegada' => $llegada->format('Y-m-d'), 'salida' => $salida->format('Y-m-d'), 'personas' => $personas, 'idioma' => app()->getLocale(), 'aviso' => 'sin_lugar'] + $filtro->parametros());
        }

        return view('publico.motor-datos', [
            'propiedad' => $propiedad,
            'ajustes' => $ajustes,
            'conPago' => $oferta->anticipo > 0 && (new Pasarelas($propiedad))->disponibles() !== [],
            'oferta' => $oferta,
            'tarifa' => $tarifa,
            'filtro' => $filtro,
            'lugar' => $lugar,
            // Los extras y el cupón son de una sola unidad (D-158).
            'cupos' => $this->motor->cupos($cargos = $unidades > 1 ? new \Illuminate\Database\Eloquent\Collection() : $this->motor->cargosOpcionales($propiedad, $lugar ?? $oferta->unidad, $tarifa->id, $llegada, $salida, $personas), $llegada, $salida),
            'unidades' => $unidades,
            'unidadLargo' => AtributosLugar::unidadLargo($propiedad),
            'llegada' => $llegada,
            'salida' => $salida,
            'personas' => $personas,
            'noches' => (int) $llegada->diffInDays($salida),
            'cargos' => $cargos,
            'valores' => $valores,
            'errores' => $errores,
        ]);
    }

    /**
     * El plano del lugar, para el que elige en el motor. Solo imágenes comunes: un SVG puede llevar código, y esto lo abre cualquiera.
     */
    public function plano(Request $request, string $codigo, int $plano, GestorArchivos $archivos): Response
    {
        $propiedad = $this->propiedad($request, $codigo);
        abort_unless((new AjustesMotor($propiedad))->elegirLugar(), 404);
        $archivo = Plano::query()->with('archivo')->where('propiedad_id', $propiedad->id)->find($plano)?->archivo;
        abort_unless($archivo !== null && in_array($archivo->tipo_mime, ['image/jpeg', 'image/png', 'image/webp'], true), 404);

        return response($archivos->contenido($archivo), 200, ['Content-Type' => $archivo->tipo_mime, 'Cache-Control' => 'public, max-age=3600']);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function filtro(array $datos): FiltroLugares
    {
        return Modulos::activo(Modulo::Camping) ? FiltroLugares::desde($datos) : new FiltroLugares();
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
