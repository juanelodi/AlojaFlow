<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actualizacion\Actualizador;
use App\Actualizacion\ErrorActualizacion;
use App\Enums\EstadoActualizacion;
use App\Filament\Pages\Actualizaciones;
use App\Models\Actualizacion;
use App\Seguridad\Acceso;
use Illuminate\Foundation\Http\MaintenanceModeBypassCookie;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Las dos fases de una actualización, cada una en su propia petición para que la segunda ya se
 * ejecute con el código nuevo. Rutas firmadas, solo para administradores y exentas del modo
 * mantenimiento (bootstrap/app.php). Repetir una petición (recargar) no repite la fase: se sigue
 * desde donde esté la actualización.
 */
class ActualizacionController extends Controller
{
    public function __construct(private readonly Actualizador $actualizador)
    {
    }

    public function aplicar(Actualizacion $actualizacion): RedirectResponse
    {
        $this->soloAdministrador();
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
        $this->soloAdministrador();
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
        $this->soloAdministrador();
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

    private function soloAdministrador(): void
    {
        abort_unless((bool) Acceso::usuarioActual()?->es_administrador, 403);
    }
}
