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
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditUsuario extends EditRecord
{
    protected static string $resource = UsuarioResource::class;

    /** @var list<array<string, mixed>> */
    private array $accesos = [];

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var User $usuario */
        $usuario = $this->record;
        $data['accesos'] = Acceso::rolesDe($usuario);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->accesos = array_values($data['accesos'] ?? []);
        unset($data['accesos']);
        // Nadie se quita a sí mismo el acceso de administrador ni se desactiva: siempre queda un super administrador.
        if ($this->record->getKey() === auth()->id()) {
            unset($data['es_administrador'], $data['es_superadministrador'], $data['activo']);
        }

        return $data;
    }

    protected function afterSave(): void
    {
        /** @var User $usuario */
        $usuario = $this->record;
        Acceso::asignarRoles($usuario, $this->accesos);
        RegistroAuditoria::anotar('usuario.editar', $usuario, [
            'campos' => array_values(array_diff(array_keys($usuario->getChanges()), ['password', 'remember_token', 'updated_at'])),
            'accesos' => $this->accesos,
        ]);
    }
}
