<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Instalación sencilla: el dominio apunta a la carpeta de arriba y su .htaccess lo manda todo aquí.
// Las direcciones se calculan como si public/ no existiera, también dentro de una subcarpeta.
$carpetaPublica = dirname((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
if (str_ends_with($carpetaPublica, '/public') && ! str_starts_with((string) ($_SERVER['REQUEST_URI'] ?? ''), $carpetaPublica.'/')) {
    $_SERVER['SCRIPT_NAME'] = substr($carpetaPublica, 0, -7).'/index.php';
    $_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'];
}

// Determine if the application is in maintenance mode... En el servicio alojado (D-162), el de la instancia.
$instancia = $_SERVER['ALOJAFLOW_INSTANCIA'] ?? $_ENV['ALOJAFLOW_INSTANCIA'] ?? getenv('ALOJAFLOW_INSTANCIA');
$almacen = is_string($instancia) && $instancia !== '' ? rtrim($instancia, '/\\').'/storage' : __DIR__.'/../storage';
if (file_exists($maintenance = $almacen.'/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
