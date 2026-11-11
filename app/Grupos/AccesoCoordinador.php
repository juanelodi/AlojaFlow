<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Grupos;

use App\Enums\EstadoGrupo;
use App\Enums\TipoEnlace;
use App\Models\EnlacePublico;
use App\Models\Grupo;
use App\Models\Propietario;
use App\Seguridad\AccesoConClave;

/**
 * Cómo entra el coordinador a la página de su grupo (D-096).
 */
final class AccesoCoordinador extends AccesoConClave
{
    protected function tipoEnlace(): TipoEnlace
    {
        return TipoEnlace::Grupo;
    }

    protected function prefijo(): string
    {
        return 'grupo';
    }

    protected function claveDeSesion(Grupo|Propietario $titular): string
    {
        return 'grupo_coordinador.'.$titular->id;
    }

    public function url(Grupo $grupo, ?string $idioma = null): ?string
    {
        $enlace = $this->enlaceVigente($grupo);

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
}
