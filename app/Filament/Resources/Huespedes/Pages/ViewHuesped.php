<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\Huespedes\Pages;

use App\Filament\Resources\Huespedes\HuespedResource;
use App\Models\Huesped;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewHuesped extends ViewRecord
{
    protected static string $resource = HuespedResource::class;

    protected function getHeaderActions(): array
    {
        return [EditAction::make()->visible(fn (Huesped $record): bool => HuespedResource::canEdit($record))];
    }
}
