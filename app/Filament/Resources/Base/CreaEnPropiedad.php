<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\Base;

use App\Models\RegistroAuditoria;
use Illuminate\Support\Str;

/**
 * Alta en un catálogo de propiedad: comprueba la propiedad y lo anota en la auditoría.
 */
trait CreaEnPropiedad
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (! in_array((int) ($data['propiedad_id'] ?? 0), $this->propiedadesPermitidas(), true)) {
            abort(403);
        }

        return $this->completarDatos($data);
    }

    /**
     * @return list<int>
     */
    private function propiedadesPermitidas(): array
    {
        /** @var class-string<RecursoDePropiedad> $recurso */
        $recurso = static::getResource();

        return $recurso::propiedadesGestionables();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function completarDatos(array $data): array
    {
        return $data;
    }

    protected function afterCreate(): void
    {
        RegistroAuditoria::anotar(Str::snake(class_basename($this->record)).'.crear', $this->record, [], (int) $this->record->getAttribute('propiedad_id'));
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
