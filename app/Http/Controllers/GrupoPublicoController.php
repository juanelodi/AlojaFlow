<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Archivos\GestorArchivos;
use App\Comprobantes\ComprobanteRechazado;
use App\Comprobantes\GestorComprobantes;
use App\Comunicacion\VariablesMensaje;
use App\Enums\EstadoReserva;
use App\Grupos\AccesoCoordinador;
use App\Grupos\CalendarioPagos;
use App\Grupos\ContratoGrupo;
use App\Llegada\GestorLlegadas;
use App\Models\Aceptacion;
use App\Models\ComprobantePago;
use App\Models\Grupo;
use App\Models\MensajeGrupo;
use App\Models\Reserva;
use App\Modulos\Modulo;
use App\Modulos\Modulos;
use App\Motor\AjustesMotor;
use App\Reservas\GestorGrupos;
use App\Reservas\ReservaRechazada;
use App\Support\Dinero;
use App\Support\Formato;
use Carbon\CarbonImmutable;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * La página del coordinador (D-096). Lo identifican el enlace secreto y su clave; no tiene cuenta en el panel y solo ve su grupo. Antes de usarla cambia la
 * clave que le dieron y acepta lo que el alojamiento tenga publicado.
 */
class GrupoPublicoController extends Controller
{
    public function __construct(
        private readonly AccesoCoordinador $acceso,
        private readonly ContratoGrupo $contrato,
        private readonly GestorGrupos $grupos,
        private readonly GestorComprobantes $comprobantes,
    ) {
    }

    public function ver(Request $request, string $token): Response
    {
        $grupo = $this->grupo($request, $token);
        if (! $this->acceso->disponible($grupo)) {
            return $this->pagina('publico.grupo-cerrado', $grupo, $token);
        }
        if (! $this->acceso->estaDentro($grupo, $request)) {
            return $this->pagina('publico.grupo-entrar', $grupo, $token);
        }
        if ($grupo->debe_cambiar_clave) {
            return $this->pagina('publico.grupo-clave', $grupo, $token);
        }
        $pendientes = $this->contrato->pendientes($grupo, app()->getLocale());
        if ($pendientes !== []) {
            return $this->pagina('publico.grupo-aceptar', $grupo, $token, ['pendientes' => $pendientes]);
        }

        return $this->pagina('publico.grupo', $grupo, $token, $this->datosCuenta($grupo));
    }

    public function entrar(Request $request, string $token): RedirectResponse
    {
        $grupo = $this->grupo($request, $token);
        abort_unless($this->acceso->disponible($grupo), 404);
        $datos = $request->validate(['clave' => ['required', 'string', 'max:200']]);
        if (! $this->acceso->entrar($grupo, (string) $datos['clave'], $request)) {
            throw ValidationException::withMessages(['clave' => __('grupos.publico.clave_incorrecta')]);
        }

        return $this->aLaCuenta($token);
    }

    public function salir(Request $request, string $token): RedirectResponse
    {
        $this->acceso->salir($this->grupo($request, $token), $request);

        return $this->aLaCuenta($token);
    }

    public function cambiarClave(Request $request, string $token): RedirectResponse
    {
        $grupo = $this->grupo($request, $token);
        abort_unless($this->acceso->disponible($grupo) && $this->acceso->estaDentro($grupo, $request), 403);
        $datos = $request->validate(['clave' => ['required', 'string', 'confirmed', Password::min(8), 'max:200']], [], ['clave' => __('grupos.publico.clave_nueva')]);
        $this->acceso->cambiarClave($grupo, (string) $datos['clave'], $request);

        return $this->aLaCuenta($token);
    }

    public function aceptar(Request $request, string $token): RedirectResponse
    {
        $grupo = $this->grupo($request, $token);
        abort_unless($this->acceso->disponible($grupo) && $this->acceso->estaDentro($grupo, $request), 403);
        $datos = $request->validate([
            'acepto' => ['accepted'],
            'nombre' => ['required', 'string', 'max:200'],
        ], [], ['acepto' => __('grupos.publico.acepto'), 'nombre' => __('grupos.publico.nombre_firma')]);
        $this->contrato->aceptar($grupo, trim((string) $datos['nombre']), app()->getLocale(), $request->ip(), $request->userAgent());

        return $this->aLaCuenta($token);
    }

    public function guardarHabitacion(Request $request, string $token, int $reserva): RedirectResponse
    {
        $grupo = $this->exigirCuenta($request, $token);
        $habitacion = $grupo->reservas()->with('unidad')->where('estado', '!=', EstadoReserva::Cancelada->value)->findOrFail($reserva);
        $capacidad = $habitacion->unidad->capacidad;
        $bolsa = 'habitacion_'.$habitacion->id;
        $datos = $request->validateWithBag($bolsa, [
            'responsable' => ['required', 'string', 'max:150'],
            'personas' => array_filter(['required', 'integer', 'min:1', $capacidad > 0 ? 'max:'.$capacidad : null]),
            'telefono' => ['nullable', 'string', 'max:50'],
            'correo' => ['nullable', 'email', 'max:150'],
            'acompanantes' => ['nullable', 'string', 'max:2000'],
        ], [], [
            'responsable' => __('grupos.lista.responsable'),
            'personas' => __('grupos.lista.personas'),
            'telefono' => __('grupos.lista.telefono'),
            'correo' => __('grupos.lista.correo'),
            'acompanantes' => __('grupos.lista.acompanantes'),
        ]);
        try {
            $this->grupos->guardarHabitacion($habitacion, $datos);
        } catch (ReservaRechazada $e) {
            throw ValidationException::withMessages(['personas' => $e->getMessage()])->errorBag($bolsa);
        }

        return $this->aLaCuenta($token, '#habitacion-'.$habitacion->id)->with('estado', __('grupos.publico.habitacion_guardada'));
    }

    public function registrarPago(Request $request, string $token): RedirectResponse
    {
        $grupo = $this->exigirCuenta($request, $token);
        $datos = $request->validateWithBag('pago', [
            'importe' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'fecha' => ['required', 'date', 'before_or_equal:'.$grupo->propiedad->hoy()->addDay()->format('Y-m-d')],
            'referencia' => ['nullable', 'string', 'max:100'],
            'comprobante' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [], [
            'importe' => __('ui.pagos.importe'),
            'fecha' => __('ui.pagos.fecha'),
            'referencia' => __('ui.pagos.referencia'),
            'comprobante' => __('grupos.comprobante.archivo'),
        ]);
        try {
            $this->comprobantes->recibirDeGrupo(
                $grupo,
                (string) file_get_contents((string) $request->file('comprobante')?->getRealPath()),
                Dinero::aUnidadMenor((string) $datos['importe'], $grupo->propiedad->moneda),
                CarbonImmutable::parse((string) $datos['fecha'])->startOfDay(),
                $datos['referencia'] ?? null,
            );
        } catch (ComprobanteRechazado $e) {
            throw ValidationException::withMessages(['comprobante' => $e->getMessage()])->errorBag('pago');
        }

        return $this->aLaCuenta($token, '#pagos')->with('estado', __('grupos.publico.pago_recibido'));
    }

    public function escribir(Request $request, string $token): RedirectResponse
    {
        $grupo = $this->exigirCuenta($request, $token);
        $datos = $request->validateWithBag('mensaje', ['texto' => ['required', 'string', 'max:2000']], [], ['texto' => __('grupos.mensajes.texto')]);
        MensajeGrupo::query()->create(['grupo_id' => $grupo->id, 'del_coordinador' => true, 'texto' => trim((string) $datos['texto'])]);

        return $this->aLaCuenta($token, '#mensajes');
    }

    public function verComprobante(Request $request, string $token, int $comprobante, GestorArchivos $archivos): Response
    {
        $grupo = $this->exigirCuenta($request, $token);
        $fila = ComprobantePago::query()->where('grupo_id', $grupo->id)->findOrFail($comprobante);
        abort_if($fila->archivo === null, 404);

        return response($archivos->contenido($fila->archivo), 200, [
            'Content-Type' => 'image/jpeg',
            'Content-Disposition' => 'inline; filename="comprobante-'.$fila->id.'.jpg"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function verCopia(Request $request, string $token, int $aceptacion, GestorArchivos $archivos): Response
    {
        $grupo = $this->exigirCuenta($request, $token);
        $fila = Aceptacion::query()->where('grupo_id', $grupo->id)->findOrFail($aceptacion);
        abort_if($fila->copia === null, 404);

        return response($archivos->contenido($fila->copia), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="aceptacion-'.$fila->id.'.pdf"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function datosCuenta(Grupo $grupo): array
    {
        MensajeGrupo::query()->where('grupo_id', $grupo->id)->where('del_coordinador', false)->whereNull('leido_en')->update(['leido_en' => now('UTC')]);
        $reservas = $grupo->reservas()->with(['unidad', 'habitacionGrupo'])->where('estado', '!=', EstadoReserva::Cancelada->value)->orderBy('llegada')->orderBy('id')->get();
        $saldo = $this->grupos->saldo($grupo);

        return [
            'saldo' => $saldo,
            'progreso' => $saldo['total'] > 0 ? min(100, (int) floor($saldo['pagado'] * 100 / $saldo['total'])) : 0,
            'calendario' => CalendarioPagos::de($grupo),
            'reservas' => $reservas,
            'whatsapp' => $reservas->mapWithKeys(fn (Reserva $r): array => [$r->id => $this->enlaceWhatsapp($grupo, $r)])->all(),
            'comprobantes' => $grupo->comprobantes()->latest('id')->limit(50)->get(),
            'mensajes' => $grupo->mensajes()->orderBy('id')->limit(200)->get(),
            'aceptaciones' => Aceptacion::query()->with('documento')->where('grupo_id', $grupo->id)->latest('id')->get(),
            'instrucciones' => (new AjustesMotor($grupo->propiedad))->instrucciones(),
        ];
    }

    /**
     * Lo que le manda a quien va en esa habitación, con su registro en línea si el alojamiento lo usa.
     */
    private function enlaceWhatsapp(Grupo $grupo, Reserva $reserva): ?string
    {
        $telefono = $reserva->habitacionGrupo?->telefono;
        if (blank($telefono)) {
            return null;
        }
        $texto = __('grupos.publico.whatsapp', [
            'nombre' => (string) $reserva->habitacionGrupo->responsable,
            'propiedad' => $grupo->propiedad->nombre,
            'unidad' => $reserva->unidad->nombre,
            'llegada' => Formato::fecha($reserva->llegada),
            'salida' => Formato::fecha($reserva->salida),
        ]);
        if (Modulos::activo(Modulo::Llegada)) {
            $texto .= "\n".__('grupos.publico.whatsapp_llegada', ['enlace' => app(GestorLlegadas::class)->urlDe($reserva, app()->getLocale())]);
        }

        return VariablesMensaje::enlaceWhatsapp($telefono, $texto, $grupo->propiedad->pais);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function pagina(string $vista, Grupo $grupo, string $token, array $datos = []): Response
    {
        return response()->view($vista, ['grupo' => $grupo, 'propiedad' => $grupo->propiedad, 'token' => $token] + $datos)->header('Cache-Control', 'private, no-store');
    }

    private function aLaCuenta(string $token, string $ancla = ''): RedirectResponse
    {
        return redirect()->to(route('grupo.ver', ['token' => $token, 'idioma' => app()->getLocale()]).$ancla);
    }

    private function grupo(Request $request, string $token): Grupo
    {
        $grupo = $this->acceso->grupoDelToken($token);
        abort_if($grupo === null, 404);
        $grupo->load('propiedad');

        $idiomas = (array) config('alojaflow.idiomas');
        $idioma = $request->query('idioma');
        if (! in_array($idioma, $idiomas, true)) {
            $idioma = $request->headers->has('Accept-Language') ? (string) $request->getPreferredLanguage($idiomas) : $grupo->propiedad->idioma;
        }
        app()->setLocale((string) $idioma);

        return $grupo;
    }

    /**
     * Para lo que se hace ya dentro: sin clave cambiada o sin aceptar, de vuelta a donde toca.
     */
    private function exigirCuenta(Request $request, string $token): Grupo
    {
        $grupo = $this->grupo($request, $token);
        if (! $this->acceso->disponible($grupo) || ! $this->acceso->estaDentro($grupo, $request) || $grupo->debe_cambiar_clave
            || $this->contrato->pendientes($grupo, app()->getLocale()) !== []) {
            throw new HttpResponseException($this->aLaCuenta($token));
        }

        return $grupo;
    }
}
