<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Seguridad;

/**
 * Una página del panel pregunta decenas de veces lo mismo («¿en qué propiedades puede?»). Dura lo
 * que dura la petición: un permiso retirado cuenta desde la siguiente.
 */
final class MemoriaAcceso
{
    /** @var array<string, list<int>> */
    private array $valores = [];

    /**
     * @param  callable(): list<int>  $calcular
     * @return list<int>
     */
    public function recordar(string $clave, callable $calcular): array
    {
        return $this->valores[$clave] ??= $calcular();
    }

    public function vaciar(): void
    {
        $this->valores = [];
    }
}
