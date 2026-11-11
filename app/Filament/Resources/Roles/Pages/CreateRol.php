<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Resources\Roles\RolResource;
use App\Models\RegistroAuditoria;
use App\Seguridad\Acceso;
use Filament\Resources\Pages\CreateRecord;
use Spatie\Permission\Models\Role;

class CreateRol extends CreateRecord
{
    protected static string $resource = RolResource::class;

    protected static bool $canCreateAnother = false;

    /** @var list<string> */
    private array $permisos = [];

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->permisos = array_values(array_intersect((array) ($data['permisos'] ?? []), Acceso::PERMISOS));
        unset($data['permisos']);

        return $data + ['guard_name' => 'web', 'propiedad_id' => null];
    }

    protected function afterCreate(): void
    {
        /** @var Role $rol */
        $rol = $this->record;
        Acceso::guardarPermisosDeRol($rol, $this->permisos);
        RegistroAuditoria::anotar('rol.crear', $rol, ['nombre' => $rol->name, 'permisos' => $this->permisos]);
    }
}
