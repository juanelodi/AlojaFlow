<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Archivos\GestorArchivos;
use App\Models\Archivo;
use App\Seguridad\Acceso;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sirve un archivo privado después de comprobar el permiso según su clase.
 */
class ArchivoController extends Controller
{
    /** Lo demás se descarga: un HTML o un SVG subido no debe abrirse dentro de nuestro dominio. */
    private const SE_PUEDEN_VER = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];

    public function __invoke(Archivo $archivo, GestorArchivos $archivos): Response
    {
        $usuario = Acceso::usuarioActual();
        $permitido = $archivo->propiedad_id === null
            ? (bool) $usuario?->es_administrador
            : Acceso::puede($usuario, $archivo->clase->permiso(), $archivo->propiedad_id);
        abort_unless($permitido, 403);

        $disposicion = in_array($archivo->tipo_mime, self::SE_PUEDEN_VER, true) ? 'inline' : 'attachment';

        return response($archivos->contenido($archivo), 200, [
            'Content-Type' => in_array($archivo->tipo_mime, self::SE_PUEDEN_VER, true) ? $archivo->tipo_mime : 'application/octet-stream',
            'Content-Disposition' => $disposicion.'; filename="archivo-'.$archivo->id.'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
