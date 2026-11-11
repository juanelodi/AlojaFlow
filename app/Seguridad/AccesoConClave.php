<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Seguridad;

use App\Enums\TipoEnlace;
use App\Models\EnlacePublico;
use App\Models\Grupo;
use App\Models\Propietario;
use App\Models\RegistroAuditoria;
use App\Support\ClaveCliente;
use App\Support\Huellas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Cómo entra a su página quien no es usuario del panel (D-096): hacen falta las dos cosas, el enlace secreto y la clave. Su sesión solo vale para lo suyo, y cambiar la
 * clave saca a quien tuviera la anterior.
 */
abstract class AccesoConClave
{
    private const LARGO_CLAVE = 12;

    private const ERRORES_MAXIMOS = 5;

    private const MINUTOS_BLOQUEO = 15;

    private const ERRORES_POR_CONEXION = 20;

    abstract protected function tipoEnlace(): TipoEnlace;

    /**
     * Con qué se anota en la auditoría y se cuentan los errores de una conexión.
     */
    abstract protected function prefijo(): string;

    abstract protected function claveDeSesion(Grupo|Propietario $titular): string;

    /**
     * Una clave nueva cambia también el enlace: el que tuviera el anterior ya no sirve de nada.
     *
     * @return string la clave en claro, para mostrarla
     */
    public function generarClave(Grupo|Propietario $titular): string
    {
        $clave = Huellas::codigoPublico(self::LARGO_CLAVE);
        DB::transaction(function () use ($titular, $clave): void {
            EnlacePublico::query()->where('objeto_type', $titular->getMorphClass())->where('objeto_id', $titular->id)
                ->where('tipo', $this->tipoEnlace()->value)->whereNull('revocado_en')->update(['revocado_en' => now('UTC')]);
            EnlacePublico::nuevo($titular->propiedad_id, $this->tipoEnlace(), $titular);
            $titular->forceFill([
                'acceso_activo' => true,
                'clave_hash' => Hash::make($clave),
                'clave_cifrada' => $clave,
                'debe_cambiar_clave' => true,
                'intentos_fallidos' => 0,
                'bloqueado_hasta' => null,
            ])->save();
            RegistroAuditoria::anotar($this->prefijo().'.clave_nueva', $titular, [], $titular->propiedad_id);
        });

        return $clave;
    }

    public function desactivar(Grupo|Propietario $titular): void
    {
        $titular->forceFill(['acceso_activo' => false])->save();
        RegistroAuditoria::anotar($this->prefijo().'.acceso_desactivado', $titular, [], $titular->propiedad_id);
    }

    public function activar(Grupo|Propietario $titular): void
    {
        $titular->forceFill(['acceso_activo' => true])->save();
        RegistroAuditoria::anotar($this->prefijo().'.acceso_activado', $titular, [], $titular->propiedad_id);
    }

    protected function enlaceVigente(Grupo|Propietario $titular): ?EnlacePublico
    {
        return EnlacePublico::query()->where('objeto_type', $titular->getMorphClass())->where('objeto_id', $titular->id)
            ->where('tipo', $this->tipoEnlace()->value)->whereNull('revocado_en')->latest('id')->first();
    }

    /**
     * Quien se equivoca recibe siempre la misma respuesta, esté bloqueado o no, y la clave se comprueba igual: bloqueada no se nota ni en lo que tarda.
     */
    public function entrar(Grupo|Propietario $titular, #[\SensitiveParameter] string $clave, Request $request): bool
    {
        $conexion = $this->prefijo().'-conexion:'.ClaveCliente::de($request);
        $correcta = Hash::check($clave, (string) $titular->clave_hash);
        $bloqueada = $titular->bloqueado_hasta?->isFuture() === true || RateLimiter::tooManyAttempts($conexion, self::ERRORES_POR_CONEXION);
        if (! $correcta || $bloqueada) {
            RateLimiter::hit($conexion, 3600);
            if (! $bloqueada) {
                $this->anotarError($titular);
            }

            return false;
        }

        $titular->forceFill(['intentos_fallidos' => 0, 'bloqueado_hasta' => null, 'ultimo_acceso_en' => now('UTC')])->save();
        // Otra sesión: quien hubiera fijado el identificador antes de entrar no entra con ella.
        $request->session()->regenerate();
        $this->marcarDentro($titular, $request);

        return true;
    }

    public function estaDentro(Grupo|Propietario $titular, Request $request): bool
    {
        $marca = $request->session()->get($this->claveDeSesion($titular));

        return is_string($marca) && hash_equals($this->huella($titular), $marca);
    }

    public function salir(Grupo|Propietario $titular, Request $request): void
    {
        $request->session()->forget($this->claveDeSesion($titular));
        $request->session()->regenerate();
    }

    /**
     * La que elige nunca se guarda en claro: desde aquí, ni el alojamiento puede verla.
     */
    public function cambiarClave(Grupo|Propietario $titular, #[\SensitiveParameter] string $nueva, Request $request): void
    {
        $titular->forceFill(['clave_hash' => Hash::make($nueva), 'clave_cifrada' => null, 'debe_cambiar_clave' => false])->save();
        $request->session()->regenerate();
        $this->marcarDentro($titular, $request);
        RegistroAuditoria::anotar($this->prefijo().'.clave_cambiada', $titular, [], $titular->propiedad_id);
    }

    private function anotarError(Grupo|Propietario $titular): void
    {
        $errores = $titular->intentos_fallidos + 1;
        $titular->forceFill($errores >= self::ERRORES_MAXIMOS
            ? ['intentos_fallidos' => 0, 'bloqueado_hasta' => now('UTC')->addMinutes(self::MINUTOS_BLOQUEO)]
            : ['intentos_fallidos' => $errores])->save();
    }

    private function marcarDentro(Grupo|Propietario $titular, Request $request): void
    {
        $request->session()->put($this->claveDeSesion($titular), $this->huella($titular));
    }

    /**
     * Cambia con la clave: quien entró con la anterior deja de estar dentro.
     */
    private function huella(Grupo|Propietario $titular): string
    {
        return hash_hmac('sha256', (string) $titular->clave_hash, (string) config('app.key'));
    }
}
