<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Actualizacion;

/**
 * Resultado de revisar un paquete sin cambiar nada: si se puede instalar y qué haría.
 */
final class Revision
{
    /** @var list<string> */
    public array $errores = [];

    /** @var list<string> */
    public array $avisos = [];

    public string $version = '';

    public string $base = '';

    public ?string $notas = null;

    public string $manifiestoJson = '';

    /** @var list<string> */
    public array $nuevos = [];

    /** @var list<string> */
    public array $cambiados = [];

    public int $iguales = 0;

    /** @var list<string> obsoletos sin cambios: se borran */
    public array $borrar = [];

    /** @var list<string> obsoletos modificados en esta instalación: se conservan */
    public array $conservados = [];

    public int $modificadosLocalmente = 0;

    public int $bytes = 0;

    public function __construct(
        public readonly string $versionActual,
        public readonly bool $reinstalar,
    ) {
    }

    public function valida(): bool
    {
        return $this->errores === [] && $this->version !== '';
    }

    public function error(string $mensaje): self
    {
        $this->errores[] = $mensaje;

        return $this;
    }

    /**
     * Resumen para la pantalla (las listas largas se recortan).
     *
     * @return array<string, mixed>
     */
    public function resumen(): array
    {
        return [
            'valida' => $this->valida(),
            'errores' => $this->errores,
            'avisos' => $this->avisos,
            'version' => $this->version,
            'version_actual' => $this->versionActual,
            'reinstalar' => $this->reinstalar,
            'notas' => $this->notas,
            'nuevos' => count($this->nuevos),
            'cambiados' => count($this->cambiados),
            'iguales' => $this->iguales,
            'borrar' => count($this->borrar),
            'conservados' => array_slice($this->conservados, 0, 20),
            'modificados_localmente' => $this->modificadosLocalmente,
            'bytes' => $this->bytes,
        ];
    }
}
