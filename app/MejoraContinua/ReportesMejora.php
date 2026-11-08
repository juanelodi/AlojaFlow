<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\MejoraContinua;

use App\Models\ReporteMejora;
use App\Models\User;
use App\Support\Ajustes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * «Reportar o sugerir» (D-150). Se guarda aquí y se manda en cuanto se puede: primero el texto y luego
 * cada imagen. Lo manda quien lo escribe, así que no depende del permiso de los datos de uso. Las imágenes
 * se borran de esta instalación cuando ya llegaron. La respuesta del equipo se trae con el latido.
 */
final class ReportesMejora
{
    public const AJUSTE_SINCRONIZADOS = 'mejora.reportes_sincronizados_en';

    /** Después de tantos intentos fallidos, se deja de reintentar solo. */
    private const INTENTOS_MAXIMOS = 30;

    public function __construct(private readonly ClienteReceptor $cliente)
    {
    }

    /**
     * @param  array<string, mixed>  $datos  del formulario
     * @param  list<string>  $imagenes  rutas en el disco privado
     */
    public function crear(User $usuario, array $datos, array $imagenes): ReporteMejora
    {
        $peticion = request();

        return ReporteMejora::query()->create([
            'user_id' => $usuario->id,
            'clave' => (string) Str::uuid(),
            'tipo' => in_array($datos['tipo'] ?? null, ReporteMejora::TIPOS, true) ? $datos['tipo'] : 'otro',
            'titulo' => mb_substr(trim((string) ($datos['titulo'] ?? '')), 0, 150),
            'descripcion' => mb_substr(trim((string) ($datos['descripcion'] ?? '')), 0, 10000),
            'pasos' => filled($datos['pasos'] ?? null) ? mb_substr(trim((string) $datos['pasos']), 0, 5000) : null,
            'pantalla' => Saneador::corto(is_string($datos['pantalla'] ?? null) ? $datos['pantalla'] : null),
            'idioma' => app()->getLocale(),
            'navegador' => Saneador::corto($peticion->userAgent()),
            'resolucion' => Saneador::corto(is_string($datos['resolucion'] ?? null) ? $datos['resolucion'] : null, 20),
            'rol' => $usuario->es_administrador ? 'administrador' : 'personal',
            'contacto_nombre' => Saneador::corto(is_string($datos['contacto_nombre'] ?? null) ? $datos['contacto_nombre'] : null, 150),
            'contacto_correo' => Saneador::corto(is_string($datos['contacto_correo'] ?? null) ? $datos['contacto_correo'] : null, 190),
            'permite_contacto' => (bool) ($datos['permite_contacto'] ?? false),
            'adjuntar_diagnostico' => (bool) ($datos['adjuntar_diagnostico'] ?? false),
            'imagenes' => array_slice($imagenes, 0, (int) config('alojaflow.mejora_continua.imagenes_por_reporte')),
        ]);
    }

    /**
     * @return int cuántos se terminaron de mandar
     */
    public function enviarPendientes(): int
    {
        if (! Permiso::hayReceptor()) {
            return 0;
        }
        $enviados = 0;
        $pendientes = ReporteMejora::query()
            ->whereIn('envio', [ReporteMejora::ENVIO_PENDIENTE, ReporteMejora::ENVIO_ERROR])
            ->where('intentos', '<', self::INTENTOS_MAXIMOS)
            ->orderBy('id')->limit(20)->get();
        foreach ($pendientes as $reporte) {
            try {
                $this->enviar($reporte);
                $enviados++;
            } catch (ErrorReceptor $error) {
                $reporte->forceFill([
                    'envio' => $error->reintentable() ? ReporteMejora::ENVIO_ERROR : ReporteMejora::ENVIO_RECHAZADO,
                    'intentos' => $reporte->intentos + 1,
                    'ultimo_error' => mb_substr($error->getMessage(), 0, 255),
                ])->save();
                if ($error->motivo === 'sin_conexion' || $error->motivo === 'sin_receptor') {
                    break;
                }
            }
        }

        return $enviados;
    }

    /**
     * @throws ErrorReceptor
     */
    public function enviar(ReporteMejora $reporte): void
    {
        if ($reporte->folio === null) {
            $respuesta = $this->cliente->crearReporte($this->datosParaEnviar($reporte));
            $reporte->forceFill([
                'folio' => is_string($respuesta['folio'] ?? null) ? $respuesta['folio'] : null,
                'estado' => is_string($respuesta['estado'] ?? null) ? $respuesta['estado'] : 'nuevo',
            ])->save();
            if ($reporte->folio === null) {
                throw new ErrorReceptor('sin_folio', 0);
            }
        }
        $disco = Storage::disk('local');
        $imagenes = $reporte->imagenes ?? [];
        foreach ($imagenes as $indice => $ruta) {
            $orden = $indice + 1;
            if ($orden <= $reporte->imagenes_enviadas) {
                continue;
            }
            $contenido = $disco->exists($ruta) ? $disco->get($ruta) : null;
            if (is_string($contenido) && $contenido !== '') {
                try {
                    $this->cliente->subirImagen((string) $reporte->folio, $contenido, basename($ruta), $orden);
                } catch (ErrorReceptor $error) {
                    if ($error->reintentable()) {
                        throw $error;
                    }
                    // Esa imagen no la acepta el receptor: el texto ya llegó, y se sigue con las demás.
                }
            }
            $reporte->forceFill(['imagenes_enviadas' => $orden])->save();
        }

        foreach ($imagenes as $ruta) {
            $disco->delete($ruta);
        }
        $reporte->forceFill([
            'envio' => ReporteMejora::ENVIO_ENVIADO,
            'enviado_en' => now(),
            'ultimo_error' => null,
            'imagenes' => [],
            'imagenes_enviadas' => count($imagenes) ?: $reporte->imagenes_enviadas,
        ])->save();
    }

    /**
     * Si hay reportes ya enviados que el equipo todavía no cierra.
     */
    public function hayAbiertos(): bool
    {
        return ReporteMejora::query()->whereNotNull('folio')
            ->where(fn ($query) => $query->whereNull('estado')->orWhereNotIn('estado', ['resuelto', 'descartado', 'duplicado']))
            ->exists();
    }

    /**
     * Trae el estado y la respuesta del equipo de los reportes de esta instalación.
     *
     * @throws ErrorReceptor
     */
    public function sincronizar(): int
    {
        if (! ReporteMejora::query()->whereNotNull('folio')->exists()) {
            return 0;
        }
        $desde = Ajustes::obtener(self::AJUSTE_SINCRONIZADOS);
        $momento = now('UTC');
        $cambiados = 0;
        foreach ($this->cliente->reportes(is_string($desde) ? $desde : null) as $remoto) {
            $reporte = ReporteMejora::query()->where('clave', (string) ($remoto['clave'] ?? ''))->first();
            if ($reporte === null) {
                continue;
            }
            $estado = $remoto['estado'] ?? null;
            $reporte->forceFill([
                'folio' => is_string($remoto['folio'] ?? null) ? $remoto['folio'] : $reporte->folio,
                'estado' => is_string($estado) && in_array($estado, ReporteMejora::ESTADOS, true) ? $estado : $reporte->estado,
                'respuesta' => is_string($remoto['respuesta'] ?? null) ? mb_substr($remoto['respuesta'], 0, 5000) : null,
                'respondido_en' => is_string($remoto['respondido_en'] ?? null) ? Carbon::parse($remoto['respondido_en']) : null,
                'resuelto_en_version' => is_string($remoto['resuelto_en_version'] ?? null) ? mb_substr($remoto['resuelto_en_version'], 0, 20) : null,
            ]);
            if ($reporte->isDirty()) {
                $reporte->save();
                $cambiados++;
            }
        }
        Ajustes::guardar(self::AJUSTE_SINCRONIZADOS, $momento->toIso8601String());

        return $cambiados;
    }

    /**
     * @return array<string, mixed>
     */
    private function datosParaEnviar(ReporteMejora $reporte): array
    {
        $diagnostico = null;
        if ($reporte->adjuntar_diagnostico) {
            $diagnostico = app(ArmadorLatido::class)->armar();
            unset($diagnostico['identificacion']);
        }

        return array_filter([
            'clave' => $reporte->clave,
            'tipo' => $reporte->tipo,
            'titulo' => $reporte->titulo,
            'descripcion' => $reporte->descripcion,
            'pasos' => $reporte->pasos,
            'pantalla' => $reporte->pantalla,
            'version' => (string) config('alojaflow.version'),
            'idioma' => $reporte->idioma,
            'navegador' => $reporte->navegador,
            'resolucion' => $reporte->resolucion,
            'rol' => $reporte->rol,
            'contacto' => filled($reporte->contacto_correo) || filled($reporte->contacto_nombre) ? [
                'nombre' => $reporte->contacto_nombre,
                'correo' => $reporte->contacto_correo,
                'permite' => $reporte->permite_contacto,
            ] : null,
            'diagnostico' => $diagnostico,
        ], fn (mixed $valor): bool => $valor !== null);
    }
}
