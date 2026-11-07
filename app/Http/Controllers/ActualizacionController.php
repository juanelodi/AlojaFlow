<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actualizacion\Actualizador;
use App\Actualizacion\DescargaPorPedazos;
use App\Actualizacion\ErrorActualizacion;
use App\Actualizacion\SubidaPorPartes;
use App\Actualizacion\UltimaVersion;
use App\Demo\ModoDemo;
use App\Enums\EstadoActualizacion;
use App\Filament\Pages\Actualizaciones;
use App\Models\Actualizacion;
use App\Seguridad\Acceso;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Http\MaintenanceModeBypassCookie;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Las dos fases de una actualización, cada una en su propia petición para que la segunda ya se
 * ejecute con el código nuevo. Rutas firmadas, solo para el super administrador y exentas del modo
 * mantenimiento (bootstrap/app.php). Repetir una petición (recargar) no repite la fase: se sigue
 * desde donde esté la actualización.
 */
class ActualizacionController extends Controller
{
    public function __construct(private readonly Actualizador $actualizador)
    {
    }

    /**
     * Una parte del zip (D-072). Responde siempre JSON: lo lee el JavaScript de la pantalla.
     */
    public function subirParte(Request $request, SubidaPorPartes $subida): JsonResponse
    {
        $this->soloSuper();
        abort_if(ModoDemo::activo(), 403);
        $parte = $request->file('parte');
        if (! $parte instanceof UploadedFile) {
            return response()->json(['ok' => false, 'error' => __('actualizaciones.error.subida_parte', ['parte' => $request->integer('indice') + 1])], 422);
        }
        try {
            $paquete = $subida->recibir($parte, $request->string('subida')->toString(), $request->string('nombre')->toString(), $request->integer('indice'), $request->integer('total'));
        } catch (ErrorActualizacion $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'paquete' => $paquete]);
    }

    /**
     * Un pedazo de la versión nueva (D-140). No se elige qué bajar: solo la última que se sabe publicada.
     */
    public function descargarPedazo(DescargaPorPedazos $descarga, UltimaVersion $ultimaVersion): JsonResponse
    {
        $this->soloSuper();
        abort_if(ModoDemo::activo(), 403);
        $nueva = $ultimaVersion->disponible();
        if ($nueva === null) {
            return response()->json(['ok' => false, 'error' => __('actualizaciones.error.sin_version_nueva')], 422);
        }
        if (function_exists('set_time_limit')) {
            @set_time_limit(120);
        }
        try {
            return response()->json(['ok' => true] + $descarga->avanzar($nueva['version']));
        } catch (ErrorActualizacion $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /**
     * Una página que aparece de inmediato y se queda en pantalla mientras el navegador espera las dos fases: si
     * no, durante unos minutos parece que el botón no hizo nada.
     */
    public function instalando(Actualizacion $actualizacion): View
    {
        $this->soloSuper();

        return view('actualizaciones.instalando', [
            'version' => $actualizacion->version_nueva,
            'enlace' => URL::temporarySignedRoute('actualizaciones.aplicar', now()->addMinutes(30), ['actualizacion' => $actualizacion->id]),
        ]);
    }

    public function aplicar(Actualizacion $actualizacion): RedirectResponse
    {
        $this->soloSuper();
        $this->sinLimiteDeTiempo();

        // El navegador del administrador recibe la cookie que le deja seguir usando la aplicación
        // en mantenimiento: si se cierra antes de terminar, al volver verá el aviso para terminarla.
        $secreto = Str::random(40);
        if ($actualizacion->estado === EstadoActualizacion::Preparada) {
            try {
                $this->actualizador->aplicar($actualizacion, $secreto);
            } catch (ErrorActualizacion $e) {
                return redirect()->to(Actualizaciones::getUrl())->with('actualizacion_error', $e->getMessage());
            }

            return redirect()->to($this->enlaceFinalizar($actualizacion))->withCookie(MaintenanceModeBypassCookie::create($secreto));
        }

        return $this->segunEstado($actualizacion->refresh());
    }

    public function finalizar(Actualizacion $actualizacion): RedirectResponse
    {
        $this->soloSuper();
        $this->sinLimiteDeTiempo();

        if ($actualizacion->estado === EstadoActualizacion::ArchivosAplicados) {
            try {
                $this->actualizador->finalizar($actualizacion);
            } catch (ErrorActualizacion $e) {
                return redirect()->to(Actualizaciones::getUrl())->with('actualizacion_error', $e->getMessage());
            }
        }

        return $this->segunEstado($actualizacion->refresh());
    }

    public function copiaBaseDatos(Actualizacion $actualizacion): BinaryFileResponse
    {
        $this->soloSuper();
        $ruta = $this->actualizador->carpetaDe($actualizacion).'/'.Actualizador::COPIA_BASE_DATOS;
        abort_unless(is_file($ruta), 404);

        return response()->download($ruta, 'alojaflow-'.$actualizacion->version_anterior.'-base-datos-'.$actualizacion->id.'.sql.gz');
    }

    private function segunEstado(Actualizacion $actualizacion): RedirectResponse
    {
        $panel = redirect()->to(Actualizaciones::getUrl());

        return match ($actualizacion->estado) {
            EstadoActualizacion::ArchivosAplicados => redirect()->to($this->enlaceFinalizar($actualizacion)),
            EstadoActualizacion::Completada => $panel->with('actualizacion_ok', $actualizacion->version_nueva),
            EstadoActualizacion::Fallida, EstadoActualizacion::Revertida => $panel->with('actualizacion_error', (string) $actualizacion->detalle),
            default => $panel,
        };
    }

    private function enlaceFinalizar(Actualizacion $actualizacion): string
    {
        return URL::temporarySignedRoute('actualizaciones.finalizar', now()->addHour(), ['actualizacion' => $actualizacion->id]);
    }

    private function sinLimiteDeTiempo(): void
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }
        ignore_user_abort(true);
    }

    private function soloSuper(): void
    {
        abort_unless(Acceso::puedeActualizar(Acceso::usuarioActual()), 403);
    }
}
