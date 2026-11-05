<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\Reservas\RelationManagers;

use App\Filament\Resources\Comprobantes\AccionesComprobante;
use App\Filament\Resources\Reservas\ReservaResource;
use App\Models\Reserva;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Las transferencias que el huésped subió desde la página de su reserva.
 */
class ComprobantesRelationManager extends RelationManager
{
    protected static string $relationship = 'comprobantes';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('grupos.comprobante.titulo_lista');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Reserva && $ownerRecord->comprobantes()->exists() && in_array($ownerRecord->propiedad_id, ReservaResource::propiedadesGestionables(), true);
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return AccionesComprobante::tabla($table);
    }
}
