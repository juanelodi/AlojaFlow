<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Console\Commands;

use App\Copias\CifradoCopia;
use App\Copias\ErrorCopia;
use Illuminate\Console\Command;

/**
 * Descifra una copia de las que salen del servidor (.afcopia, D-152) para restaurarla en otro lado. La clave
 * se escribe sin que se vea; también se puede subir el .afcopia en Copias de seguridad con su clave.
 */
class DescifrarCopia extends Command
{
    protected $signature = 'alojaflow:descifrar-copia {archivo : El .afcopia} {salida? : Dónde dejar el zip}';

    protected $description = 'Descifra una copia de seguridad que salió del servidor';

    public function handle(): int
    {
        $archivo = (string) $this->argument('archivo');
        if (! is_file($archivo) || ! CifradoCopia::esCifrada($archivo)) {
            $this->error(__('copias.externa.no_es_cifrada'));

            return self::FAILURE;
        }
        $salida = (string) ($this->argument('salida') ?: preg_replace('/\.afcopia$/', '', $archivo).'.zip');
        $clave = (string) $this->secret(__('copias.externa.clave'));
        try {
            CifradoCopia::descifrar($archivo, $salida, $clave);
        } catch (ErrorCopia $e) {
            @unlink($salida);
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $this->info(__('copias.externa.descifrada', ['archivo' => $salida]));

        return self::SUCCESS;
    }
}
