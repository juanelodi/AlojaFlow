<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Resources\Roles\RolResource;
use App\Models\RegistroAuditoria;
use App\Seguridad\Acceso;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role;

class EditRol extends EditRecord
{
    protected static string $resource = RolResource::class;

    /** @var list<string> */
    private array $permisos = [];

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()->visible(fn (Model $record): bool => RolResource::canDelete($record))];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Role $rol */
        $rol = $this->record;
        $data['permisos'] = $rol->permissions()->pluck('name')->all();

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->permisos = array_values(array_intersect((array) ($data['permisos'] ?? []), Acceso::PERMISOS));
        unset($data['permisos']);

        return $data;
    }

    protected function afterSave(): void
    {
        /** @var Role $rol */
        $rol = $this->record;
        Acceso::guardarPermisosDeRol($rol, $this->permisos);
        RegistroAuditoria::anotar('rol.editar', $rol, ['nombre' => $rol->name, 'permisos' => $this->permisos]);
    }
}
