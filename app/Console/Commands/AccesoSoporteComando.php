<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Console\Commands;

use App\Seguridad\AccesoSoporte;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Lo pide AlojaFlow App cuando alguien del equipo va a atender a un cliente (D-166): crea o reactiva al usuario
 * «Soporte AlojaFlow — nombre» y da un enlace de un solo uso que caduca en 5 minutos. Sin el permiso del cliente, solo
 * con --emergencia y su motivo, y el cliente recibe un correo.
 */
class AccesoSoporteComando extends Command
{
    protected $signature = 'alojaflow:acceso-soporte
        {--nombre= : Nombre de quien atiende}
        {--minutos=60 : Cuánto tiempo puede estar dentro}
        {--emergencia= : Motivo, para entrar sin el permiso del cliente}
        {--json : Responder en JSON (para AlojaFlow App)}';

    protected $description = 'Da al soporte de AlojaFlow un enlace de entrada de un solo uso, con el permiso del cliente';

    public function handle(AccesoSoporte $soporte): int
    {
        $minutos = filter_var($this->option('minutos'), FILTER_VALIDATE_INT);
        try {
            $acceso = $soporte->abrir((string) $this->option('nombre'), $minutos === false ? 0 : $minutos, $this->option('emergencia') === null ? null : (string) $this->option('emergencia'));
        } catch (RuntimeException $e) {
            if ($this->option('json')) {
                $this->line((string) json_encode(['enlace' => null, 'error' => $e->getMessage()], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            } else {
                $this->error($e->getMessage());
            }

            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line((string) json_encode([
                'enlace' => $acceso['enlace'],
                'usuario' => $acceso['usuario']->name,
                'hasta' => $acceso['hasta']->toIso8601String(),
                'enlace_caduca_en_min' => AccesoSoporte::MINUTOS_ENLACE,
                'aviso_enviado' => $acceso['aviso_enviado'],
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        } else {
            $this->info(__('soporte.consola.listo', ['usuario' => $acceso['usuario']->name, 'hasta' => $acceso['hasta']->toDateTimeString().' UTC']));
            $this->line(__('consola.instalar.enlace').' '.$acceso['enlace']);
        }

        return self::SUCCESS;
    }
}
