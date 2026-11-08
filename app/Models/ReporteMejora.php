<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Un error, una sugerencia o una pregunta que alguien del equipo mandó al equipo de AlojaFlow (D-150), con
 * hasta diez imágenes. Las imágenes se quedan en el disco privado solo hasta que se mandan.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $clave
 * @property string $tipo
 * @property string $titulo
 * @property string $descripcion
 * @property string|null $pasos
 * @property string|null $pantalla
 * @property string|null $idioma
 * @property string|null $navegador
 * @property string|null $resolucion
 * @property string|null $rol
 * @property string|null $contacto_nombre
 * @property string|null $contacto_correo
 * @property bool $permite_contacto
 * @property bool $adjuntar_diagnostico
 * @property list<string>|null $imagenes
 * @property int $imagenes_enviadas
 * @property string $envio
 * @property int $intentos
 * @property string|null $ultimo_error
 * @property Carbon|null $enviado_en
 * @property string|null $folio
 * @property string|null $estado
 * @property string|null $respuesta
 * @property Carbon|null $respondido_en
 * @property string|null $resuelto_en_version
 * @property Carbon|null $creado_en
 * @property Carbon|null $actualizado_en
 * @property-read User|null $usuario
 */
class ReporteMejora extends ModeloBase
{
    public const TIPOS = ['error', 'sugerencia', 'informe', 'pregunta', 'otro'];

    public const ENVIO_PENDIENTE = 'pendiente';

    public const ENVIO_ENVIADO = 'enviado';

    public const ENVIO_ERROR = 'error';

    /** El receptor lo rechazó (no se reintenta). */
    public const ENVIO_RECHAZADO = 'rechazado';

    public const ESTADOS = ['nuevo', 'en_revision', 'planeado', 'en_curso', 'resuelto', 'descartado', 'duplicado'];

    protected $table = 'reportes_mejora';

    protected $fillable = [
        'user_id', 'clave', 'tipo', 'titulo', 'descripcion', 'pasos', 'pantalla', 'idioma', 'navegador', 'resolucion', 'rol',
        'contacto_nombre', 'contacto_correo', 'permite_contacto', 'adjuntar_diagnostico', 'imagenes', 'imagenes_enviadas',
        'envio', 'intentos', 'ultimo_error', 'enviado_en', 'folio', 'estado', 'respuesta', 'respondido_en', 'resuelto_en_version',
    ];

    protected $attributes = [
        'envio' => self::ENVIO_PENDIENTE,
        'intentos' => 0,
        'imagenes_enviadas' => 0,
        'permite_contacto' => false,
        'adjuntar_diagnostico' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'imagenes' => 'array',
            'imagenes_enviadas' => 'integer',
            'intentos' => 'integer',
            'permite_contacto' => 'boolean',
            'adjuntar_diagnostico' => 'boolean',
            'enviado_en' => 'datetime',
            'respondido_en' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function cuantasImagenes(): int
    {
        return max($this->imagenes_enviadas, count($this->imagenes ?? []));
    }
}
