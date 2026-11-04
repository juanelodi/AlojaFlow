<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

/*
 * Abre una sesión del personal sin pasar por el formulario y escribe la cookie, para que Lighthouse pueda medir pantallas
 * con sesión. Solo sirve en una copia desechable con datos inventados: no se instala ni va en los paquetes.
 *
 *   php desarrollo/rendimiento/sesion.php correo@ejemplo.com
 */

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

Session::start();
Auth::guard('web')->login(User::query()->where('email', $argv[1] ?? '')->firstOrFail());
Session::save();

$nombre = (string) config('session.cookie');
$valor = CookieValuePrefix::create($nombre, app('encrypter')->getKey()).Session::getId();

echo $nombre.'='.urlencode(app('encrypter')->encrypt($valor, false));
