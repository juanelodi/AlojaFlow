<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Filament\Resources\Grupos\RelationManagers;

use App\Filament\Resources\Comprobantes\AccionesComprobante;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ComprobantesRelationManager extends RelationManager
{
    protected static string $relationship = 'comprobantes';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('grupos.comprobante.titulo_lista');
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
