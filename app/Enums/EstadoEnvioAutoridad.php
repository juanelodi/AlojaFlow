<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Enums;

/**
 * Una comunicación al registro de viajeros. La autoridad procesa por lotes y más tarde: «enviado» aún no quiere decir que la aceptó.
 */
enum EstadoEnvioAutoridad: string
{
    use ConEtiqueta;

    case Pendiente = 'pendiente';
    case FaltanDatos = 'falta_datos';
    case Enviado = 'enviado';
    case Aceptado = 'aceptado';
    case Rechazado = 'rechazado';
    case PorAnular = 'por_anular';
    case Anulando = 'anulando';
    case Anulado = 'anulado';

    public function color(): string
    {
        return match ($this) {
            self::Aceptado => 'success',
            self::Rechazado => 'danger',
            self::FaltanDatos => 'warning',
            self::Anulado => 'gray',
            default => 'info',
        };
    }

    /**
     * @return list<string> los que todavía cuentan como la comunicación vigente de la reserva
     */
    public static function vigentes(): array
    {
        return [self::Pendiente->value, self::FaltanDatos->value, self::Enviado->value, self::Aceptado->value, self::Rechazado->value];
    }
}
