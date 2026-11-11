<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Autoridades\Ses;

use App\Models\Propiedad;
use App\Support\Ajustes;
use Illuminate\Support\Carbon;
use SensitiveParameter;

/**
 * La conexión de una propiedad con SES.Hospedajes. La contraseña va cifrada, aparte de lo demás.
 */
final class AjustesSes
{
    public const MODO_PRUEBAS = 'pruebas';

    public const MODO_PRODUCCION = 'produccion';

    public function __construct(private readonly Propiedad $propiedad)
    {
    }

    public function conectada(): bool
    {
        return $this->desde() !== null && filled($this->arrendador()) && filled($this->establecimiento()) && filled($this->usuario()) && filled($this->contrasena());
    }

    public function desde(): ?Carbon
    {
        $desde = $this->valor('desde');

        return is_string($desde) && $desde !== '' ? Carbon::parse($desde, 'UTC') : null;
    }

    public function modo(): string
    {
        return $this->valor('modo') === self::MODO_PRODUCCION ? self::MODO_PRODUCCION : self::MODO_PRUEBAS;
    }

    public function arrendador(): ?string
    {
        return $this->texto('arrendador');
    }

    public function establecimiento(): ?string
    {
        return $this->texto('establecimiento');
    }

    public function usuario(): ?string
    {
        return $this->texto('usuario');
    }

    public function internet(): ?bool
    {
        $internet = $this->valor('internet');

        return is_bool($internet) ? $internet : null;
    }

    public function contrasena(): ?string
    {
        return Ajustes::secreto($this->claveContrasena());
    }

    public function cliente(): ClienteSes
    {
        return new ClienteSes($this->modo(), (string) $this->usuario(), (string) $this->contrasena(), (string) $this->arrendador());
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function guardar(array $datos, #[SensitiveParameter] ?string $contrasena): void
    {
        $ajustes = (array) $this->propiedad->ajustes;
        $ajustes['ses'] = $datos;
        $this->propiedad->update(['ajustes' => $ajustes]);
        if (filled($contrasena)) {
            Ajustes::guardarSecreto($this->claveContrasena(), $contrasena);
        }
    }

    public function desconectar(): void
    {
        $ajustes = (array) $this->propiedad->ajustes;
        unset($ajustes['ses']);
        $this->propiedad->update(['ajustes' => $ajustes]);
        Ajustes::guardarSecreto($this->claveContrasena(), null);
    }

    private function claveContrasena(): string
    {
        return 'autoridad.'.$this->propiedad->id.'.ses';
    }

    private function texto(string $clave): ?string
    {
        $valor = $this->valor($clave);

        return is_string($valor) && $valor !== '' ? $valor : null;
    }

    private function valor(string $clave): mixed
    {
        return ((array) $this->propiedad->ajustes)['ses'][$clave] ?? null;
    }
}
