<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Seguridad;

use App\Enums\ModoPropiedad;
use App\Models\Propiedad;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permisos por propiedad: cada usuario tiene un rol en cada propiedad a la que accede
 * (spatie/laravel-permission con equipos = propiedades). El administrador ve todo.
 */
final class Acceso
{
    public const OPERACION = 'operacion.ver';

    public const DATOS = 'datos.ver';

    public const INDICADORES = 'indicadores.ver';

    public const SINCRONIZACION = 'sincronizacion.gestionar';

    /** Crear y cambiar reservas, huéspedes y pagos en propiedades independientes (D-035). */
    public const RESERVAS = 'reservas.gestionar';

    /** Crear y cambiar unidades y tarifas en propiedades independientes (D-035). */
    public const CATALOGO = 'catalogo.gestionar';

    /** Estado de las unidades y tareas de limpieza y mantenimiento, en cualquier propiedad. */
    public const LIMPIEZA = 'limpieza.gestionar';

    /** Mandar mensajes a los huéspedes y ver lo enviado, en cualquier propiedad. */
    public const MENSAJES = 'mensajes.gestionar';

    /** Plantillas, automatizaciones, avisos a otros sistemas, calendarios iCal y motor. */
    public const AJUSTES = 'ajustes.gestionar';

    /** Registro de llegada, documentos legales y privacidad de los huéspedes, en cualquier propiedad. */
    public const HUESPEDES = 'huespedes.gestionar';

    /** Datos fiscales, facturas, recibos y exportaciones para el contador. */
    public const FACTURACION = 'facturacion.gestionar';

    public const PERMISOS = [self::OPERACION, self::DATOS, self::INDICADORES, self::SINCRONIZACION, self::RESERVAS, self::CATALOGO, self::LIMPIEZA, self::MENSAJES, self::AJUSTES, self::HUESPEDES, self::FACTURACION];

    /** Roles y sus permisos (modelo-datos.md §4.1). */
    public const ROLES = [
        'gerente' => self::PERMISOS,
        'recepcion' => [self::OPERACION, self::DATOS, self::RESERVAS, self::LIMPIEZA, self::MENSAJES, self::HUESPEDES],
        'limpieza' => [self::OPERACION, self::LIMPIEZA],
        'mantenimiento' => [self::OPERACION, self::LIMPIEZA],
        'contador' => [self::DATOS, self::INDICADORES, self::FACTURACION],
    ];

    /**
     * Propiedades activas en las que el usuario tiene el permiso.
     *
     * @return list<int>
     */
    public static function propiedades(?User $usuario, string $permiso): array
    {
        if ($usuario === null || ! $usuario->activo) {
            return [];
        }

        if ($usuario->es_administrador) {
            return Propiedad::query()->where('activa', true)->orderBy('nombre')->pluck('id')->map(fn (mixed $id): int => (int) $id)->values()->all();
        }

        return DB::table('model_has_roles as mr')
            ->join('role_has_permissions as rp', 'rp.role_id', '=', 'mr.role_id')
            ->join('permissions as p', 'p.id', '=', 'rp.permission_id')
            ->join('propiedades as pr', 'pr.id', '=', 'mr.propiedad_id')
            ->where('mr.model_type', $usuario->getMorphClass())
            ->where('mr.model_id', $usuario->id)
            ->where('p.name', $permiso)
            ->where('pr.activa', true)
            ->distinct()
            ->orderBy('pr.nombre')
            ->get(['mr.propiedad_id', 'pr.nombre'])
            ->map(fn (object $fila): int => (int) $fila->propiedad_id)
            ->values()
            ->all();
    }

    /**
     * De las propiedades con ese permiso, las independientes: las únicas donde se escribe.
     *
     * @return list<int>
     */
    public static function propiedadesIndependientes(?User $usuario, string $permiso): array
    {
        return Propiedad::query()->whereIn('id', self::propiedades($usuario, $permiso))
            ->where('modo', ModoPropiedad::Nativa->value)
            ->orderBy('nombre')->pluck('id')->map(fn (mixed $id): int => (int) $id)->values()->all();
    }

    public static function puede(?User $usuario, string $permiso, ?int $propiedadId = null): bool
    {
        $propiedades = self::propiedades($usuario, $permiso);

        return $propiedadId === null ? $propiedades !== [] : in_array($propiedadId, $propiedades, true);
    }

    public static function usuarioActual(): ?User
    {
        $usuario = auth()->user();

        return $usuario instanceof User ? $usuario : null;
    }

    /**
     * Roles del usuario por propiedad, para el formulario de usuarios.
     *
     * @return list<array{propiedad_id: int, rol: string}>
     */
    public static function rolesDe(User $usuario): array
    {
        return DB::table('model_has_roles as mr')
            ->join('roles as r', 'r.id', '=', 'mr.role_id')
            ->where('mr.model_type', $usuario->getMorphClass())
            ->where('mr.model_id', $usuario->id)
            ->whereNotNull('mr.propiedad_id')
            ->orderBy('mr.propiedad_id')
            ->get(['mr.propiedad_id', 'r.name'])
            ->map(fn (object $fila): array => ['propiedad_id' => (int) $fila->propiedad_id, 'rol' => (string) $fila->name])
            ->values()
            ->all();
    }

    /**
     * Sustituye los roles del usuario: una fila por propiedad.
     *
     * @param  list<array{propiedad_id: int|string, rol: string}>  $filas
     */
    public static function asignarRoles(User $usuario, array $filas): void
    {
        DB::transaction(function () use ($usuario, $filas): void {
            DB::table('model_has_roles')->where('model_type', $usuario->getMorphClass())->where('model_id', $usuario->id)->delete();
            $roles = Role::query()->whereNull('propiedad_id')->pluck('id', 'name');
            $vistas = [];
            foreach ($filas as $fila) {
                $propiedadId = (int) $fila['propiedad_id'];
                if (isset($vistas[$propiedadId]) || ! isset($roles[$fila['rol']])) {
                    continue;
                }
                $vistas[$propiedadId] = true;
                DB::table('model_has_roles')->insert([
                    'role_id' => $roles[$fila['rol']],
                    'model_type' => $usuario->getMorphClass(),
                    'model_id' => $usuario->id,
                    'propiedad_id' => $propiedadId,
                ]);
            }
        });
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * @return array<string, string> rol => etiqueta
     */
    public static function opcionesRol(): array
    {
        $opciones = [];
        foreach (array_keys(self::ROLES) as $rol) {
            $opciones[$rol] = __('ui.roles.'.$rol);
        }

        return $opciones;
    }
}
