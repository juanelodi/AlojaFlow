<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\Auditoria\Pages;

use App\Filament\Resources\Auditoria\RegistroAuditoriaResource;
use Filament\Resources\Pages\ListRecords;

class ListRegistrosAuditoria extends ListRecords
{
    protected static string $resource = RegistroAuditoriaResource::class;
}
