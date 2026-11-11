<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Filament\Resources\Usuarios\Pages;

use App\Filament\Resources\Usuarios\UsuarioResource;
use App\Models\RegistroAuditoria;
use App\Models\User;
use App\Seguridad\Acceso;
use Filament\Resources\Pages\CreateRecord;

class CreateUsuario extends CreateRecord
{
    protected static string $resource = UsuarioResource::class;

    /** @var list<array<string, mixed>> */
    private array $accesos = [];

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->accesos = array_values($data['accesos'] ?? []);
        unset($data['accesos']);

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var User $usuario */
        $usuario = $this->record;
        Acceso::asignarRoles($usuario, $this->accesos);
        RegistroAuditoria::anotar('usuario.crear', $usuario, ['accesos' => $this->accesos]);
    }
}
