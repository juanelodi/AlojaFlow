<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Sincronizacion\HotelDruid;

/**
 * Por qué no se pudo copiar de una principal (subordinacion.md A5; SUB-017 y SUB-018 son de otra instalación de AlojaFlow).
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
    case ErrorInterno = 'SUB-098';
    case RespuestaDesconocida = 'SUB-099';

    /**
     * Con estos, volver a intentarlo no arregla nada o empeora las cosas (siete contraseñas erróneas bloquean la cuenta
     * de la principal): la principal queda en pausa hasta que alguien lo atienda, o hasta mañana si es el límite diario.
     */
    public function pausaLaPrincipal(): bool
    {
        return match ($this) {
            self::Credenciales, self::LimiteDiario, self::AnioInexistente, self::VersionNoAdmitida,
            self::DestinoNoPermitido, self::ConexionRechazada => true,
            default => false,
        };
    }

    public function etiqueta(): string
    {
        return __('sincronizacion.errores.'.$this->value);
    }
}
