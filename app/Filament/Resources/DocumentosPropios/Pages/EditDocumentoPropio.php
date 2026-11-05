<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\DocumentosPropios\Pages;

use App\Filament\Resources\Base\EditaEnPropiedad;
use App\Filament\Resources\DocumentosPropios\DocumentoPropioResource;
use Filament\Resources\Pages\EditRecord;

class EditDocumentoPropio extends EditRecord
{
    use EditaEnPropiedad;

    protected static string $resource = DocumentoPropioResource::class;
}
