<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Sincronizacion\HotelDruid;

/**
 * Códigos de error de la sincronización (subordinacion.md A5).
 */
enum CodigoError: string
{
    case Conexion = 'SUB-001';
    case Tls = 'SUB-002';
    case Credenciales = 'SUB-003';
    case LimiteDiario = 'SUB-005';
    case AnioInexistente = 'SUB-006';
    case EscrituraPrincipal = 'SUB-007';
    case TamanoExcedido = 'SUB-008';
    case GzipDanado = 'SUB-009';
    case FormatoInvalido = 'SUB-010';
    case VersionNoAdmitida = 'SUB-011';
    case CopiaIncompleta = 'SUB-012';
    case BorradoFallido = 'SUB-013';
    case EnCurso = 'SUB-014';
    case TiempoAgotado = 'SUB-015';
    case DestinoNoPermitido = 'SUB-016';
    case PrincipalSinCompartir = 'SUB-017';
    case ConexionRechazada = 'SUB-018';
    case RespuestaDesconocida = 'SUB-099';

    /**
     * Errores que pausan la principal en lugar de reintentar en la siguiente vuelta.
     * Credenciales: siete intentos fallidos bloquean la cuenta de la principal (A3).
     */
    public function pausaLaPrincipal(): bool
    {
        return in_array($this, [self::Credenciales, self::ConexionRechazada, self::VersionNoAdmitida, self::DestinoNoPermitido, self::LimiteDiario], true);
    }

    public function etiqueta(): string
    {
        return __('sincronizacion.errores.'.$this->value);
    }
}
