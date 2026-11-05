<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Grupos;

use App\Enums\EstadoGrupo;
use App\Enums\TipoEnlace;
use App\Models\EnlacePublico;
use App\Models\Grupo;
use App\Models\RegistroAuditoria;
use App\Support\ClaveCliente;
use App\Support\Huellas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Cómo entra el coordinador a su página (D-096). Hacen falta las dos cosas: el enlace secreto y la clave. El coordinador no es un usuario del panel: su
 * sesión solo vale para ese grupo, y cambiar la clave saca a quien tuviera la anterior.
 */
final class AccesoCoordinador
{
    private const LARGO_CLAVE = 12;

    private const ERRORES_MAXIMOS = 5;

    private const MINUTOS_BLOQUEO = 15;

    private const ERRORES_POR_CONEXION = 20;

    /**
     * Una clave nueva cambia también el enlace: el que tuviera el anterior ya no sirve de nada.
     *
     * @return string la clave en claro, para mostrarla
     */
    public function generarClave(Grupo $grupo): string
    {
        $clave = Huellas::codigoPublico(self::LARGO_CLAVE);
        DB::transaction(function () use ($grupo, $clave): void {
            EnlacePublico::query()->where('objeto_type', $grupo->getMorphClass())->where('objeto_id', $grupo->id)
                ->where('tipo', TipoEnlace::Grupo->value)->whereNull('revocado_en')->update(['revocado_en' => now('UTC')]);
            EnlacePublico::nuevo($grupo->propiedad_id, TipoEnlace::Grupo, $grupo);
            $grupo->forceFill([
                'acceso_activo' => true,
                'clave_hash' => Hash::make($clave),
                'clave_cifrada' => $clave,
                'debe_cambiar_clave' => true,
                'intentos_fallidos' => 0,
                'bloqueado_hasta' => null,
            ])->save();
            RegistroAuditoria::anotar('grupo.clave_nueva', $grupo, [], $grupo->propiedad_id);
        });

        return $clave;
    }

    public function desactivar(Grupo $grupo): void
    {
        $grupo->forceFill(['acceso_activo' => false])->save();
        RegistroAuditoria::anotar('grupo.acceso_desactivado', $grupo, [], $grupo->propiedad_id);
    }

    public function activar(Grupo $grupo): void
    {
        $grupo->forceFill(['acceso_activo' => true])->save();
        RegistroAuditoria::anotar('grupo.acceso_activado', $grupo, [], $grupo->propiedad_id);
    }

    public function url(Grupo $grupo, ?string $idioma = null): ?string
    {
        $enlace = EnlacePublico::query()->where('objeto_type', $grupo->getMorphClass())->where('objeto_id', $grupo->id)
            ->where('tipo', TipoEnlace::Grupo->value)->whereNull('revocado_en')->latest('id')->first();

        return $enlace === null ? null : route('grupo.ver', ['token' => $enlace->token(), 'idioma' => $idioma]);
    }

    public function grupoDelToken(string $token): ?Grupo
    {
        $objeto = EnlacePublico::buscar(TipoEnlace::Grupo, $token)?->objeto;

        return $objeto instanceof Grupo ? $objeto : null;
    }

    /**
     * Caduca unos días después de la salida: lo que queda ahí son datos de otras personas, y al irse ya no hace falta.
     */
    public function disponible(Grupo $grupo): bool
    {
        if (! $grupo->acceso_activo || $grupo->clave_hash === null || $grupo->estado === EstadoGrupo::Cancelado) {
            return false;
        }
        $salida = $grupo->ultimaSalida();

        return $salida === null || $salida->addDays($grupo->dias_acceso_tras_salida)->format('Y-m-d') >= $grupo->propiedad->hoy()->format('Y-m-d');
    }

    /**
     * Quien se equivoca recibe siempre la misma respuesta, esté bloqueado o no, y la clave se comprueba igual: bloqueada no se nota ni en lo que tarda.
     */
    public function entrar(Grupo $grupo, #[\SensitiveParameter] string $clave, Request $request): bool
    {
        $conexion = 'grupo-conexion:'.ClaveCliente::de($request);
        $correcta = Hash::check($clave, (string) $grupo->clave_hash);
        $bloqueada = $grupo->bloqueado_hasta?->isFuture() === true || RateLimiter::tooManyAttempts($conexion, self::ERRORES_POR_CONEXION);
        if (! $correcta || $bloqueada) {
            RateLimiter::hit($conexion, 3600);
            if (! $bloqueada) {
                $this->anotarError($grupo);
            }

            return false;
        }

        $grupo->forceFill(['intentos_fallidos' => 0, 'bloqueado_hasta' => null, 'ultimo_acceso_en' => now('UTC')])->save();
        // Otra sesión: quien hubiera fijado el identificador antes de entrar no entra con ella.
        $request->session()->regenerate();
        $this->marcarDentro($grupo, $request);

        return true;
    }

    public function estaDentro(Grupo $grupo, Request $request): bool
    {
        $marca = $request->session()->get($this->clave($grupo));

        return is_string($marca) && hash_equals($this->huella($grupo), $marca);
    }

    public function salir(Grupo $grupo, Request $request): void
    {
        $request->session()->forget($this->clave($grupo));
        $request->session()->regenerate();
    }

    /**
     * La que elige el coordinador nunca se guarda en claro: desde aquí, ni el alojamiento puede verla.
     */
    public function cambiarClave(Grupo $grupo, #[\SensitiveParameter] string $nueva, Request $request): void
    {
        $grupo->forceFill(['clave_hash' => Hash::make($nueva), 'clave_cifrada' => null, 'debe_cambiar_clave' => false])->save();
        $request->session()->regenerate();
        $this->marcarDentro($grupo, $request);
        RegistroAuditoria::anotar('grupo.clave_cambiada', $grupo, [], $grupo->propiedad_id);
    }

    private function anotarError(Grupo $grupo): void
    {
        $errores = $grupo->intentos_fallidos + 1;
        $grupo->forceFill($errores >= self::ERRORES_MAXIMOS
            ? ['intentos_fallidos' => 0, 'bloqueado_hasta' => now('UTC')->addMinutes(self::MINUTOS_BLOQUEO)]
            : ['intentos_fallidos' => $errores])->save();
    }

    private function marcarDentro(Grupo $grupo, Request $request): void
    {
        $request->session()->put($this->clave($grupo), $this->huella($grupo));
    }

    private function clave(Grupo $grupo): string
    {
        return 'grupo_coordinador.'.$grupo->id;
    }

    /**
     * Cambia con la clave: quien entró con la anterior deja de estar dentro.
     */
    private function huella(Grupo $grupo): string
    {
        return hash_hmac('sha256', (string) $grupo->clave_hash, (string) config('app.key'));
    }
}
