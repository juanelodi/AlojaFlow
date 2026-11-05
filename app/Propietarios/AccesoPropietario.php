<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Propietarios;

use App\Enums\TipoEnlace;
use App\Models\EnlacePublico;
use App\Models\Grupo;
use App\Models\Propietario;
use App\Seguridad\AccesoConClave;

/**
 * Cómo entra el dueño a su portal (D-116): lo mismo que un coordinador. Mientras esté activo: un dueño no se va.
 */
final class AccesoPropietario extends AccesoConClave
{
    protected function tipoEnlace(): TipoEnlace
    {
        return TipoEnlace::Propietario;
    }

    protected function prefijo(): string
    {
        return 'propietario';
    }

    protected function claveDeSesion(Grupo|Propietario $titular): string
    {
        return 'propietario_portal.'.$titular->id;
    }

    public function url(Propietario $propietario, ?string $idioma = null): ?string
    {
        $enlace = $this->enlaceVigente($propietario);

        return $enlace === null ? null : route('propietario.ver', ['token' => $enlace->token(), 'idioma' => $idioma]);
    }

    public function propietarioDelToken(string $token): ?Propietario
    {
        $objeto = EnlacePublico::buscar(TipoEnlace::Propietario, $token)?->objeto;

        return $objeto instanceof Propietario ? $objeto : null;
    }

    public function disponible(Propietario $propietario): bool
    {
        return $propietario->activo && $propietario->acceso_activo && $propietario->clave_hash !== null;
    }
}
