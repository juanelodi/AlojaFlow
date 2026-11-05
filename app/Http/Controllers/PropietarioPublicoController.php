<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Archivos\GestorArchivos;
use App\Comunicacion\Mensajero;
use App\Documentos\GeneradorPdf;
use App\Enums\FaseEstadoCuenta;
use App\Enums\TipoReserva;
use App\Models\EstadoCuenta;
use App\Models\Movimiento;
use App\Models\Propietario;
use App\Models\Reserva;
use App\Propietarios\AccesoPropietario;
use App\Propietarios\PortalPropietario;
use App\Reservas\GestorReservas;
use App\Reservas\ReservaRechazada;
use App\Support\Formato;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * El portal del dueño (D-116): enlace secreto y clave, como un coordinador. Solo ve lo suyo, y nunca quién se queda en su unidad.
 */
class PropietarioPublicoController extends Controller
{
    public function __construct(private readonly AccesoPropietario $acceso, private readonly PortalPropietario $portal)
    {
    }

    public function ver(Request $request, string $token): View
    {
        $propietario = $this->propietario($request, $token);
        if (! $this->acceso->estaDentro($propietario, $request)) {
            return $this->pagina('publico.propietario-entrar', $propietario, $token);
        }
        if ($propietario->debe_cambiar_clave) {
            return $this->pagina('publico.propietario-clave', $propietario, $token);
        }

        return $this->pagina('publico.propietario', $propietario, $token, $this->portal->datos($propietario) + ['aviso' => $request->query('aviso')]);
    }

    public function entrar(Request $request, string $token): RedirectResponse
    {
        $propietario = $this->propietario($request, $token);
        $datos = $request->validate(['clave' => ['required', 'string', 'max:200']]);
        if (! $this->acceso->entrar($propietario, (string) $datos['clave'], $request)) {
            throw ValidationException::withMessages(['clave' => __('grupos.publico.clave_incorrecta')]);
        }

        return $this->volver($token);
    }

    public function salir(Request $request, string $token): RedirectResponse
    {
        $this->acceso->salir($this->propietario($request, $token), $request);

        return $this->volver($token);
    }

    public function cambiarClave(Request $request, string $token): RedirectResponse
    {
        $propietario = $this->dentro($request, $token);
        $datos = $request->validate(['clave' => ['required', 'string', 'confirmed', Password::min(8), 'max:200']], [], ['clave' => __('grupos.publico.clave_nueva')]);
        $this->acceso->cambiarClave($propietario, (string) $datos['clave'], $request);

        return $this->volver($token);
    }

    /**
     * Se aparta sus fechas él solo, si el acuerdo lo deja. El alojamiento se entera: esas noches ya no se venden.
     */
    public function bloquear(Request $request, string $token, GestorReservas $reservas): RedirectResponse
    {
        $propietario = $this->enSuCuenta($request, $token);
        abort_unless($propietario->puede_bloquear, 403);
        $propiedad = $propietario->propiedad;
        $datos = $request->validate([
            'unidad_id' => ['required', 'integer'],
            'llegada' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$propiedad->hoy()->format('Y-m-d')],
            'salida' => ['required', 'date_format:Y-m-d', 'after:llegada'],
        ], [], ['llegada' => __('ui.comun.llegada'), 'salida' => __('ui.comun.salida')]);
        $unidad = $propietario->unidades()->where('activa', true)->find((int) $datos['unidad_id']);
        abort_if($unidad === null, 403);

        try {
            $estancia = $reservas->crear($propiedad, [
                'tipo' => TipoReserva::Bloqueo->value,
                'unidad_id' => $unidad->id,
                'llegada' => $datos['llegada'],
                'salida' => $datos['salida'],
                'origen_texto' => __('propietarios.estancia_dueno', [], $propiedad->idioma),
                'propietario_id' => $propietario->id,
            ]);
        } catch (ReservaRechazada $e) {
            throw ValidationException::withMessages(['llegada' => $e->getMessage()]);
        }
        $this->avisarAlAlojamiento($propietario, $estancia, 'aviso');

        return redirect()->to(route('propietario.ver', ['token' => $token, 'idioma' => app()->getLocale(), 'aviso' => 'bloqueado']).'#estancias');
    }

    /**
     * Solo las suyas, y mientras no hayan empezado. También se avisa: esas noches ya se pueden vender.
     */
    public function quitarBloqueo(Request $request, string $token, int $reserva, GestorReservas $reservas): RedirectResponse
    {
        $propietario = $this->enSuCuenta($request, $token);
        abort_unless($propietario->puede_bloquear, 403);
        $bloqueo = Reserva::query()->where('propietario_id', $propietario->id)->where('tipo', TipoReserva::Bloqueo->value)->where('llegada', '>', $propietario->propiedad->hoy()->format('Y-m-d'))->findOrFail($reserva);
        $reservas->cancelar($bloqueo);
        $this->avisarAlAlojamiento($propietario, $bloqueo, 'aviso_libera');

        return redirect()->to(route('propietario.ver', ['token' => $token, 'idioma' => app()->getLocale(), 'aviso' => 'desbloqueado']).'#estancias');
    }

    public function estadoPdf(Request $request, string $token, int $estado, GeneradorPdf $pdf): Response
    {
        $propietario = $this->enSuCuenta($request, $token);
        $fila = EstadoCuenta::query()->where('propietario_id', $propietario->id)->where('fase', '!=', FaseEstadoCuenta::Borrador->value)->findOrFail($estado);

        return response($pdf->estadoCuenta($fila), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="estado-'.$fila->desde->format('Y-m').'.pdf"']);
    }

    /**
     * El comprobante de un gasto que se le cargó.
     */
    public function comprobante(Request $request, string $token, int $movimiento, GestorArchivos $archivos): Response
    {
        $propietario = $this->enSuCuenta($request, $token);
        $archivo = Movimiento::query()->with('comprobante')->where('propietario_id', $propietario->id)->findOrFail($movimiento)->comprobante;
        abort_if($archivo === null, 404);

        $extension = preg_replace('/[^a-z0-9]/', '', mb_strtolower(pathinfo($archivo->nombre_original, PATHINFO_EXTENSION)));
        $nombre = rtrim('comprobante-'.$movimiento.'.'.$extension, '.');

        return response($archivos->contenido($archivo), 200, ['Content-Type' => 'application/octet-stream', 'Content-Disposition' => 'attachment; filename="'.$nombre.'"']);
    }

    /**
     * Al alojamiento, en su idioma y con las fechas en su formato.
     */
    private function avisarAlAlojamiento(Propietario $propietario, Reserva $estancia, string $aviso): void
    {
        $propiedad = $propietario->propiedad;
        $anterior = app()->getLocale();
        app()->setLocale($propiedad->idioma);
        $datos = ['nombre' => $propietario->nombre, 'unidad' => $estancia->unidad->nombre, 'llegada' => Formato::fecha($estancia->llegada), 'salida' => Formato::fecha($estancia->salida)];
        $asunto = __('propietarios.portal.'.$aviso.'_asunto', $datos);
        $cuerpo = __('propietarios.portal.'.$aviso.'_cuerpo', $datos);
        app()->setLocale($anterior);
        app(Mensajero::class)->avisarSinReserva($propiedad, $aviso.':'.$estancia->id, $propiedad->getAttribute('correo'), $asunto, $cuerpo, $propiedad->idioma);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function pagina(string $vista, Propietario $propietario, string $token, array $datos = []): View
    {
        return view($vista, $datos + ['propiedad' => $propietario->propiedad, 'propietario' => $propietario, 'token' => $token]);
    }

    private function volver(string $token): RedirectResponse
    {
        return redirect()->route('propietario.ver', ['token' => $token, 'idioma' => app()->getLocale()]);
    }

    private function propietario(Request $request, string $token): Propietario
    {
        $propietario = $this->acceso->propietarioDelToken($token);
        abort_if($propietario === null || ! $this->acceso->disponible($propietario), 404);
        $idiomas = (array) config('alojaflow.idiomas');
        $idioma = $request->query('idioma');
        $principal = $propietario->idioma ?? $propietario->propiedad->idioma;
        app()->setLocale(in_array($idioma, $idiomas, true) ? (string) $idioma : $principal);

        return $propietario;
    }

    /**
     * Para lo que se hace ya dentro.
     */
    private function dentro(Request $request, string $token): Propietario
    {
        $propietario = $this->propietario($request, $token);
        abort_unless($this->acceso->estaDentro($propietario, $request), 403);

        return $propietario;
    }

    /**
     * Lo de su cuenta: mientras siga con la clave que le dieron, de vuelta a cambiarla. Esa clave también la conoce el alojamiento.
     */
    private function enSuCuenta(Request $request, string $token): Propietario
    {
        $propietario = $this->dentro($request, $token);
        if ($propietario->debe_cambiar_clave) {
            throw new HttpResponseException($this->volver($token));
        }

        return $propietario;
    }
}
