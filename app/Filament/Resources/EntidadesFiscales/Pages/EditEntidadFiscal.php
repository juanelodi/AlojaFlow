<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\EntidadesFiscales\Pages;

use App\Filament\Resources\Base\EditaEnPropiedad;
use App\Filament\Resources\EntidadesFiscales\EntidadFiscalResource;
use Filament\Resources\Pages\EditRecord;

class EditEntidadFiscal extends EditRecord
{
    use EditaEnPropiedad;

    protected static string $resource = EntidadFiscalResource::class;
}
