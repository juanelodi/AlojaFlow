<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Console\Commands;

use App\Instalacion\EnlaceEntrada;
use App\Instalacion\Instancia;
use App\Models\RegistroAuditoria;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Un enlace de un solo uso, que caduca en 30 minutos, para que el super administrador entre sin contraseña (D-071,
 * D-162). Lo pide AlojaFlow App mientras el dueño no ha creado la suya. No sirve a quien activó la verificación en
 * dos pasos, y queda en la auditoría.
 */
class EnlaceEntradaComando extends Command
{
    protected $signature = 'alojaflow:enlace-entrada
        {--correo= : Del super administrador; si no se dice, el primero activo}
        {--json : Responder en JSON (para AlojaFlow App)}';

    protected $description = 'Da un enlace de entrada de un solo uso al super administrador';

    public function handle(): int
    {
        $consulta = User::query()->where('es_superadministrador', true)->where('activo', true)->orderBy('id');
        if (filled($this->option('correo'))) {
            $consulta->where('email', strtolower(trim((string) $this->option('correo'))));
        }
        $usuario = $consulta->first();
        if ($usuario === null) {
            return $this->fallar(__('consola.enlace_entrada.sin_super'));
        }
        // En el servicio alojado solo sirve mientras el dueño no ha creado su contraseña: después, el equipo de AlojaFlow
        // entra como soporte y con el permiso del cliente (D-166), nunca como él.
        if (Instancia::activa() && ! $usuario->debe_crear_contrasena) {
            return $this->fallar(__('consola.enlace_entrada.ya_tiene_contrasena'));
        }
        if (filled($usuario->getAppAuthenticationSecret())) {
            return $this->fallar(__('consola.enlace_entrada.dos_pasos'));
        }
        $enlace = EnlaceEntrada::crear($usuario);
        RegistroAuditoria::anotar('instalacion.enlace_entrada', $usuario);

        if ($this->option('json')) {
            $this->line((string) json_encode(['enlace' => $enlace], JSON_UNESCAPED_SLASHES));
        } else {
            $this->line(__('consola.instalar.enlace').' '.$enlace);
        }

        return self::SUCCESS;
    }

    private function fallar(string $mensaje): int
    {
        if ($this->option('json')) {
            $this->line((string) json_encode(['enlace' => null, 'error' => $mensaje], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        } else {
            $this->error($mensaje);
        }

        return self::FAILURE;
    }
}
