<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

/*
 * Router para servir con el servidor de PHP una instalación hecha desde el zip (no la copia de
 * desarrollo), con límites de subida suficientes para probar Sistema → Actualizaciones. Solo
 * para desarrollo; no va en los paquetes. Uso (desde la raíz del repositorio):
 *
 *   C:\wamp64\bin\php\php8.3.28\php.exe -d upload_max_filesize=512M -d post_max_size=512M ^
 *     -S 127.0.0.1:8001 -t E:\AlojaFlow-Proyecto\AF-PRUEBAS\alojaflow-prueba\public ^
 *     desarrollo\servidor-instalacion.php
 *
 * `php artisan serve` no sirve para esto: su servidor no hereda los -d de los límites.
 */

$publica = (string) $_SERVER['DOCUMENT_ROOT'];
chdir($publica);

return require $publica.'/../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php';
