<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Sincronizacion\AlojaFlow;

use App\Models\ConexionCompartida;
use App\Models\Propiedad;
use App\Models\RegistroAuditoria;
use InvalidArgumentException;

/**
 * Compartir una propiedad con otra instalación (D-073), del lado de la principal. Solo las independientes: así no hay
 * cadenas de subordinación.
 */
final class GestorConexiones
{
    /**
     * @return array{conexion: ConexionCompartida, codigo: string} el código solo se puede ver esta vez
     */
    public function crear(Propiedad $propiedad, string $nombre, bool $incluirDocumentos): array
    {
        if (! $propiedad->esIndependiente()) {
            throw new InvalidArgumentException('Solo se comparte una propiedad independiente.');
        }
        $token = bin2hex(random_bytes(32));
        $conexion = ConexionCompartida::query()->create([
            'propiedad_id' => $propiedad->id,
            'nombre' => $nombre,
            'token_hash' => hash('sha256', $token),
            'incluir_documentos' => $incluirDocumentos,
        ]);
        RegistroAuditoria::anotar('conexion.crear', $conexion, ['nombre' => $nombre, 'incluir_documentos' => $incluirDocumentos], $propiedad->id);

        return ['conexion' => $conexion, 'codigo' => CodigoConexion::armar(rtrim((string) config('app.url'), '/').'/', $token)];
    }

    public function revocar(ConexionCompartida $conexion): void
    {
        $conexion->update(['revocada_en' => now('UTC')]);
        RegistroAuditoria::anotar('conexion.revocar', $conexion, ['nombre' => $conexion->nombre], $conexion->propiedad_id);
    }

    /**
     * La conexión de un token, si sigue vigente.
     */
    public function vigente(string $token): ?ConexionCompartida
    {
        return ConexionCompartida::query()->with('propiedad')->where('token_hash', hash('sha256', $token))->whereNull('revocada_en')->first();
    }
}
