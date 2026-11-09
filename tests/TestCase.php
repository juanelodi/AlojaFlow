<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace Tests;

use App\Instalacion\Instancia;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\File;

abstract class TestCase extends BaseTestCase
{
    private string $almacenDePrueba;

    /**
     * Las pruebas corren de verdad lo que borra archivos (limpieza, reinicio de la demo, rotación de
     * copias): cada una con su propio almacén, para no tocar los datos locales de este equipo.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Lo que corre en un hosting pone su propio límite de tiempo, y dentro del mismo proceso seguiría contando en las demás pruebas.
        set_time_limit(0);

        $this->almacenDePrueba = storage_path('framework/testing/'.uniqid('almacen'));
        // El modo de mantenimiento escribe aquí sin crear la carpeta.
        File::ensureDirectoryExists($this->almacenDePrueba.'/framework');
        $this->app->useStoragePath($this->almacenDePrueba);
        config(['filesystems.disks.local.root' => storage_path('app/private')]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->almacenDePrueba);
        (new \ReflectionProperty(Instancia::class, 'carpeta'))->setValue(null, null);

        parent::tearDown();
    }
}
