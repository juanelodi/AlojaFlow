<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Caja;

use RuntimeException;

/**
 * No se pudo abrir o cerrar un turno. El mensaje ya viene traducido, para mostrarlo tal cual.
 */
final class TurnoRechazado extends RuntimeException
{
}
