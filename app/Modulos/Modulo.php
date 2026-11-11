<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Modulos;

/**
 * Lo que se puede prender y apagar en una instalación (D-069). Hoy, calendario, reservas, huéspedes, usuarios,
 * copias, actualizaciones, privacidad y el código fuente no están aquí: siempre están.
 */
enum Modulo: string
{
    case VariasPropiedades = 'varias_propiedades';
    case Subordinacion = 'subordinacion';
    case Motor = 'motor';
    case Pagos = 'pagos';
    case Ical = 'ical';
    case Cupones = 'cupones';
    case Grupos = 'grupos';
    case EstanciasLargas = 'estancias_largas';
    case Camping = 'camping';
    case Empresas = 'empresas';
    case Limpieza = 'limpieza';
    case AvisoEquipo = 'aviso_equipo';
    case Plano = 'plano';
    case Llegada = 'llegada';
    case Mensajes = 'mensajes';
    case Bitacora = 'bitacora';
    case Opiniones = 'opiniones';
    case Portal = 'portal';
    case Propietarios = 'propietarios';
    case Documentos = 'documentos';
    case Caja = 'caja';
    case Informes = 'informes';
    case Integraciones = 'integraciones';

    /**
     * @return list<self> los módulos sin los que este no funciona
     */
    public function requiere(): array
    {
        return match ($this) {
            self::Pagos => [self::Motor],
            default => [],
        };
    }

    /**
     * @return list<self> los módulos que dependen de este
     */
    public function dependientes(): array
    {
        return array_values(array_filter(self::cases(), fn (self $otro): bool => in_array($this, $otro->requiere(), true)));
    }

    public function etiqueta(): string
    {
        return __('modulos.lista.'.$this->value.'.nombre');
    }

    public function descripcion(): string
    {
        return __('modulos.lista.'.$this->value.'.descripcion');
    }

    /**
     * Cómo se agrupan en la pantalla de módulos.
     */
    public function grupo(): string
    {
        return match ($this) {
            self::VariasPropiedades, self::Subordinacion => 'propiedades',
            self::Motor, self::Pagos, self::Ical, self::Cupones, self::Grupos, self::EstanciasLargas, self::Camping, self::Empresas => 'reservas',
            self::Limpieza, self::AvisoEquipo, self::Plano, self::Llegada, self::Mensajes, self::Bitacora, self::Opiniones, self::Portal, self::Documentos => 'operacion',
            self::Caja, self::Informes, self::Propietarios => 'dinero',
            self::Integraciones => 'sistema',
        };
    }
}
